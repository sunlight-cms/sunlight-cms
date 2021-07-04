<?php

use Sunlight\Page\Page;
use Sunlight\Util\Form;

defined('_root') or exit;

/* ---  nastaveni a vlozeni skriptu pro upravu stranky  --- */

$type = Page::GROUP;
require _root . 'admin/action/modules/include/page-editscript-init.php';
if ($continue) {
    $custom_settings = "
  <tr><td colspan='2'><label><input type='checkbox' name='var1' value='1'" . Form::activateCheckbox($query['var1']) . "> " . _lang('admin.content.form.showinfo') . "</label></td></tr>
  ";
    $custom_save_array = [
        'var1' => ['type' => 'bool', 'nullable' => false],
    ];
}
require _root . 'admin/action/modules/include/page-editscript.php';
