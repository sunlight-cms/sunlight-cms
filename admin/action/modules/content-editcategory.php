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
        $artorder_select .= '<option value="' . $x . '"' . ($x == $query['var1'] ? ' selected' : '') . '>' . _lang('admin.content.form.artorder.' . $x) . '</option>';
    }

    $artorder_choices = [];

    for ($x = 1; $x <= 4; ++$x) {
        $artorder_choices[$x] = _lang('admin.content.form.artorder.' . $x);
    }

    $custom_settings = '
    <tr><td colspan="2"><label>' . Form::input('checkbox', 'var3', '1', ['checked' => (bool) $query['var3']]) . ' ' . _lang('admin.content.form.showinfo') . '</label></td></tr>
    <tr><td colspan="2"><label>' . Form::input('checkbox', 'var4', '1', ['checked' => (bool) $query['var4']]) . ' ' . _lang('admin.content.form.showpics') . '</label></td></tr>
    <tr><td>' . Form::select('var1', $artorder_choices, $query['var1'], ['class' => 'selectmax']) . '</td><td>'._lang('admin.content.form.artorder') . '</td></tr>
    <tr><td>' . Form::input('number', 'var2', $query['var2'], ['class' => 'inputmax', 'min' => 1]) . '</td><td>' . _lang('admin.content.form.artsperpage') . '</td></tr>
  ';

    $custom_save_array = [
        'var1' => ['type' => 'int', 'nullable' => false],
        'var2' => ['type' => 'int', 'nullable' => true],
        'var3' => ['type' => 'bool', 'nullable' => false],
        'var4' => ['type' => 'bool', 'nullable' => false],
    ];
}

require SL_ROOT . 'admin/action/modules/include/page-editscript.php';
