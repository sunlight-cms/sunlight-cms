<?php

if (!defined('_root')) {
    exit;
}

/* ---  nastaveni a vlozeni skriptu pro upravu stranky  --- */

$type = _page_forum;
require _root . 'admin/action/modules/include/page-editscript-init.php';
if ($continue) {
    $custom_settings = "
  <label><input type='checkbox' name='var2' value='1'" . _checkboxActivate($query['var2']) . "> " . $_lang['admin.content.form.locked3'] . "</label> 
  <label><input type='checkbox' name='var3' value='1'" . _checkboxActivate($query['var3']) . "> " . $_lang['admin.content.form.unregpost'] . "</label> 
  ";
    if (!$new) {
        $custom_settings .= " <label><input type='checkbox' name='delposts' value='1'> " . $_lang['admin.content.form.deltopics'] . "</label><small>(" . DB::result(DB::query("SELECT COUNT(*) FROM " . _posts_table . " WHERE home=" . $id . " AND type=" . _post_forum_topic . " AND xhome=-1"), 0) . ")</small>";
    }
    $custom_settings .= " <input type='number' min='1' name='var1' value='" . $query['var1'] . "' class='inputmini'> " . $_lang['admin.content.form.topicssperpage'];
    $custom_save_array = array(
        'var1' => array('type' => 'int', 'nullable' => true),
        'var2' => array('type' => 'bool', 'nullable' => false),
        'var3' => array('type' => 'bool', 'nullable' => false),
        'delposts' => array('type' => 'bool', 'nullable' => true),
    );
}
require _root . 'admin/action/modules/include/page-editscript.php';
