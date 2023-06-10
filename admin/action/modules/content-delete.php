<?php

use Sunlight\Database\Database as DB;
use Sunlight\GenericTemplates;
use Sunlight\Message;
use Sunlight\Page\Page;
use Sunlight\Page\PageManipulator;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

$continue = false;

if (isset($_GET['id'])) {
    $id = (int) Request::get('id');
    $query = DB::queryRow('SELECT id,node_level,node_depth,node_parent,title,type,type_idt,ord FROM ' . DB::table('page') . ' WHERE id=' . $id);

    if ($query !== false && User::hasPrivilege('admin' . Page::TYPES[$query['type']])) {
        $continue = true;
    }
}

if ($continue) {
    // removing child pages requires privileges for all page types
    $recursive = true;

    foreach (Page::TYPES as $type) {
        if (!User::hasPrivilege('admin' . $type)) {
            $recursive = false;
            break;
        }
    }

    // delete
    if (isset($_POST['confirm'])) {
        $error = null;

        if (!PageManipulator::delete($query, $recursive, $error)) {
            // failure
            $output .= Message::error($error, true);

            return;
        }

        // redirect
        $_admin->redirect(Router::admin('content', ['query' => ['done' => 1]]));

        return;
    }

    // output
    $content_array = PageManipulator::listDependencies($query, $recursive);

    $output .= '
    <p class="bborder">' . _lang('admin.content.delete.p') . '</p>
    <h2>' . _lang('global.item') . ' <em>' . $query['title'] . '</em></h2><br>
    ' . (!empty($content_array)
            ? '<p>' . _lang('admin.content.delete.contentlist') . ':</p>'
                . GenericTemplates::renderMessageList($content_array, ['escape' => false])
                . '<div class="hr"><hr></div>'
            : '')
    . '

    <form class="cform" action="' . _e(Router::admin('content-delete', ['query' => ['id' => $id]])) . '" method="post">
    <input type="hidden" name="confirm" value="1">
    <input type="submit" value="' . _lang('admin.content.delete.confirm') . '">
    ' . Xsrf::getInput() . '</form>
    ';
} else {
    $output .= Message::error(_lang('global.badinput'));
}
