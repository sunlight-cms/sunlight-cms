<?php

use Sunlight\Page\Page;
use Sunlight\Util\Form;
use Sunlight\Util\UrlHelper;

defined('SL_ROOT') or exit;

/* ---  nastaveni a vlozeni skriptu pro upravu stranky  --- */

$type = Page::LINK;
require SL_ROOT . 'admin/action/modules/include/page-editscript-init.php';
if ($continue) {
    $editscript_enable_content = false;
    $editscript_enable_heading = false;
    $editscript_enable_perex = false;
    $editscript_enable_meta = false;
    $editscript_enable_layout = false;
    $editscript_enable_show_heading = false;
    $editscript_extra_row = "<tr class='valign-top'>
<th>" . _lang('admin.content.form.url') . (!$new ? " <a onclick='this.href=$(\"input[name=link_url]\").val()' href='" . (UrlHelper::isAbsolute($query['link_url']) ? '' : SL_ROOT) . _e($query['link_url']) . "' target='_blank'><img src='images/icons/loupe.png' alt='prev'></a>" : '') . "</td>
<td colspan='3'>
<input class='inputmax' type='url' name='link_url' value='" . _e($query['link_url']) . "'>
</td>
</tr>";
    $custom_settings = "<tr><td colspan='2'><label><input type='checkbox' name='link_new_window' value='1'" . Form::activateCheckbox($query['link_new_window']) . "> " . _lang('admin.content.form.newwindow') . "</label></td></tr>";
    $custom_save_array = [
        'link_url' => ['type' => 'raw', 'nullable' => true],
        'link_new_window' => ['type' => 'bool', 'nullable' => false],
    ];
}
require SL_ROOT . 'admin/action/modules/include/page-editscript.php';
