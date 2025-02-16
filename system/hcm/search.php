<?php

use Sunlight\Hcm;
use Sunlight\Router;
use Sunlight\Search\Search;
use Sunlight\Settings;
use Sunlight\Util\Form;

return function () {
    if (Settings::get('search')) {
        $output = Form::start('search-' . Hcm::$uid, ['action' => Router::module('search'), 'class' => 'searchform', 'method' => 'get']) . "\n";

        foreach (Search::getSources() as $source) {
            if ($source->isEnabledByDefault()) {
                $output .= Form::input('hidden', $source->getKey(), '1') . "\n";
            }
        }

        $output .= Form::input('search', 'q', null, ['class' => 'search-query']) . ' ' . Form::input('submit', null, _lang('mod.search.submit')) . "\n"
            . Form::end('search-' . Hcm::$uid) . "\n";

        return $output;
    }
};
