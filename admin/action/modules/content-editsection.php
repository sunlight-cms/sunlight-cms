<?php

use Sunlight\Database\Database as DB;
use Sunlight\Util\Form;

defined('_root') or exit;

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
  <tr><td colspan='2'><label><input type='checkbox' name='var1' value='1'" . Form::activateCheckbox($query['var1']) . "> " . _lang('admin.content.form.comments') . "</label></td></td></td></tr>
  <tr><td colspan='2'><label><input type='checkbox' name='var3' value='1'" . Form::activateCheckbox($query['var3']) . "> " . _lang('admin.content.form.commentslocked') . "</label></td></td></td></tr>
  ";
    if (!$new) {
        $custom_settings .= "<tr><td colspan='2'><label><input type='checkbox' name='delcomments' value='1'> " . _lang('admin.content.form.delcomments') . " <small>(" . DB::count(_posts_table, 'home=' . DB::val($id) . ' AND type=' . _post_section_comment) . ")</small></label></td></td></td></tr>";
    }
}
require _root . 'admin/action/modules/include/page-editscript.php';
