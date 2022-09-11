<?php

use Sunlight\Page\Page;
use Sunlight\Util\Form;

defined('SL_ROOT') or exit;

$type = Page::CATEGORY;
require SL_ROOT . 'admin/action/modules/include/page-editscript-init.php';

if ($continue) {
    // order type select
    $artorder_select = '';

    for ($x = 1; $x <= 4; $x++) {
        if ($x == $query['var1']) {
            $selected = ' selected';
        } else {
            $selected = '';
        }

        $artorder_select .= '<option value="' . $x . '"' . $selected . '>' . _lang('admin.content.form.artorder.' . $x) . '</option>';
    }

    $custom_settings = '
    <tr><td colspan="2"><label><input type="checkbox" name="var3" value="1"' . Form::activateCheckbox($query['var3']) . '> ' . _lang('admin.content.form.showinfo') . '</label></td></tr>
    <tr><td colspan="2"><label><input type="checkbox" name="var4" value="1"' . Form::activateCheckbox($query['var4']) . '> ' . _lang('admin.content.form.showpics') . '</label></td></tr>
    <tr><td><select name="var1" class="selectmax">' . $artorder_select . '</select></td><td>'._lang('admin.content.form.artorder') . '</td></tr>
    <tr><td><input type="number" min="1" name="var2" value="' . $query['var2'] . '" class="inputmax"></td><td>' . _lang('admin.content.form.artsperpage') . '</td></tr>
  ';

    $custom_save_array = [
        'var1' => ['type' => 'int', 'nullable' => false],
        'var2' => ['type' => 'int', 'nullable' => true],
        'var3' => ['type' => 'bool', 'nullable' => false],
        'var4' => ['type' => 'bool', 'nullable' => false],
    ];
}

require SL_ROOT . 'admin/action/modules/include/page-editscript.php';
