<?php

namespace Sunlight;

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
     */
    function check(): bool
    {
        $this->errors = [];

        $this->checkPaths();
        $this->checkInstallFiles();

        Extend::call('core.check', ['errors' => &$this->errors]);

        return empty($this->errors);
    }

    /**
     * See if there were any errors
     */
    function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Render errors as a plaintext list
     */
    function renderErrors(): string
    {
        $errors_str = '';

        for ($i = 0; isset($this->errors[$i]); ++$i) {
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
            $path = SL_ROOT . $this->paths[$i];

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
        if (@is_dir(SL_ROOT . 'install') && !Core::$debug) {
            $this->errors[] = [
                'Adresář install se stále nachází na serveru - po instalaci je třeba jej odstranit',
                'The install directory must be removed after installation',
            ];
        }

        if (file_exists(SL_ROOT . 'patch.php')) {
            $this->errors[] = [
                'Soubor patch.php se stále nachází na serveru - po aktualizaci databáze je třeba jej odstranit',
                'The patch.php file must be removed after the update',
            ];
        }
    }
}
