<?php

use Sunlight\Router;
use Sunlight\Search\Search;
use Sunlight\Settings;
use Sunlight\Util\Form;
use Sunlight\Xsrf;

return function () {
    if (Settings::get('search')) {
        $output = '<form action="' . _e(Router::module('search')) . '" method="get" class="searchform">' . "\n";

        foreach (Search::getSources() as $source) {
            if ($source->isEnabledByDefault()) {
                $output .= Form::input('hidden', $source->getKey(), '1') . "\n";
            }
        }

        $output .= Form::input('search', 'q', null, ['class' => 'search-query']) . ' ' . Form::input('submit', null, _lang('mod.search.submit')) . "\n"
            . Xsrf::getInput() . "\n"
            . "</form>\n";

        return $output;
    }
};
