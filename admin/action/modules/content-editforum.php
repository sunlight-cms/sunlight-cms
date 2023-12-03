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
  <tr><td colspan="2"><label>' . Form::input('checkbox', 'var2', '1', ['checked' => (bool) $query['var2']]) . ' ' . _lang('admin.content.form.locked3') . '</label></td></tr> 
  <tr><td colspan="2"><label>' . Form::input('checkbox', 'var3', '1', ['checked' => (bool) $query['var3']]) . ' ' . _lang('admin.content.form.unregpost') . '</label></td></tr>
  ';

    if (!$new) {
        $custom_settings .= ' <tr><td colspan="2"><label>'
            . Form::input('checkbox', 'delposts', '1') . ' ' . _lang('admin.content.form.deltopics')
            . ' <small>(' . _num(DB::count('post', 'home=' . DB::val($id) . ' AND type=' . Post::FORUM_TOPIC . ' AND xhome=-1')) . ')</small>'
            . '</label></td></tr>';
    }

    $custom_settings .= '<tr><td>' . Form::input('number', 'var1', $query['var1'], ['class' => 'inputmax', 'min' => 1]) . '</td><td>' . _lang('admin.content.form.topicssperpage') . '</td></tr>';
    $custom_save_array = [
        'var1' => ['type' => 'int', 'nullable' => true],
        'var2' => ['type' => 'bool', 'nullable' => false],
        'var3' => ['type' => 'bool', 'nullable' => false],
        'delposts' => ['type' => 'bool', 'nullable' => true],
    ];
}

require SL_ROOT . 'admin/action/modules/include/page-editscript.php';
