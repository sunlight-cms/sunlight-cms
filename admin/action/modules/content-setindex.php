<?php

use Sunlight\Admin\Admin;
use Sunlight\Message;
use Sunlight\Page\Page;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

$message = '';

// action
if (isset($_POST['index'])) {
    $index_id = (int) Request::post('index');
    $page = Page::getData($index_id, ['id', 'type']);

    if ($page === false || $page['type'] == Page::SEPARATOR) {
        $output .= Message::error(_lang('global.badinput'));
        return;
    }

    Settings::update('index_page_id', $index_id);
    $message = Message::ok(_lang('global.done'));
} else {
    $index_id = Settings::get('index_page_id');
}

// output
$output .= $message . '
<form class="cform" action="' . _e(Router::admin('content-setindex')) . '" method="post">
' . Admin::pageSelect('index', ['check_access' => false, 'selected' => $index_id, 'maxlength' => null]) . '
<input class="button" type="submit" value="' . _lang('global.do') . '">
' . Xsrf::getInput() . '</form>
';
