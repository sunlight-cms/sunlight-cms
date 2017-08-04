<?php

if (!defined('_root')) {
    exit;
}

/* ---  nastaveni a vlozeni skriptu pro upravu stranky  --- */

$type = _page_group;
require _root . 'admin/action/modules/include/page-editscript-init.php';
if ($continue) {
    $custom_settings = "
  <tr><td colspan='2'><label><input type='checkbox' name='var1' value='1'" . _checkboxActivate($query['var1']) . "> " . _lang('admin.content.form.showinfo') . "</label></td></tr>
  ";
    $custom_save_array = array(
        'var1' => array('type' => 'bool', 'nullable' => false),
    );
}
require _root . 'admin/action/modules/include/page-editscript.php';
