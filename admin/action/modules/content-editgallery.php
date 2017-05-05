<?php

if (!defined('_root')) {
    exit;
}

/* ---  nastaveni a vlozeni skriptu pro upravu stranky  --- */

$type = _page_gallery;
require _root . 'admin/action/modules/include/page-editscript-init.php';
if ($continue) {

    if (!$new) {
        $editscript_extra = "<p><a class='button' href='index.php?p=content-manageimgs&amp;g=" . $id . "'><img src='images/icons/edit.png' alt='edit' class='icon'><big>" . $_lang['admin.content.form.manageimgs'] . "</big></a></p>";
    }

    $custom_settings = "
  <input type='text' name='var1' value='" . $query['var1'] . "' class='inputmini'> " . $_lang['admin.content.form.imgsperrow'] . ",
  <input type='text' name='var2' value='" . $query['var2'] . "' class='inputmini'> " . $_lang['admin.content.form.imgsperpage'] . "
  </span> <span class='customsettings'>
  <input type='text' name='var4' value='" . $query['var4'] . "' class='inputmini'> " . $_lang['admin.content.form.prevwidth'] . " 
  <input type='text' name='var3' value='" . $query['var3'] . "' class='inputmini'> " . $_lang['admin.content.form.prevheight'] . "
  ";

    $custom_save_array = array(
        'var1' => array('type' => 'int', 'nullable' => true),
        'var2' => array('type' => 'int', 'nullable' => true),
        'var3' => array('type' => 'int', 'nullable' => true),
        'var4' => array('type' => 'int', 'nullable' => true),
    );

}
require _root . 'admin/action/modules/include/page-editscript.php';
