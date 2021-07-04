<?php

use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\Xsrf;

return function () {
    if (Settings::get('search')) {
        return "<form action='" . _e(Router::module('search')) . "' method='get' class='searchform'>
" . (!Settings::get('pretty_urls') ? "<input type='hidden' name='m' value='search'>" : '') . "
<input type='hidden' name='page' value='1'>
<input type='hidden' name='art' value='1'>
<input type='hidden' name='post' value='1'>
<input type='hidden' name='img' value='1'>
" . Xsrf::getInput() . "
<input type='search' name='q' class='search-query'> <input type='submit' value='" . _lang('mod.search.submit') . "'>
</form>
";
    }
};
