<?php

namespace Sunlight;

use Sunlight\Util\Environment;

class SystemChecker
{
    /** @var array */
    private $paths = [
        'upload',
        'plugins',
        'plugins/extend',
        'plugins/languages',
        'plugins/templates',
        'system/tmp',
        'system/cache',
        'system/backup',
        'images',
        'images/articles',
        'images/avatars',
        'images/galleries',
        'images/thumb',
    ];
    /** @var array */
    private $errors = [];

    /**
     * Run system checks
     *
     * @return bool
     */
    function check(): bool
    {
        $this->errors = [];

        $this->checkPaths();
        $this->checkInstallFiles();
        $this->checkHtaccess();

        Extend::call('core.check', ['errors' => &$this->errors]);

        return empty($this->errors);
    }

    /**
     * See if there were any errors
     *
     * @return bool
     */
    function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Render errors as a plaintext list
     *
     * @return string
     */
    function renderErrors(): string
    {
        $errors_str = '';
        for($i = 0; isset($this->errors[$i]); ++$i) {
            $errors_str .= ($i + 1) . '. ' . $this->errors[$i][Core::$fallbackLang === 'cs' ? 0 : 1] . "\n";
        }

        return $errors_str;
    }

    /**
     * Check system paths
     */
    private function checkPaths(): void
    {
        for ($i = 0; isset($this->paths[$i]); ++$i) {
            $path = _root . $this->paths[$i];
            if (!is_dir($path)) {
                $this->errors[] = [
                    'Adresář /' . $this->paths[$i] . ' neexistuje nebo není dostupný ke čtení',
                    'The /' . $this->paths[$i] . ' directory does not exist or is not readable',
                ];
            } elseif (!is_writable($path)) {
                $this->errors[] = [
                    'Do adresáře /' . $this->paths[$i] . ' nelze zapisovat',
                    'The /' . $this->paths[$i] . ' directory is not writeable',
                ];
            }
        }
    }

    /**
     * Check installer / patch files
     */
    private function checkInstallFiles(): void
    {
        if (@is_dir(_root . 'install') && !_debug) {
            $this->errors[] = [
                'Adresář install se stále nachází na serveru - po instalaci je třeba jej odstranit',
                'The install directory must be removed after installation',
            ];
        }
        if (file_exists(_root . 'patch.php')) {
            $this->errors[] = [
                'Soubor patch.php se stále nachází na serveru - po aktualizaci databáze je třeba jej odstranit',
                'The patch.php file must be removed after the update',
            ];
        }
    }

    /**
     * Check .htaccess file
     */
    private function checkHtaccess(): void
    {
        // auto-generate .htaccess file if pretty urls are enabled
        // and the server is apache
        if (Environment::isApache()) {
            $generatedHtaccess = self::generateHtaccess();

            $htaccessPath = _root . '.htaccess';
            $htaccessExists = file_exists($htaccessPath);

            if ($htaccessExists) {
                // the .htaccess file already exists
                if (!_pretty_urls && file_get_contents($htaccessPath) === $generatedHtaccess) {
                    // delete the previously generated one
                    unlink($htaccessPath);
                }
            } elseif (_pretty_urls) {
                // generate it
                file_put_contents(_root . '.htaccess', $generatedHtaccess);
            }
        }
    }

    /**
     * Generate the .htaccess file
     *
     * @return string
     */
    static function generateHtaccess(): string
    {
        $basePath = preg_quote(Core::getBaseUrl()->getPath());

        return <<<HTACCESS
RewriteEngine On

RewriteCond %{REQUEST_URI} ^{$basePath}/m/([0-9a-zA-Z\.\-_]+)$ [NC]
RewriteRule .* {$basePath}/index.php?m=%1 [L,QSA]

RewriteCond %{REQUEST_URI} ^{$basePath}/([0-9a-zA-Z\.\-_/]+)$ [NC]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule .* {$basePath}/index.php?_rwp=%1 [L,QSA]
HTACCESS;
    }
}
