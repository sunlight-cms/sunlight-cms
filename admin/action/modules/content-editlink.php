<?php

use Sunlight\Page\Page;
use Sunlight\Util\Form;

defined('SL_ROOT') or exit;

$type = Page::LINK;
require SL_ROOT . 'admin/action/modules/include/page-editscript-init.php';

if ($continue) {
    $editscript_enable_content = false;
    $editscript_enable_heading = false;
    $editscript_enable_perex = false;
    $editscript_enable_meta = false;
    $editscript_enable_layout = false;
    $editscript_enable_show_heading = false;
    $editscript_extra_row = '<tr class="valign-top">
<th>' . _lang('admin.content.form.url'). '</th>
<td>
' . Form::input('text', 'link_url', $query['link_url'] ?? '', ['class' => 'inputmax']) . '
</td>
</tr>';
    $custom_settings = '<tr><td colspan="2"><label>' . Form::input('checkbox', 'link_new_window', '1', ['checked' => (bool) $query['link_new_window']]) . ' ' . _lang('admin.content.form.newwindow') . '</label></td></tr>';
    $custom_save_array = [
        'link_url' => ['type' => 'raw', 'nullable' => true],
        'link_new_window' => ['type' => 'bool', 'nullable' => false],
    ];
}

require SL_ROOT . 'admin/action/modules/include/page-editscript.php';
