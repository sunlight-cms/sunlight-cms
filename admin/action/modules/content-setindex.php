<?php

use Sunlight\Admin\Admin;
use Sunlight\Message;
use Sunlight\Page\Page;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\Util\Form;
use Sunlight\Util\Request;

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
' . Form::start('setindex', ['class' => 'cform', 'action' => Router::admin('content-setindex')]) . '
' . Admin::pageSelect('index', ['check_access' => false, 'selected' => $index_id, 'maxlength' => null]) . '
' . Form::input('submit', null, _lang('global.do'), ['class' => 'button']) . '
' . Form::end('setindex') . '
';
