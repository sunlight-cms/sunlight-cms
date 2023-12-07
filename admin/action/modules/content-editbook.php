<?php

use Sunlight\Post\Post;
use Sunlight\Database\Database as DB;
use Sunlight\Page\Page;
use Sunlight\Util\Form;

defined('SL_ROOT') or exit;

$type = Page::BOOK;
require SL_ROOT . 'admin/action/modules/include/page-editscript-init.php';

if ($continue) {
    $custom_settings = '
  <tr><td colspan="2"><label>' . Form::input('checkbox', 'var3', '1', ['checked' => (bool) $query['var3']]) . ' ' . _lang('admin.content.form.locked') . '</label></td></tr> 
  <tr><td colspan="2"><label>' . Form::input('checkbox', 'var1', '1', ['checked' => (bool) $query['var1']]) . ' ' . _lang('admin.content.form.unregpost') . '</label></td></tr>
  ';

    if (!$new) {
        $custom_settings .= '<tr><td colspan="2"><label>'
            . Form::input('checkbox', 'delposts', '1') . ' ' . _lang('admin.content.form.delposts')
            . ' <small>(' . _num(DB::count('post', 'home=' . DB::val($id) . ' AND type=' . Post::BOOK_ENTRY)) . ')</small>'
            . '</label></td></tr>';
    }

    $custom_settings .= '<tr><td>' . Form::input('number', 'var2', $query['var2'], ['class' => 'inputmax', 'min' => 1]) . '</td><td>' . _lang('admin.content.form.postsperpage') . '</td></tr>';
    $custom_save_array = [
        'var1' => ['type' => 'bool', 'nullable' => false],
        'var2' => ['type' => 'int', 'nullable' => true],
        'var3' => ['type' => 'bool', 'nullable' => false],
        'delposts' => ['type' => 'bool', 'nullable' => true],
    ];
}

require SL_ROOT . 'admin/action/modules/include/page-editscript.php';
