<?php

use Sunlight\Post\Post;
use Sunlight\Database\Database as DB;
use Sunlight\Page\Page;
use Sunlight\Util\Form;

defined('SL_ROOT') or exit;

$type = Page::SECTION;
require SL_ROOT . 'admin/action/modules/include/page-editscript-init.php';

if ($continue) {
    $custom_save_array = [
        'var1' => ['type' => 'bool', 'nullable' => false],
        'var2' => ['type' => 'bool', 'nullable' => false],
        'var3' => ['type' => 'bool', 'nullable' => false],
        'delcomments' => ['type' => 'bool', 'nullable' => true],
    ];
    $custom_settings = '
  <tr><td colspan="2"><label>' . Form::input('checkbox', 'var1', '1', ['checked' => (bool) $query['var1']]) . ' ' . _lang('admin.content.form.comments') . '</label></td></tr>
  <tr><td colspan="2"><label>' . Form::input('checkbox', 'var3', '1', ['checked' => (bool) $query['var3']]) . ' ' . _lang('admin.content.form.commentslocked') . '</label></td></tr>
  ';

    if (!$new) {
        $custom_settings .= '<tr><td colspan="2"><label>'
            . Form::input('checkbox', 'delcomments', '1') . ' ' . _lang('admin.content.form.delcomments')
            . ' <small>(' . _num(DB::count('post', 'home=' . DB::val($id) . ' AND type=' . Post::SECTION_COMMENT)) . ')</small>'
            . '</label></td></tr>';
    }
}

require SL_ROOT . 'admin/action/modules/include/page-editscript.php';
