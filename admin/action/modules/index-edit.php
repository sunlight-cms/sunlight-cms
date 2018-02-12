<?php

use Sunlight\Core;

if (!defined('_root')) {
    exit;
}

/* ---  zpracovani ulozeni  --- */

if (isset($_POST['text'])) {
    Core::updateSetting('admin_index_custom', trim(_post('text')));
    Core::updateSetting('admin_index_custom_pos', (_post('pos') == 0) ? '0' : '1');
    $admin_redirect_to = 'index.php?p=index-edit&saved';

    return;
}

$admin_index_cfg = Core::loadSettings(array('admin_index_custom', 'admin_index_custom_pos'));

/* ---  vystup  --- */

$output .= "

<p class='bborder'>" . _lang('admin.menu.index.edit.p') . "</p>

" . (isset($_GET['saved']) ? _msg(_msg_ok, _lang('global.saved')) : '') . "

<form method='post'>

<table class='formtable'>

<tr>
    <th>" . _lang('admin.menu.index.edit.pos') . "</th>
    <td><select name='pos'>
        <option value='0'" . (($admin_index_cfg['admin_index_custom_pos'] == 0) ? " selected" : '') . ">" . _lang('admin.menu.index.edit.pos.0') . "</option>
        <option value='1'" . (($admin_index_cfg['admin_index_custom_pos'] == 1) ? " selected" : '') . ">" . _lang('admin.menu.index.edit.pos.1') . "</option>
    </select></td>
</tr>

<tr class='valign-top'>
    <th>" . _lang('admin.menu.index.edit.text') . "</th>
    <td class='minwidth'><textarea name='text' rows='25' cols='94' class='areabig editor'>" . _e($admin_index_cfg['admin_index_custom']) . "</textarea></td>
</tr>

<tr>
    <td></td>
    <td><input type='submit' class='button bigger' value='" . _lang('global.savechanges') . "' accesskey='s'></td>
</tr>

</table>

" . _xsrfProtect() . "</form>
";
