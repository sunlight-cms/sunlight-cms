<?php

defined('_root') or exit;

return function ()
{
    if (_search) {
        return "<form action='" . _linkModule('search') . "' method='get' class='searchform'>
" . (!_pretty_urls ? "<input type='hidden' name='m' value='search'>" : '') . "
<input type='hidden' name='root' value='1'>
<input type='hidden' name='art' value='1'>
<input type='hidden' name='post' value='1'>
<input type='hidden' name='img' value='1'>
" . _xsrfProtect() . "
<input type='search' name='q' class='search-query'> <input type='submit' value='" . _lang('mod.search.submit') . "'>
</form>
";
    }
};
