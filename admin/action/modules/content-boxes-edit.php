<?php

use Sunlight\Admin\Admin;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\Message;
use Sunlight\Plugin\TemplateService;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Html;
use Sunlight\Util\Math;
use Sunlight\Util\Request;
use Sunlight\Util\StringHelper;

defined('SL_ROOT') or exit;

$templates_to_choose_slot_from = null;

// load box
$id = Request::get('id');
$new = $id === null;

if (!$new) {
    $box = DB::queryRow('SELECT * FROM ' . DB::table('box') . ' WHERE id = ' . DB::val($id));
} else {
    $box = [
        'id' => null,
        'ord' => '',
        'title' => '',
        'content' => '',
        'visible' => 1,
        'public' => 1,
        'level' => 0,
        'template' => null,
        'layout' => null,
        'slot' => null,
        'page_ids' => null,
        'page_children' => 0,
        'class' => null,
    ];

    if (($template = Request::get('template')) !== null && TemplateService::templateExists($template)) {
        $templates_to_choose_slot_from = [TemplateService::getTemplate($template)];
    }
}

if ($box === false) {
    $output .= Message::error(_lang('global.badinput'));

    return;
}

// event
Extend::call('admin.box.edit', ['box' => &$box]);

// update or create box
if (isset($_POST['box_edit'])) do {
    $errors = [];

    $class = StringHelper::cut(StringHelper::trimExtraWhitespace(Request::post('class', '')), 255);

    $changeset = [
        'title' => Html::cut(_e(StringHelper::trimExtraWhitespace(Request::post('title'))), 255),
        'content' => User::filterContent(Html::cut(Request::post('content', ''), DB::MAX_MEDIUMTEXT_LENGTH)),
        'visible' => Form::loadCheckbox('visible'),
        'public' => Form::loadCheckbox('public'),
        'level' => Math::range((int) Request::post('level'), 0, User::MAX_LEVEL),
        'page_ids' => StringHelper::cut(
            implode(
                ',',
                array_filter(array_map('intval', (array) Request::post('page_ids', [], true)), function ($id) { return $id >= 1; })
            ),
            DB::MAX_TEXT_LENGTH
        ) ?: null,
        'page_children' => Form::loadCheckbox('page_children'),
        'class' => $class !== '' ? $class : null,
    ];

    // slot uid
    $template_components = TemplateService::getComponentsByUid(Request::post('slot_uid'), TemplateService::UID_TEMPLATE_LAYOUT_SLOT);

    if ($template_components !== null) {
        $changeset += [
            'template' => $template_components['template']->getName(),
            'layout' => $template_components['layout'],
            'slot' => $template_components['slot'],
        ];
    } else {
        $errors[] = _lang('admin.content.boxes.edit.badslot');
    }

    // ord
    $new_ord = trim(Request::post('ord', ''));

    if ($new_ord !== '') {
        $new_ord = Math::range((int) $new_ord, 0, null);
    }

    $changeset['ord'] = $new_ord;

    // event
    Extend::call('admin.box.save', ['changeset' => &$changeset, 'errors' => &$errors]);

    // merge changeset into runtime box data
    $box = $changeset + $box;

    // check for errors
    if ($errors) {
        $output .= Message::list($errors, ['type' => Message::ERROR]);
        break;
    }

    // auto order
    if ($changeset['ord'] === '') {
        $max_ord = DB::queryRow('SELECT MAX(ord) AS max_ord FROM ' . DB::table('box') . ' WHERE template=' . DB::val($changeset['template']) . ' AND layout=' . DB::val($changeset['layout']));

        if ($max_ord && $max_ord['max_ord']) {
            $changeset['ord'] = $max_ord['max_ord'] + 1;
        } else {
            $changeset['ord'] = 1;
        }
    }

    // save or create
    if (!$new) {
        DB::update('box', 'id=' . DB::val($id), $changeset);
    } else {
        $id = DB::insert('box', $changeset, true);
    }

    // redirect to form
    $_admin->redirect(Router::admin('content-boxes-edit', ['query' => ['id' => $id, ($new ? 'created' : 'saved') => 1]]));

    return;
} while (false);

