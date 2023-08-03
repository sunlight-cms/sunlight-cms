<?php

use Sunlight\Util\Json;

return function (array $args) {
    $options =  $this->getConfig()['options'] + ['albumLabel' => _lang('lightbox.album_label')];

    $args['output'] .= '<script src="' . _e($this->getAssetPath('public/js/lightbox.js')) . '"></script>' . "\n";
    $args['output'] .= '<script>lightbox.option(' . Json::encodeForInlineJs($options) . ');</script>' . "\n";
};
