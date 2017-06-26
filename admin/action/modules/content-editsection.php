<?php

use Sunlight\Database\Database as DB;

if (!defined('_root')) {
    exit;
}

/* ---  nastaveni a vlozeni skriptu pro upravu stranky  --- */

$type = _page_section;
require _root . 'admin/action/modules/include/page-editscript-init.php';
if ($continue) {
    $custom_save_array = array(
        'var1' => array('type' => 'bool', 'nullable' => false),
        'var2' => array('type' => 'bool', 'nullable' => false),
        'var3' => array('type' => 'bool', 'nullable' => false),
        'delcomments' => array('type' => 'bool', 'nullable' => true),
    );
    $custom_settings = "
  <label><input type='checkbox' name='var1' value='1'" . _checkboxActivate($query['var1']) . "> " . _lang('admin.content.form.comments') . "</label> 
  <label><input type='checkbox' name='var3' value='1'" . _checkboxActivate($query['var3']) . "> " . _lang('admin.content.form.commentslocked') . "</label>
  ";
    if (!$new) {
        $custom_settings .= " <label><input type='checkbox' name='delcomments' value='1'> " . _lang('admin.content.form.delcomments') . "</label><small>(" . DB::count(_posts_table, 'home=' . DB::val($id) . ' AND type=' . _post_section_comment) . ")</small>";
    }
}
require _root . 'admin/action/modules/include/page-editscript.php';
