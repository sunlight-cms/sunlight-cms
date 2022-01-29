<?php declare(strict_types=1);

namespace Sunlight\ErrorHandler;

use Kuria\Error\Screen\WebErrorScreen as BaseWebErrorScreen;
use Kuria\Error\Screen\WebErrorScreenEvents;
use Sunlight\Core;
use Sunlight\Exception\CoreException;
use Sunlight\Extend;
use Sunlight\Router;

class WebErrorScreen extends BaseWebErrorScreen
{
    function __construct()
    {
        $this->on(WebErrorScreenEvents::RENDER, [$this, 'onRender']);
    }

    function onRender(array $view): void
    {
        $view['title'] = $view['heading'] = Core::$fallbackLang === 'cs'
            ? 'Chyba serveru'
            : 'Something went wrong';

        $view['text'] = Core::$fallbackLang === 'cs'
            ? 'Omlouváme se, ale při zpracovávání Vašeho požadavku došlo k neočekávané chybě.'
            : 'We are sorry, but an unexpected error has occurred while processing your request.';

        if ($view['exception'] instanceof CoreException) {
            $view['extras'] .= '<div class="group core-exception-info"><div class="section">';
            $view['extras'] .= '<p class="message">' . nl2br($this->escape($view['exception']->getMessage()), false) . '</p>';
            $view['extras'] .= '</div></div>';
            $view['extras'] .= '<a class="website-link" href="https://sunlight-cms.cz/" target="_blank">SunLight CMS ' . Core::VERSION . '</a>';
        }
    }

    protected function renderLayout(bool $debug, string $title, string $content): void
    {
        ?>
<!DOCTYPE html>
<html lang="<?= $this->escape(Core::$fallbackLang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if ($debug) : ?>
<link rel="icon" type="image/x-icon" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH4gELDAY3msRFdQAAAUZJREFUWMPllztSwzAQhj9pmIE2OQO5QLgHDEtNzpG7uMlAlQLzuEe4AJzBaUnlFBEMD1lvYWbYxg9ZK+2/+3lt+O+mUie2h8OVubyTMXbfQtdCl+NDZyy+BCbAxJz/TgqM9MfA27ehE2AntRUwCzSWoUZqK2CiPwVeBh6ZAa9SSwHjeO14ZC21FDDRnwOPQG+Z+37vAniSSinoTOVz2fdfxu7Vh6utwLR4Cj5h57MoLFUqdg4ForDUGdj5LAhLlYpdgAJBWOpM7HzmxfIoALt5xgbm7cHHIJbaE/2qQNNcJaUgArssLFVEt0spQi+WaiD6G+Da4cz1Kh6yW4GFU4GAbpezASuWthRsQio/IQUAzwJnVgwLYReNpa6AXRSWugJ2UViqUOwKFeEPLHVit1MFvrAbMQqEYFfLZhp4GPHXcMy1/4jtATk5XgJfpXWMAAAAAElFTkSuQmCC">
<?php endif ?>
<style>
<?php $this->renderCss($debug) ?>
</style>
<?php Extend::call('error_screen.head') ?>
<title><?= $this->escape($title) ?></title>
</head>

<body>
<div id="wrapper">
    <div id="content">
        <?= $content ?>
    </div>
</div>
<script src="<?= $this->escape(Router::path('system/js/jquery.js')) ?>"></script>
<script><?php $this->renderJs($debug) ?></script>
<?php Extend::call('error_screen.end') ?>
</body>
</html><?php
    }

    protected function renderCss(bool $debug): void
    {
        parent::renderCss($debug);

        ?>
body {background-color: #ededed; color: #000000;}
a {color: #ff6600;}
.core-exception-info {opacity: 0.8;}
.website-link {display: block; margin: 1em 0; text-align: center; color: #000000; opacity: 0.5;}
<?php
    }
}
