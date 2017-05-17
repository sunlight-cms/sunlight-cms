<?php

if (!defined('_root')) {
    exit;
}

/* ---  nastaveni a vlozeni skriptu pro upravu stranky  --- */

$type = _page_category;
require _root . 'admin/action/modules/include/page-editscript-init.php';
if ($continue) {

    // vyber zpusobu razeni clanku
    $artorder_select = "";
    for ($x = 1; $x <= 4; $x++) {
        if ($x == $query['var1']) {
            $selected = " selected";
        } else {
            $selected = "";
        }
        $artorder_select .= "<option value='" . $x . "'" . $selected . ">" . $_lang['admin.content.form.artorder.' . $x] . "</option>";
    }

    $custom_settings = $_lang['admin.content.form.artorder'] . " <select name='var1'>" . $artorder_select . "</select> " . $_lang['admin.content.form.artsperpage'] . " <input type='number' min='1' name='var2' value='" . $query['var2'] . "' class='inputmini'></span>
  </span> <span class='customsettings'>
  <label><input type='checkbox' name='var3' value='1'" . _checkboxActivate($query['var3']) . "> " . $_lang['admin.content.form.showinfo'] . "</label>
  <label><input type='checkbox' name='var4' value='1'" . _checkboxActivate($query['var4']) . "> " . $_lang['admin.content.form.showpics'] . "</label>
  ";

    $custom_save_array = array(
        'var1' => array('type' => 'int', 'nullable' => false),
        'var2' => array('type' => 'int', 'nullable' => true),
        'var3' => array('type' => 'bool', 'nullable' => false),
        'var4' => array('type' => 'bool', 'nullable' => false),
    );
}
require _root . 'admin/action/modules/include/page-editscript.php';
