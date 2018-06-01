<?php

use Sunlight\Util\Form;
use Sunlight\Util\UrlHelper;

defined('_root') or exit;

/* ---  nastaveni a vlozeni skriptu pro upravu stranky  --- */

$type = _page_link;
require _root . 'admin/action/modules/include/page-editscript-init.php';
if ($continue) {
    $editscript_enable_content = false;
    $editscript_enable_heading = false;
    $editscript_enable_perex = false;
    $editscript_enable_meta = false;
    $editscript_enable_layout = false;
    $editscript_enable_show_heading = false;
    $editscript_extra_row = "<tr class='valign-top'>
<th>" . _lang('admin.content.form.url') . (!$new ? " <a onclick='this.href=$(\"input[name=link_url]\").val()' href='" . (UrlHelper::isAbsolute($query['link_url']) ? '' : _root) . _e($query['link_url']) . "' target='_blank'><img src='images/icons/loupe.png' alt='prev'></a>" : '') . "</td>
<td colspan='3'>
<input class='inputmax' type='url' name='link_url' value='" . _e($query['link_url']) . "'>
</td>
</tr>";
    $custom_settings = "<tr><td colspan='2'><label><input type='checkbox' name='link_new_window' value='1'" . Form::activateCheckbox($query['link_new_window']) . "> " . _lang('admin.content.form.newwindow') . "</label></td></tr>";
    $custom_save_array = array(
        'link_url' => array('type' => 'raw', 'nullable' => true),
        'link_new_window' => array('type' => 'bool', 'nullable' => false),
    );
}
require _root . 'admin/action/modules/include/page-editscript.php';
