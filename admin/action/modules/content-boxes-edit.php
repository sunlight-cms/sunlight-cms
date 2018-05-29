<?php

use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\Message;
use Sunlight\Plugin\TemplateService;
use Sunlight\Util\Math;

defined('_root') or exit;

$templates_to_choose_slot_from = null;

// fetch box data
$id = \Sunlight\Util\Request::get('id');
$new = $id === null;

if (!$new) {
    $box = DB::queryRow('SELECT * FROM ' . _boxes_table . ' WHERE id = ' . DB::val($id));
    $new = false;
} else {
    $box = array(
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
    );

    if (($template = \Sunlight\Util\Request::get('template')) !== null && TemplateService::templateExists($template)) {
        $templates_to_choose_slot_from = array(TemplateService::getTemplate($template));
    }
}

if ($box === false) {
    $output .= Message::error(_lang('global.badinput'));

    return;
}

// event
Extend::call('admin.box.edit', array('box' => &$box));

// update or create box
if (isset($_POST['box_edit'])) do {
    $errors = array();

    $changeset = array(
        'title' => \Sunlight\Util\Html::cut(_e(\Sunlight\Util\StringManipulator::trimExtraWhitespace(\Sunlight\Util\Request::post('title'))), 255),
        'content' => \Sunlight\Util\StringManipulator::trimExtraWhitespace(\Sunlight\Util\Request::post('content')),
        'visible' => \Sunlight\Util\Form::loadCheckbox('visible'),
        'public' => \Sunlight\Util\Form::loadCheckbox('public'),
        'level' => Math::range((int) \Sunlight\Util\Request::post('level'), 0, _priv_max_level),
        'page_ids' => implode(',', array_filter(array_map('intval', (array) \Sunlight\Util\Request::post('page_ids', array(), true)), function ($id) { return $id >= 1; })) ?: null,
        'page_children' => \Sunlight\Util\Form::loadCheckbox('page_children'),
        'class' => \Sunlight\Util\StringManipulator::ellipsis(\Sunlight\Util\StringManipulator::trimExtraWhitespace(\Sunlight\Util\Request::post('class')), 255, false),
    );

    // slot uid
    $template_components = TemplateService::getComponentsByUid(\Sunlight\Util\Request::post('slot_uid'), TemplateService::UID_TEMPLATE_LAYOUT_SLOT);

    if ($template_components !== null) {
        $changeset += array(
            'template' => $template_components['template']->getId(),
            'layout' => $template_components['layout'],
            'slot' => $template_components['slot'],
        );
    } else {
        $errors[] = _lang('admin.content.boxes.edit.badslot');
    }

    // ord
    $new_ord = trim(\Sunlight\Util\Request::post('ord'));

    if ($new_ord !== '') {
        $new_ord = Math::range((int) \Sunlight\Util\Request::post('ord'), 0, null);
    }

    $changeset['ord'] = $new_ord;

    // event
    Extend::call('admin.box.save', array('changeset' => &$changeset, 'errors' => &$errors));

    // merge changeset into runtime box data
    $box = $changeset + $box;

    // check for errors
    if ($errors) {
        $output .= Message::error(\Sunlight\Message::renderList($errors, 'errors'), true);
        break;
    }

    // auto order
    if ($changeset['ord'] === '') {
        $max_ord = DB::queryRow('SELECT MAX(ord) AS max_ord FROM ' . _boxes_table . ' WHERE template=' . DB::val($changeset['template']) . ' AND layout=' . DB::val($changeset['layout']));

        if ($max_ord && $max_ord['max_ord']) {
            $changeset['ord'] = $max_ord['max_ord'] + 1;
        } else {
            $changeset['ord'] = 1;
        }
    }

    // save or create
    if (!$new) {
        DB::update(_boxes_table, 'id=' . DB::val($id), $changeset);
    } else {
        $id = DB::insert(_boxes_table, $changeset, true);
    }

    // redirect to form
    $admin_redirect_to = 'index.php?p=content-boxes-edit&id=' . rawurlencode($id) . '&' . ($new ? 'created' : 'saved');

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
    <form method="post" action="index.php?p=content-boxes-edit<?php if (!$new): ?>&amp;id=<?php echo _e($id) ?><?php endif ?>">
        <table class="formtable">
            <tr>
                <th><?php echo _lang('admin.content.form.title') ?></th>
                <td><input type="text" class="inputbig" maxlength="255"<?php echo \Sunlight\Util\Form::restorePostValueAndName('title', $box['title'], false) ?>></td>
            </tr>
            <tr>
                <th><?php echo _lang('admin.content.boxes.slot') ?></th>
                <td><?php echo \Sunlight\Admin\Admin::templateLayoutSlotSelect('slot_uid', TemplateService::composeUid($box['template'], $box['layout'], $box['slot']), '', 'inputbig', $templates_to_choose_slot_from) ?></td>
            </tr>
            <tr>
                <th><?php echo _lang('admin.content.form.ord') ?></th>
                <td><input type="number" min="0" class="inputsmall"<?php echo \Sunlight\Util\Form::restorePostValueAndName('ord', $box['ord']) ?>></td>
            </tr>
            <tr>
                <th><?php echo _lang('admin.content.form.content') ?></th>
                <td><textarea class="areasmallwide editor" name="content" rows="9" cols="33"><?php echo \Sunlight\Util\Form::restorePostValue('content', $box['content'], false) ?></textarea></td>
            </tr>
            <tr>
                <th><?php echo _lang('admin.content.form.class') ?></th>
                <td><input type="text" class="inputbig" maxlength="255"<?php echo \Sunlight\Util\Form::restorePostValueAndName('class', $box['class']) ?>></td>
            </tr>
            <tr>
                <th><?php echo _lang('admin.content.form.settings') ?></th>
                <td>
                    <label><input type="checkbox"<?php echo \Sunlight\Util\Form::restoreCheckedAndName('box_edit', 'visible', $box['visible']) ?>> <?php echo _lang('admin.content.form.visible') ?></label>
                    <label><input type="checkbox"<?php echo \Sunlight\Util\Form::restoreCheckedAndName('box_edit', 'public', $box['public']) ?>> <?php echo _lang('admin.content.form.public') ?></label>
                    <label><input type="number" min="0" max="<?php echo _priv_max_level ?>" class="inputsmaller"<?php echo \Sunlight\Util\Form::restorePostValueAndName('level', $box['level']) ?>> <?php echo _lang('admin.content.form.level') ?></label>
                </td>
            </tr>
            <tr class="valign-top">
                <th><?php echo _lang('admin.content.form.pages') ?></th>
                <td>
                    <?php echo \Sunlight\Admin\Admin::rootSelect('page_ids[]', array(
                        'multiple' => true,
                        'selected' => $box['page_ids'] !== null ? explode(',', $box['page_ids']) : array(),
                        'attrs' => 'size="10" class="inputmax"',
                        'empty_item' => _lang('global.all'),
                    )) ?>
                    <p><label><input type="checkbox"<?php echo \Sunlight\Util\Form::restoreCheckedAndName('box_edit', 'page_children', $box['page_children']) ?>> <?php echo _lang('admin.content.form.include_subpages') ?></label>
                </td>
            </tr>

            <?php echo Extend::buffer('admin.box.form') ?>

            <tr>
                <td></td>
                <td>
                    <input type="submit" class="button bigger" name="box_edit" value="<?php echo _lang('global.savechanges') ?>" accesskey="s">
                </td>
            </tr>
        </table>

        <?php echo \Sunlight\Xsrf::getInput() ?>
    </form>
<?php });