// created message
if (isset($_GET['created'])) {
    $output .= Message::ok(_lang('global.created'));
} elseif (isset($_GET['saved'])) {
    $output .= Message::ok(_lang('global.saved'));
}

// form
$output .= _buffer(function () use ($id, $box, $new, $templates_to_choose_slot_from) { ?>
    <?= Form::start('box-edit', ['action' => Router::admin('content-boxes-edit', (!$new ? ['query' => ['id' => $id]] : null))]) ?>
        <table class="formtable">
            <tr>
                <th><?= _lang('admin.content.form.title') ?></th>
                <td><?= Form::input('text', 'title', Request::post('title', $box['title']), ['class' => 'inputbig', 'maxlength' => 255], false) ?></td>
            </tr>
            <tr>
                <th><?= _lang('admin.content.boxes.slot') ?></th>
                <td><?= Admin::templateLayoutSlotSelect('slot_uid', TemplateService::composeUid($box['template'], $box['layout'], $box['slot']), '', 'inputbig', $templates_to_choose_slot_from) ?></td>
            </tr>
            <tr>
                <th><?= _lang('admin.content.form.ord') ?></th>
                <td><?= Form::input('number', 'ord', Request::post('ord', $box['ord']), ['class' => 'inputsmall', 'min' => 0]) ?></td>
            </tr>
            <tr>
                <th><?= _lang('admin.content.form.content') ?></th>
                <td><?= Admin::editor('box-content', 'content', Request::post('content', $box['content']), ['rows' => 9, 'cols' => 33, 'class' => 'areasmallwide']) ?></td>
            </tr>
            <tr>
                <th><?= _lang('admin.content.form.class') ?></th>
                <td><?= Form::input('text', 'class', Request::post('class', $box['class']), ['class' => 'inputbig', 'maxlength' => 255]) ?></td>
            </tr>
            <tr>
                <th><?= _lang('admin.content.form.settings') ?></th>
                <td>
                    <label><?= Form::input('checkbox', 'visible', '1', ['checked' => Form::loadCheckbox('visible', $box['visible'], 'box_edit')]) ?> <?= _lang('admin.content.form.visible') ?></label>
                    <label><?= Form::input('checkbox', 'public', '1', ['checked' => Form::loadCheckbox('public', $box['public'], 'box_edit')]) ?> <?= _lang('admin.content.form.public') ?></label>
                    <label><?= Form::input('number', 'level', Request::post('level', $box['level']), ['class' => 'inputsmaller', 'min' => 0, 'max' => User::MAX_LEVEL]) ?> <?= _lang('admin.content.form.level') ?></label>
                </td>
            </tr>
            <tr class="valign-top">
                <th><?= _lang('admin.content.form.pages') ?></th>
                <td>
                    <?= Admin::pageSelect('page_ids[]', [
                        'multiple' => true,
                        'selected' => $box['page_ids'] !== null ? explode(',', $box['page_ids']) : [],
                        'attrs' => 'size="10" class="inputmax"',
                        'empty_item' => _lang('global.all'),
                        'check_access' => false,
                    ]) ?>
                    <p><label><?= Form::input('checkbox', 'page_children', '1', ['checked' => Form::loadCheckbox('page_children', $box['page_children'], 'box_edit')]) ?> <?= _lang('admin.content.form.include_subpages') ?></label>
                </td>
            </tr>

            <?= Extend::buffer('admin.box.form') ?>

            <tr>
                <td></td>
                <td>
                    <?= Form::input('submit', 'box_edit', _lang('global.savechanges'), ['class' => 'button bigger', 'accesskey' => 's']) ?>
                </td>
            </tr>
        </table>
    <?= Form::end('box-edit') ?>
<?php });
