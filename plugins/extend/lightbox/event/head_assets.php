<?php

use Sunlight\Core;
use Sunlight\Settings;
use Sunlight\Template;

return function (array $args) {
    $dark = $this->getConfig()['dark_mode'];

    if ($dark === null) {
        if (Core::$env === Core::ENV_ADMIN) {
            $dark = Settings::get('adminscheme_dark');
        } else {
            $dark = Template::getCurrent()->getOption('dark');
        }
    }

    $args['css']['lightbox'] = $this->getAssetPath('public/css/lightbox' . ($dark ? '-dark' : '') . '.css');
};
