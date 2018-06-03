<?php

namespace Sunlight\Util;

use Kuria\Debug\Output;

abstract class Response
{
    static function notFound()
    {
        header('HTTP/1.1 404 Not Found');
    }

    static function unauthorized()
    {
        header('HTTP/1.1 401 Unauthorized');
    }

    static function forbidden()
    {
        header('HTTP/1.1 403 Forbidden');
    }

    /**
     * Odeslat hlavicky pro presmerovani
     *
     * @param string $url       absolutni URL
     * @param bool   $permanent vytvorit permanentni presmerovani 1/0
     */
    static function redirect($url, $permanent = false)
    {
        header('HTTP/1.1 ' . ($permanent ? '301 Moved Permanently' : '302 Found'));
        header('Location: ' . $url);
    }

    /**
     * Navrat na predchozi stranku
     * Po provedeni presmerovani je skript ukoncen.
     *
     * @param string|null $url adresa pro navrat, null = {@see \Sunlight\Util\Response::getReturnUrl()}
     */
    static function redirectBack($url = null)
    {
        if ($url === null) {
            $url = static::getReturnUrl();
        }

        if (!headers_sent()) {
            static::redirect($url);
        } else {
            ?>
            <meta http-equiv="refresh" content="1;url=<?php echo _e($url) ?>">
            <p><a href="<?php echo _e($url) ?>"><?php echo _lang('global.continue') ?></a></p>
            <?php
        }

        exit;
    }

    /**
     * Ziskat navratovou adresu
     *
     * Jsou pouzity nasledujici adresy (v poradi priority):
     *
     * 1) parametr $url
     * 2) _get('_return')
     * 3) $_SERVER['HTTP_REFERER']
     *
     * @return string
     */
    static function getReturnUrl()
    {
        $specifiedUrl = Request::get('_return', '');
        $baseUrl = Url::base();
        $returnUrl = clone $baseUrl;

        if ($specifiedUrl !== '') {
            if ($specifiedUrl[0] === '/') {
                $returnUrl->path = $specifiedUrl;
            }  elseif ($specifiedUrl !== './') {
                $returnUrl->path = $returnUrl->path . '/' . $specifiedUrl;
            }
        } elseif (!empty($_SERVER['HTTP_REFERER'])) {
            $returnUrl = Url::parse($_SERVER['HTTP_REFERER']);
        }

        // pouzit vychozi URL, pokud ma zjistena navratova URL jiny hostname (prevence open redirection vulnerability)
        if ($baseUrl->host !== $returnUrl->host) {
            $returnUrl = $baseUrl;
        }

        return $returnUrl->generateAbsolute();
    }

    /**
     * Poslat hlavicky pro stazeni souboru
     *
     * @param string   $filename nazev souboru
     * @param int|null $filesize velikost souboru v bajtech, je-li znama
     */
    static function download($filename, $filesize = null)
    {
        header('Content-Type: application/octet-stream');
        header(sprintf('Content-Disposition: attachment; filename="%s"', $filename));

        if ($filesize !== null) {
            header(sprintf('Content-Length: %d', $filesize));
        }
    }

    /**
     * Stahnout lokalni soubor
     *
     * Skript NENI ukoncen po zavolani teto funkce.
     *
     * @param string      $filepath cesta k souboru
     * @param string|null $filename vlastni nazev souboru nebo null (= zjistit z $filepath)
     */
    static function downloadFile($filepath, $filename = null)
    {
        static::ensureHeadersNotSent();
        Filesystem::ensureFileExists($filepath);

        if ($filename === null) {
            $filename = basename($filepath);
        }

        Output::cleanBuffers();
        static::download($filename, filesize($filepath));

        $handle = fopen($filepath, 'rb');
        while (!feof($handle)) {
            echo fread($handle, 131072);
            flush();
        }
        fclose($handle);
    }

    /**
     * Ujistit se, ze jeste nebyly odeslany hlavicky
     *
     * @throws \RuntimeException pokud jiz byly hlavicky odeslany
     */
    static function ensureHeadersNotSent()
    {
        if (headers_sent($file, $line)) {
            throw new \RuntimeException(sprintf('Headers already sent (output started in "%s" on line %d)', $file, $line));
        }
    }
}
