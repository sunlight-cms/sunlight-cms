<?php

use Sunlight\Post\Post;
use Sunlight\Database\Database as DB;
use Sunlight\Page\Page;
use Sunlight\Util\Form;

defined('SL_ROOT') or exit;

$type = Page::FORUM;
require SL_ROOT . 'admin/action/modules/include/page-editscript-init.php';
if ($continue) {
    $custom_settings = '
  <tr><td colspan="2"><label><input type="checkbox" name="var2" value="1"' . Form::activateCheckbox($query['var2']) . '> ' . _lang('admin.content.form.locked3') . '</label></td></tr> 
  <tr><td colspan="2"><label><input type="checkbox" name="var3" value="1"' . Form::activateCheckbox($query['var3']) . '> ' . _lang('admin.content.form.unregpost') . '</label></td></tr>
  ';
    if (!$new) {
        $custom_settings .= ' <tr><td colspan="2"><label><input type="checkbox" name="delposts" value="1"> ' . _lang('admin.content.form.deltopics') . '<small>(' . DB::count('post', 'home=' . DB::val($id) . ' AND type=' . Post::FORUM_TOPIC . ' AND xhome=-1') . ')</small></label></td></tr>';
    }
    $custom_settings .= '<tr><td><input type="number" min="1" name="var1" value="' . $query['var1'] . '" class="inputmax"></td><td>' . _lang('admin.content.form.topicssperpage') . '</td></tr>';
    $custom_save_array = [
        'var1' => ['type' => 'int', 'nullable' => true],
        'var2' => ['type' => 'bool', 'nullable' => false],
        'var3' => ['type' => 'bool', 'nullable' => false],
        'delposts' => ['type' => 'bool', 'nullable' => true],
    ];
}
require SL_ROOT . 'admin/action/modules/include/page-editscript.php';
