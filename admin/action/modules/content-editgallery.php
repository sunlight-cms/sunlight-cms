<?php

use Sunlight\Page\Page;

defined('SL_ROOT') or exit;

/* ---  nastaveni a vlozeni skriptu pro upravu stranky  --- */

$type = Page::GALLERY;
require SL_ROOT . 'admin/action/modules/include/page-editscript-init.php';
if ($continue) {

    if (!$new) {
        $editscript_extra = "<p><a class='button' href='index.php?p=content-manageimgs&amp;g=" . $id . "'><img src='images/icons/edit.png' alt='edit' class='icon'><span class='big-text'>" . _lang('admin.content.form.manageimgs') . "</span></a></p>";
    }

    $custom_settings = "
  <tr><td><label><input type='number' min='-1' name='var1' value='" . $query['var1'] . "' class='inputmax'></td><td>" . _lang('admin.content.form.imgsperrow') . "</td></tr>
  <tr><td><label><input type='number' min='1' name='var2' value='" . $query['var2'] . "' class='inputmax'></td><td>" . _lang('admin.content.form.imgsperpage') . "</td></tr>
 
  <tr><td><label><input type='number' min='10' max='1024' name='var4' value='" . $query['var4'] . "' class='inputmax'></td><td>" . _lang('admin.content.form.prevwidth') . "</td></tr> 
  <tr><td><label><input type='number' min='10' max='1024' name='var3' value='" . $query['var3'] . "' class='inputmax'></td><td>" . _lang('admin.content.form.prevheight') . "</td></tr>
";

    $custom_save_array = [
        'var1' => ['type' => 'int', 'nullable' => true],
        'var2' => ['type' => 'int', 'nullable' => true],
        'var3' => ['type' => 'int', 'nullable' => true],
        'var4' => ['type' => 'int', 'nullable' => true],
    ];

}
require SL_ROOT . 'admin/action/modules/include/page-editscript.php';
