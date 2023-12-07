<?php

use Sunlight\Admin\PageLister;
use Sunlight\Database\Database as DB;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\Util\Form;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

$message = '';

// action
if (isset($_POST['title']) && is_array($_POST['title'])) {
    foreach ($_POST['title'] as $id => $title) {
        $id = (int) $id;
        $title = _e(trim($title));

        if ($title == '') {
            $title = _lang('global.novalue');
        }

        DB::update('page', 'id=' . DB::val($id), ['title' => $title]);
    }

    $message = Message::ok(_lang('global.saved'));
}

// output
$output .= $message . '

<form action="' . _e(Router::admin('content-titles')) . '" method="post">
';

$output .= PageLister::render([
    'mode' => PageLister::MODE_SINGLE_LEVEL,
    'links' => false,
    'actions' => false,
    'breadcrumbs' => false,
    'title_editable' => true,
    'type' => true,
]);

$output .= '
    <p>
        ' . Form::input('submit', null, _lang('global.save'), ['accesskey' => 's']) . '
        ' . Form::input('reset', null, _lang('global.reset'), ['onclick' => 'return Sunlight.confirm();']) . '
    </p>
' . Xsrf::getInput() . '</form>';
