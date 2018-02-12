<?php

use Sunlight\Database\Database as DB;
use Sunlight\Message;
use Sunlight\Plugin\TemplatePlugin;
use Sunlight\Plugin\TemplateService;

if (!defined('_root')) {
    exit;
}

$message = null;

// process action
if (isset($_POST['action'])) {
    list($action, $param) = explode(':', _post('action', '')) + array(1 => null);

    switch ($action) {
        case 'save_ord':
            $changeset = array();

            foreach ($_POST['ord'] as $boxId => $boxOrd) {
                $changeset[$boxId] = array('ord' => (int) $boxOrd);
            }

            DB::updateSetMulti(_boxes_table, 'id', $changeset);
            $message = Message::ok(_lang('admin.content.form.ord.saved'));
            break;

        case 'delete':
            DB::delete(_boxes_table, 'id=' . DB::val($param));
            $message = Message::ok(_lang('global.deleted'));
            break;
    }
}

// fetch boxes
$boxes = array();
$unassigned_boxes = array();
$query = DB::query('SELECT id, ord, title, visible, public, level, template, layout, slot, page_ids, page_children, class FROM ' . _boxes_table . ' ORDER BY template ASC, layout ASC, ord ASC');

while ($box = DB::row($query)) {
    if (
        TemplateService::templateExists($box['template'])
        && TemplateService::getTemplate($box['template'])->hasLayout($box['layout'])
    ) {
        $boxes[$box['template']][$box['layout']][] = $box;
    } else {
        $unassigned_boxes[] = $box;
    }
}

// message
$output .= $message;

// main form
$output .= '<form method="post">';
$output .= _buffer(function () { ?>
    <form method="post">
    <p>
        <a class="button" href="index.php?p=content-boxes-edit"><img class="icon" src="images/icons/new.png" alt="new"><?php echo _lang('admin.content.boxes.new') ?></a>
    </p>
<?php });

// template sections
foreach ($boxes as $template_idt => $template_boxes) {
    $output .= _buffer(
        function (TemplatePlugin $template, array $boxes) { ?>
    <table class="box-list list list-hover list-max">
        <caption>
            <h2><?php echo _lang('admin.content.form.template') ?>: <?php echo _e($template->getOption('name')) ?></h2>
        </caption>
        <thead>
        <tr>
            <th class="box-order-cell"><?php echo _lang('admin.content.form.ord') ?></th>
            <th class="box-slot-cell"><?php echo _lang('admin.content.boxes.slot') ?></th>
            <th class="box-title-cell"><?php echo _lang('admin.content.form.title') ?></th>
            <th class="box-settings-cell"><?php echo _lang('admin.content.form.settings') ?></th>
            <th class="box-action-cell"><?php echo _lang('global.action') ?></th>
        </tr>
        </thead>
        <?php foreach ($boxes as $layout => $layout_boxes): ?>
            <tbody class="sortable" data-input-selector=".box-order-input" data-handle-selector="td.box-sortable-cell, .sortable-handle">
            <?php foreach ($layout_boxes as $box): ?>
                <tr>
                    <td class="box-order-cell"><span class="sortable-handle"></span><input class="inputmini box-order-input" type="number" name="ord[<?php echo _e($box['id']) ?>]" value="<?php echo _e($box['ord']) ?>"></td>
                    <td class="box-slot-cell box-sortable-cell"><?php echo _e(sprintf('%s - %s', $template->getLayoutLabel($box['layout']), $template->getSlotLabel($box['layout'], $box['slot']))) ?></td>
                    <td class="box-title-cell box-sortable-cell"><?php echo $box['title'] ?></td>
                    <td class="box-settings-cell">
                        <?php if ($box['level'] > 0): $iconTitle = _lang('admin.content.form.level') . ' ' . _e($box['level']) . '+'; ?><img src="images/icons/lock3.png" class="icon" alt="<?php echo $iconTitle ?>" title="<?php echo $iconTitle ?>"><?php endif ?>
                        <?php if ($box['page_ids'] !== null): $iconTitle = _lang('admin.content.boxes.page_ids.icon'); ?><img src="images/icons/tree.png" class="icon" alt="<?php echo $iconTitle ?>" title="<?php echo $iconTitle ?>"><?php endif ?>
                    </td>
                    <td class="box-action-cell">
                        <a class="button" href="index.php?p=content-boxes-edit&amp;id=<?php echo _e($box['id']) ?>"><img src="images/icons/edit.png" alt="edit" class="icon"><?php echo _lang('global.edit') ?></a>
                        <button onclick="return Sunlight.confirm()" name="action" value="delete:<?php echo _e($box['id']) ?>" class="button"><img src="images/icons/delete.png" alt="delete" class="icon"><?php echo _lang('global.delete') ?></button>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        <?php endforeach ?>
        <tfoot>
            <tr>
                <td colspan="5">
                    <button name="action" value="save_ord" accesskey="s"><?php echo _lang('global.savechanges') ?></button>
                    <a class="button right big" href="index.php?p=content-boxes-edit&amp;template=<?php echo _e(rawurlencode($template->getId())) ?>"><img class="icon" src="images/icons/new.png" alt="new"><?php echo _lang('admin.content.boxes.new.for_template') ?></a>
                </td>
            </tr>
        </tfoot>
    </table>
<?php },
        array(TemplateService::getTemplate($template_idt), $template_boxes)
    );
}

// unassigned boxes
if (!empty($unassigned_boxes)) $output .= _buffer(function () use ($unassigned_boxes) { ?>
    <table class="list list-hover">
        <caption>
            <h2><?php echo _lang('admin.content.boxes.unassigned') ?></h2>
            <?php echo _adminNote(_lang('admin.content.boxes.unassigned.note')) ?>
        </caption>
        <thead>
        <tr>
            <th><?php echo _lang('admin.content.boxes.original_template') ?></th>
            <th><?php echo _lang('admin.content.boxes.slot') ?></th>
            <th><?php echo _lang('admin.content.form.title') ?></th>
            <th><?php echo _lang('global.action') ?></th>
        </tr>
        </thead>
        <tbody>
            <?php foreach ($unassigned_boxes as $box): ?>
                <tr>
                    <td><?php echo _e($box['template']) ?></td>
                    <td><?php echo _e(sprintf('%s - %s', $box['layout'], $box['slot'])) ?></td>
                    <td><?php echo $box['title'] ?></td>
                    <td>
                        <a class="button" href="index.php?p=content-boxes-edit&amp;id=<?php echo _e($box['id']) ?>"><img src="images/icons/edit.png" alt="edit" class="icon"><?php echo _lang('global.edit') ?></a>
                        <button onclick="return Sunlight.confirm()" name="action" value="delete:<?php echo _e($box['id']) ?>" class="button"><img src="images/icons/delete.png" alt="delete" class="icon"><?php echo _lang('global.delete') ?></button>
                    </td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
<?php });

// main form end
$output .= _xsrfProtect() . "</form>\n";
