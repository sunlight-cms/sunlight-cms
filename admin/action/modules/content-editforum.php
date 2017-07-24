<?php

use Sunlight\Database\Database as DB;

if (!defined('_root')) {
    exit;
}

/* ---  nastaveni a vlozeni skriptu pro upravu stranky  --- */

$type = _page_forum;
require _root . 'admin/action/modules/include/page-editscript-init.php';
if ($continue) {
    $custom_settings = "
  <tr><td colspan='2'><label><input type='checkbox' name='var2' value='1'" . _checkboxActivate($query['var2']) . "> " . _lang('admin.content.form.locked3') . "</label></td></tr> 
  <tr><td colspan='2'><label><input type='checkbox' name='var3' value='1'" . _checkboxActivate($query['var3']) . "> " . _lang('admin.content.form.unregpost') . "</label></td></tr>
  ";
    if (!$new) {
        $custom_settings .= " <tr><td colspan='2'><label><input type='checkbox' name='delposts' value='1'> " . _lang('admin.content.form.deltopics') . "<small>(" . DB::count(_posts_table, 'home=' . DB::val($id) . ' AND type=' . _post_forum_topic . ' AND xhome=-1') . ")</small></label></td></tr>";
    }
    $custom_settings .= "<tr><td><input type='number' min='1' name='var1' value='" . $query['var1'] . "' class='inputmax'></td><td>" . _lang('admin.content.form.topicssperpage') . "</td></tr>";
    $custom_save_array = array(
        'var1' => array('type' => 'int', 'nullable' => true),
        'var2' => array('type' => 'bool', 'nullable' => false),
        'var3' => array('type' => 'bool', 'nullable' => false),
        'delposts' => array('type' => 'bool', 'nullable' => true),
    );
}
require _root . 'admin/action/modules/include/page-editscript.php';
