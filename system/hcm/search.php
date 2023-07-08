<?php

use Sunlight\Router;
use Sunlight\Search\Search;
use Sunlight\Settings;
use Sunlight\Xsrf;

return function () {
    if (Settings::get('search')) {
        $output = '<form action="' . _e(Router::module('search')) . '" method="get" class="searchform">' . "\n";

        foreach (Search::getSources() as $source) {
            if ($source->isEnabledByDefault()) {
                $output .= '<input type="hidden" name="' . _e($source->getKey()) . '" value="1">' . "\n";
            }
        }

        $output .= '<input type="search" name="q" class="search-query"> <input type="submit" value="' . _lang('mod.search.submit') . "\">\n"
            . Xsrf::getInput() . "\n"
            . "</form>\n";

        return $output;
    }
};
