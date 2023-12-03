<?php

use Sunlight\Admin\Admin;
use Sunlight\Database\Database as DB;
use Sunlight\Message; 
use Sunlight\Page\Page;
use Sunlight\Page\PageManipulator;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

// handle change
if (isset($_POST['new_type'])) {
    // load page
    $page_id = (int) Request::post('page_id', '');
    $page = DB::queryRow('SELECT id,node_depth,type,type_idt FROM ' . DB::table('page') .  ' WHERE id=' . DB::val($page_id) . ' AND level<=' . User::getLevel());
    
    // load type
    $new_type = Request::post('new_type', '');
    $type_idt = null;

    if ($new_type == Page::PLUGIN || $new_type == Page::SEPARATOR) {
        $output .= Message::error(_lang('global.badinput'));
        return;
    }

    if (!isset(Page::TYPES[$new_type]) && isset(Page::getPluginTypes()[$new_type])) {
        $type_idt = $new_type;
        $new_type = Page::PLUGIN;
    }

    if (
        $page === false
        || !isset(Page::TYPES[$new_type])
        || !User::hasPrivilege('admin' . Page::TYPES[$page['type']])
        || !User::hasPrivilege('admin' . Page::TYPES[$new_type])
    ) {
        $output .= Message::error(_lang('global.badinput'));
        return;
    }

    // prepare changeset
    $initial_data = PageManipulator::getInitialData($new_type, $type_idt);

    $changeset = [
        'type' => $new_type,
        'type_idt' => $type_idt,
        'var1' => $initial_data['var1'],
        'var2' => $initial_data['var2'],
        'var3' => $initial_data['var3'],
        'var4' => $initial_data['var4'],
    ];

    // remove dependencies
    if (!PageManipulator::deleteDependencies($page, PageManipulator::DEPEND_DIRECT | PageManipulator::DEPEND_DIRECT_FORCE, $error)) {
        $output .= Message::error($error, true);
        return;        
    }

    // change type
    DB::update('page', 'id=' . DB::val($page_id), $changeset);

    $output .= Message::ok(_lang('global.done'));
    return;
}

// prepare type choices
$new_type_choices = [];

foreach (Page::TYPES as $type => $name) {
    if ($type !== Page::PLUGIN && $type !== Page::SEPARATOR && User::hasPrivilege('admin' . $name)) {
        $new_type_choices[$type] = _lang('page.type.' . $name);
    }
}

if (User::hasPrivilege('adminpluginpage')) {
    $new_type_choices += Page::getPluginTypes();
}

// output
$output .= _buffer(function () use ($new_type_choices) { ?>
    <?= Message::warning(_lang('admin.content.chtype.warning')) ?>

    <form method="post">
        <table class="formtable">
            <tr>
                <th><?= _lang('admin.content.form.page') ?></th>
                <td><?= Admin::pageSelect('page_id', ['check_privilege' => true, 'maxlength' => null]) ?></td>
            </tr>
            <tr>
                <th><?= _lang('admin.content.chtype.new_type') ?></th>
                <td>
                    <?= Form::select('new_type', $new_type_choices) ?>
                </td>
            </tr>
            <tr>
                <td></td>
                <td><?= Form::input('submit', null, _lang('global.do')) ?></td>
            </tr>
        </table>
        <?= Xsrf::getInput() ?>
    </form>
<?php });
