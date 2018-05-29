<?php

namespace Sunlight;

use Sunlight\Util\Url;

class SystemChecker
{
    /** @var array */
    private $paths = array(
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
    );
    /** @var array */
    private $errors = array();

    /**
     * Run system checks
     *
     * @return bool
     */
    function check()
    {
        $this->errors = array();

        $this->checkPaths();
        $this->checkInstallFiles();
        $this->checkHtaccess();

        Extend::call('core.check', array('errors' => &$this->errors));

        return empty($this->errors);
    }

    /**
     * See if there were any errors
     *
     * @return bool
     */
    function hasErrors()
    {
        return !empty($this->errors);
    }

    /**
     * Render errors as a plaintext list
     *
     * @return string
     */
    function renderErrors()
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
    private function checkPaths()
    {
        for ($i = 0; isset($this->paths[$i]); ++$i) {
            $path = _root . $this->paths[$i];
            if (!is_dir($path)) {
                $this->errors[] = array(
                    'Adresář /' . $this->paths[$i] . ' neexistuje nebo není dostupný ke čtení',
                    'The /' . $this->paths[$i] . ' directory does not exist or is not readable',
                );
            } elseif (!is_writeable($path)) {
                $this->errors[] = array(
                    'Do adresáře /' . $this->paths[$i] . ' nelze zapisovat',
                    'The /' . $this->paths[$i] . ' directory is not writeable',
                );
            }
        }
    }

    /**
     * Check installer / patch files
     */
    private function checkInstallFiles()
    {
        if (@is_dir(_root . 'install') && !_debug) {
            $this->errors[] = array(
                'Adresář install se stále nachází na serveru - po instalaci je třeba jej odstranit',
                'The install directory must be removed after installation',
            );
        }
        if (file_exists(_root . 'patch.php')) {
            $this->errors[] = array(
                'Soubor patch.php se stále nachází na serveru - po aktualizaci databáze je třeba jej odstranit',
                'The patch.php file must be removed after the update',
            );
        }
    }

    /**
     * Check .htaccess file
     */
    private function checkHtaccess()
    {
        // auto-generate .htaccess file if pretty urls are enabled
        // and the server is apache
        if (\Sunlight\Util\Environment::isApache()) {
            $generatedHtaccess = static::generateHtaccess();

            $htaccessPath = _root . '.htaccess';
            $htaccessExists = file_exists($htaccessPath);

            if ($htaccessExists) {
                // the .htaccess file already exists
                if (!_pretty_urls && file_get_contents($htaccessPath) === $generatedHtaccess) {
                    // delete the previously generated one
                    unlink($htaccessPath);
                }
            } else {
                // the .htaccess file does not exist
                if (_pretty_urls) {
                    // generate it
                    file_put_contents(_root . '.htaccess', $generatedHtaccess);
                }
            }
        }
    }

    /**
     * Generate the .htaccess file
     *
     * @return string
     */
    static function generateHtaccess()
    {
        $baseUrl = Url::base();

        return <<<HTACCESS
RewriteEngine On

RewriteCond %{REQUEST_URI} ^{$baseUrl->path}/m/([0-9a-zA-Z\.\-_]+)$ [NC]
RewriteRule .* {$baseUrl->path}/index.php?m=%1 [L,QSA]

RewriteCond %{REQUEST_URI} ^{$baseUrl->path}/([0-9a-zA-Z\.\-_/]+)$ [NC]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule .* {$baseUrl->path}/index.php?_rwp=%1 [L,QSA]
HTACCESS;
    }
}
