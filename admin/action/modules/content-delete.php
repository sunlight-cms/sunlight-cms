<?php

if (!defined('_root')) {
    exit;
}

$type_array = Sunlight\Page\PageManager::getTypes();

/* ---  priprava promennych  --- */

$continue = false;
if (isset($_GET['id'])) {
    $id = (int) _get('id');
    $query = DB::query("SELECT id,node_level,node_depth,node_parent,title,type,type_idt,ord FROM " . _root_table . " WHERE id=" . $id);
    if (DB::size($query) != 0) {
        $query = DB::row($query);
        if (_userHasRight('admin' . $type_array[$query['type']])) {
            $continue = true;
        }
    }
}

if ($continue) {

    // opravneni k mazani podstranek = pravo na vsechny typy
    $recursive = true;
    foreach (Sunlight\Page\PageManager::getTypes() as $type) {
        if (!_userHasRight('admin' . $type)) {
            $recursive = false;
            break;
        }
    }

    /* ---  odstraneni  --- */
    if (isset($_POST['confirm'])) {

        // smazani
        $error = null;
        if (!Sunlight\Page\PageManipulator::delete($query, $recursive, $error)) {
            // selhani
            $output .= _msg(_msg_err, $error);

            return;
        }

        // redirect
        $admin_redirect_to = 'index.php?p=content&done';

        return;

    }

    /* ---  vystup  --- */

    // pole souvisejicich polozek
    $content_array = Sunlight\Page\PageManipulator::listDependencies($query, $recursive);

    $output .= "
    <p class='bborder'>" . $_lang['admin.content.delete.p'] . "</p>
    <h2>" . $_lang['global.item'] . " <em>" . $query['title'] . "</em></h2><br>
    " . (!empty($content_array) ? "<p>" . $_lang['admin.content.delete.contentlist'] . ":</p>" . _msgList($content_array) . "<div class='hr'><hr></div>" : '') . "

    <form class='cform' action='index.php?p=content-delete&amp;id=" . $id . "' method='post'>
    <input type='hidden' name='confirm' value='1'>
    <input type='submit' value='" . $_lang['admin.content.delete.confirm'] . "'>
    " . _xsrfProtect() . "</form>
    ";

} else {
    $output .= _msg(_msg_err, $_lang['global.badinput']);
}
