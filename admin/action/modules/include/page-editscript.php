<?php

use Sunlight\Admin\Admin;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\GenericTemplates;
use Sunlight\Message;
use Sunlight\Page\Page;
use Sunlight\Page\PageManipulator;
use Sunlight\Plugin\TemplateService;
use Sunlight\Post\Post;
use Sunlight\Router;
use Sunlight\Search\FulltextContentBuilder;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Html;
use Sunlight\Util\Request;
use Sunlight\Util\StringHelper;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

// check access
if (!$continue) {
    $output .= Message::error(_lang('global.badinput'));
    return;
}

// prepare
if ($query['slug_abs']) {
    $editable_slug = $query['slug'];
    $base_slug = '';
} else {
    $slug_last_slash = mb_strrpos($query['slug'], '/');

    if ($slug_last_slash === false) {
        $editable_slug = $query['slug'];
        $base_slug = '';
    } else {
        $editable_slug = mb_substr($query['slug'], $slug_last_slash + 1);
        $base_slug = mb_substr($query['slug'], 0, $slug_last_slash);
    }
}

// save
if (!empty($_POST)) {
    // checks
    if (!$editscript_enable_slug && $type != Page::SEPARATOR) {
        throw new LogicException('Only separators are allowed to have disabled identifier');
    }

    // define save array
    $save_array = [
        'title' => ['type' => 'escaped_plaintext', 'length' => 255, 'nullable' => false],
        'heading' => ['type' => 'escaped_plaintext', 'length' => 255, 'nullable' => false, 'enabled' => $editscript_enable_heading],
        'slug_abs' => ['type' => 'bool', 'nullable' => false, 'enabled' => $editscript_enable_slug],
        'slug' => ['type' => 'raw', 'nullable' => false, 'enabled' => $editscript_enable_slug],
        'description' => ['type' => 'escaped_plaintext', 'length' => 255, 'nullable' => false, 'enabled' => $editscript_enable_meta],
        'node_parent' => ['type' => 'int', 'nullable' => true, 'enabled' => User::hasPrivilege('adminpages')],
        'ord' => ['type' => 'raw', 'nullable' => false, 'enabled' => User::hasPrivilege('adminpages')],
        'visible' => ['type' => 'bool', 'nullable' => false, 'enabled' => $editscript_enable_visible],
        'public' => ['type' => 'bool', 'nullable' => false, 'enabled' => $editscript_enable_access],
        'level' => ['type' => 'raw', 'nullable' => false, 'enabled' => $editscript_enable_access],
        'show_heading' => ['type' => 'bool', 'nullable' => false, 'enabled' => $editscript_enable_show_heading],
        'perex' => ['type' => 'raw', 'length' => DB::MAX_TEXT_LENGTH, 'nullable' => false, 'enabled' => $editscript_enable_perex],
        'content' => ['type' => 'raw', 'length' => DB::MAX_MEDIUMTEXT_LENGTH, 'nullable' => false, 'enabled' => $editscript_enable_content],
        'events' => ['type' => 'raw', 'length' => 255, 'nullable' => true, 'enabled' => $editscript_enable_events],
        'layout' => ['type' => 'raw', 'nullable' => true, 'enabled' => $editscript_enable_layout],
    ];
    $save_array += $custom_save_array;

    // calculate changeset
    $changeset = [];
    $refresh_tree = null;
    $refresh_slug = false;
    $refresh_levels = false;
    $refresh_layouts = false;
    $slug_abs = false;
    $actual_parent_id = $query['node_parent'];
    $search_content = new FulltextContentBuilder();

    foreach ($save_array as $item => $item_opts) {
        $skip = false;

        // load and process value
        if (!isset($item_opts['enabled']) || $item_opts['enabled']) {
            $val = Request::post($item);

            if ($val !== null) {
                $val = trim($val);
            } elseif (!$item_opts['nullable']) {
                $val = '';
            }
        } else {
            $val = $query[$item];
        }

        switch ($item_opts['type']) {
            case 'raw':
                if ($item_opts['nullable'] && $val === '') {
                    $val = null;
                }
                break;
            case 'bool':
                $val = (empty($val) ? 0 : 1);
                break;
            case 'int':
                if ($item_opts['nullable'] && $val === '') {
                    $val = null;
                } else {
                    $val = (int) $val;
                }
                break;
            case 'escaped_plaintext':
                if ($item_opts['nullable'] && $val === '') {
                    $val = null;
                } else {
                    $val = _e($val);
                }
                break;
        }

        // individual action
        switch ($item) {
            // content
            case 'content':
                $val = User::filterContent($val);
                $search_content->add($val, ['strip_tags' => true, 'unescape_html' => true, 'remove_hcm' => true]);
                break;

            // node_parent
            case 'node_parent':
                if ($val == -1) {
                    $val = null;
                }

                $skip = true;

                if ($new || $val != $query['node_parent']) {
                    $pageTreeManager = Page::getTreeManager();

                    if ($val !== null) {
                        // new parent
                        $parentData = Page::getData($val, ['id', 'type']);

                        if (
                            $parentData !== false
                            && $parentData['type'] != Page::SEPARATOR
                            && ($new || $pageTreeManager->checkParent($id, $val))
                        ) {
                            $val = $actual_parent_id = $parentData['id'];
                            $refresh_tree = true;
                            $refresh_slug = true;
                            $refresh_levels = true;
                            $refresh_layouts = true;
                            $skip = false;
                        }
                    } else {
                        // no parent
                        $actual_parent_id = null;
                        $refresh_tree = true;
                        $refresh_slug = true;
                        $refresh_levels = true;
                        $refresh_layouts = true;
                        $skip = false;
                    }
                }
                break;

            // ord
            case 'ord':
                if ($val === '') {
                    $maxOrd = DB::queryRow('SELECT MAX(ord) max_ord FROM ' . DB::table('page') . ' WHERE node_parent' . ($actual_parent_id === null ? ' IS NULL' : '=' . DB::val($actual_parent_id)));

                    if ($maxOrd && $maxOrd['max_ord'] !== null) {
                        $val = $maxOrd['max_ord'] + 1;
                    } else {
                        $val = 1;
                    }
                } else {
                    $val = (int) $val;
                }
                break;

            // title
            case 'title':
                if ($val === '') {
                    $val = _lang('global.novalue');
                }

                $title = $val;
                break;

            // perex
            case 'perex':
                $val = User::filterContent($val, true, false);
                $search_content->add($val, ['strip_tags' => true, 'unescape_html' => true]);
                break;

            // slug_abs
            case 'slug_abs':
                $slug_abs = $val;
                break;

            // slug
            case 'slug':
                if ($val === '') {
                    $val = Request::post('title');
                }

                if ($slug_abs) {
                    // absolute slug
                    $val = StringHelper::slugify($val, ['extra' => '._/', 'max_len' => 127, 'fallback' => 'page']);
                } else {
                    // segment only
                    $val = ($base_slug !== '' ? $base_slug . '/' : '') . StringHelper::slugify($val, ['extra' => '._', 'max_len' => 127, 'fallback' => 'page']);
                }

                if ($query['slug'] !== $val || $query['slug_abs'] != $slug_abs) {
                    $refresh_slug = true;
                }
                break;

            // level
            case 'level':
                if ($val === '') {
                    if (!$new && $query['level_inherit']) {
                        $skip = true;
                    } else {
                        $val = 0;
                        $changeset['level_inherit'] = 1;
                    }
                } else {
                    $val = min(User::getLevel(), max(0, (int) $val));
                    $changeset['level_inherit'] = 0;
                }

                if (!$skip && ($val != $query['level'] || $query['level_inherit'] != $changeset['level_inherit'])) {
                    $refresh_levels = true;
                }
                break;

            // var1
            case 'var1':
                if ($val === null) {
                    break;
                }

                switch ($type) {
                    // category sort type
                    case Page::CATEGORY:
                        if ($val < 1 || $val > 4) {
                            $val = 1;
                        }
                        break;
                    // images per row in galleries
                    case Page::GALLERY:
                        if ($val <= 0 && $val != -1) {
                            $val = 1;
                        }
                        break;
                    // topics per page in forums
                    case Page::FORUM:
                        if ($val <= 0) {
                            $val = 1;
                        }
                        break;
                }
                break;

            // var2
            case 'var2':
                if ($val === null) {
                    break;
                }

                switch ($type) {
                    // article, book and gallery items per page
                    case Page::CATEGORY:
                    case Page::BOOK:
                    case Page::GALLERY:
                        if ($val <= 0) {
                            $val = 1;
                        }
                        break;
                }
                break;

            // var3
            case 'var3':
                if ($val === null) {
                    break;
                }

                switch ($type) {
                    // gallery thumbnail height
                    case Page::GALLERY:
                        if ($val < 10) {
                            $val = 10;
                        } elseif ($val > 1024) {
                            $val = 1024;
                        }
                        break;
                }
                break;

            // var4
            case 'var4':
                if ($val === null) {
                    break;
                }

                switch ($type) {
                    // gallery thumbnail width
                    case Page::GALLERY:
                        if ($val <= 10) {
                            $val = 10;
                        } elseif ($val > 1024) {
                            $val = 1024;
                        }
                        break;
                }
                break;

            // delete section comments
            case 'delcomments':
                if ($type == Page::SECTION && $val == 1 && !$new) {
                    DB::delete('post', 'home=' . $id . ' AND type=' . Post::SECTION_COMMENT);
                }

                $skip = true;
                break;

            // delete book posts
            case 'delposts':
                if ($val == 1 && !$new) {
                    $ptype = null;

                    switch ($type) {
                        case Page::BOOK:
                            $ptype = Post::BOOK_ENTRY;
                            break;
                        case Page::FORUM:
                            $ptype = Post::FORUM_TOPIC;
                            break;
                    }

                    if ($ptype != null) {
                        DB::delete('post', 'home=' . $id . ' AND type=' . $ptype);
                    }
                }

                $skip = true;
                break;

            // plugin page type
            case 'type_idt':
                if ($type == Page::PLUGIN && $new) {
                    $val = $type_idt;
                } else {
                    $skip = true;
                }
                break;

            // page events
            case 'events':
                if ($val === '') {
                    $val = null;
                }
                break;

            // template layout
            case 'layout':
                if ($val === '' || !TemplateService::validateUid((string)$val, TemplateService::UID_TEMPLATE_LAYOUT)) {
                    if ($query['layout_inherit']) {
                        $skip = true;
                    } else {
                        $val = null;
                        $changeset['layout_inherit'] = 1;
                    }
                } else {
                    $changeset['layout_inherit'] = 0;
                }

                if (!$skip && ($val != $query['layout'] || $query['layout_inherit'] != $changeset['layout_inherit'])) {
                    $refresh_layouts = true;
                }
                break;
        }

        if (!$skip) {
            if (isset($item_opts['length']) && $val !== null) {
                if ($item_opts['type'] === 'escaped_plaintext') {
                    $val = Html::cut($val, $item_opts['length']);
                } else {
                    $val = StringHelper::cut($val, $item_opts['length']);
                }
            }

            $changeset[$item] = $val;
        }
    }

    $search_content = $search_content->build();

    if ($new || $search_content !== $query['search_content']) {
        $changeset['search_content'] = $search_content;
    }

    // create or save
    $action = ($new ? 'new' : 'edit');
    Extend::call('admin.page.' . $action . '.before', [
        'id' => $id,
        'page' => $new ? null : $query,
        'changeset' => &$changeset,
    ]);

    if (!$new) {
        // save
        DB::update('page', 'id=' . $id, $changeset);
    } else {
        // create
        $changeset['type'] = $type;
        $id = $query['id'] = DB::insert('page', $changeset, true);
    }

    Extend::call('admin.page.' . $action, [
        'id' => $id,
        'page' => $query,
        'changeset' => $changeset,
    ]);

    // refresh tree
    if ($refresh_tree) {
        if ($new) {
            $pageTreeManager->refresh($id);
        } else {
            $pageTreeManager->refreshOnParentUpdate($id, $actual_parent_id, $query['node_parent']);
        }
    }

    // referesh slugs
    if ($refresh_slug) {
        PageManipulator::refreshSlugs($id);
    }

    // update levels
    if ($refresh_levels) {
        PageManipulator::refreshLevels($id);
    }

    // update layouts
    if ($refresh_layouts) {
        PageManipulator::refreshLayouts($id);
    }

    $_admin->redirect(Router::admin('content-edit' . Page::TYPES[$type], ['query' => ['id' => $id, 'saved' => 1]]));

    return;
}

// parent select
if (User::hasPrivilege('adminpages')) {
    $parent_row = "<tr>\n<th>" . _lang('admin.content.form.node_parent') . '</th><td>';
    $parent_row .= Admin::pageSelect('node_parent', [
        'empty_item' => _lang('admin.content.form.node_parent.none'),
        'disabled_branches' => $new ? null : [$id],
        'maxlength' => null,
        'attrs' => 'class="inputmax"',
        'selected' => $query['node_parent'],
    ]);
    $parent_row .= "</td>\n</tr>\n";
} else {
    $parent_row = '';
}

// messages
if (isset($_GET['saved'])) {
    $output .= Message::ok(_lang('global.saved') . ' <small>(' . GenericTemplates::renderTime(time(), 'saved_msg') . ')</small>', true);
}

if (!$new && $editscript_enable_slug && DB::count('page', 'id!=' . DB::val($query['id']) . ' AND slug=' . DB::val($query['slug'])) !== 0) {
    $output .= Message::warning(_lang('admin.content.form.slug.collision'));
}

if (!$new && $id == Settings::get('index_page_id')) {
    $output .= Admin::note(_lang('admin.content.form.indexnote'));
}

$actionOptions = array_merge(
    [],
    (!$new ? ['query' => ['id' => $id]] : []),
    ($type == Page::PLUGIN && $new ? ['query' => ['idt' => $type_idt]] : [])
);

$output .= '<form class="cform" action="' . _e(Router::admin('content-edit' . Page::TYPES[$type], $actionOptions)) . '" method="post">
' . $editscript_extra . '  
    <table class="formtable edittable">
        <tbody>
            <tr class="valign-top">
                <td class="contenttable-box main-box">
                    <table>
                        <tbody>
                            <tr>
                                <th>' . _lang('admin.content.form.title') . '</th>
                                <td><input type="text" name="title" value="' . $query['title'] . '" class="inputmax" maxlength="255"></td>
                            </tr>'

                            . ($editscript_enable_slug ?
                            '<tr>
                                <th>' . _lang('admin.content.form.slug') . '</th>
                                <td><input type="text" name="slug" value="' . $editable_slug . '" maxlength="1024" class="inputmax"></td>
                            </tr>' : '')

                            . ($editscript_enable_slug ?
                            '<tr>
                                <th></th>
                                <td><label><input type="checkbox" name="slug_abs"' . Form::activateCheckbox($query['slug_abs']) . '> ' . _lang('admin.content.form.slug_abs.label') . '</label></td>
                            </tr>' : '')

                            . ($editscript_enable_meta ?
                            '<tr>
                                <th>' . _lang('admin.content.form.description') . '</th>
                                <td><input type="text" name="description" value="' . $query['description'] . '" maxlength="255" class="inputmax"></td>
                            </tr>' : '')

                            . $parent_row

                            . ($editscript_enable_perex ?
                            '<tr class="valign-top">
                                <th>' . _lang('admin.content.form.perex') . '</th>
                                <td>' . Admin::editor('page-perex', 'perex', _e($query['perex']), ['mode' => 'lite', 'rows' => 2, 'class' => 'arealine']) . '</td>
                            </tr>' : '')

                            . ($editscript_enable_heading ?
                            '<tr>
                                <th>' . _lang('admin.content.form.heading') . '</th>
                                <td><input type="text" name="heading" value="' . $query['heading'] . '" class="inputmax" maxlength="255"></td>
                            </tr>' : '')

                            . ($editscript_enable_content ?
                            '<tr class="valign-top">
                                <th>' . _lang('admin.content.form.content'). '</th>
                                <td>' . Admin::editor('page-content', 'content', _e($query['content'])) . '</td>
                            </tr>' : '')

                            . $editscript_extra_row

                            . ($editscript_enable_events ?
                            '<tr>
                                <th>' . _lang('admin.content.form.events') . '</th>
                                <td><input type="text" name="events" value="' . (isset($query['events']) ? _e($query['events']) : '') . '" class="inputmax" maxlength="255"></td>
                            </tr>' : '')

                            . $editscript_extra_row2

                        . '</tbody>
                       <tfoot>
                        <tr><td></td><td></td></tr>
                        <tr>
                            <td></td>
                            <td>
                                <input type="submit" class="button bigger" value="' . ($new ? _lang('global.create') : _lang('global.savechanges')) . '" accesskey="s">

                                ' . (!$new
                                    ? ' <a class="button bigger" href="' . _e(Router::page($query['id'], $query['slug'])) . '" target="_blank">'
                                        . '<img src="' . _e(Router::path('admin/public/images/icons/show.png')) . '" alt="open" class="icon">'
                                        . _lang('global.open')
                                        . '</a>'
                                    : '') . '
                            </td>
                        </tr>
                       </tfoot>     
                    </table>                
                </td> 
                <td class="contenttable-box">

                    <div id="settingseditform">'

                    . $editscript_setting_extra
                    . '<fieldset>
                        <legend>' . _lang('admin.content.form.settings') . '</legend>
                        <table>
                        <tbody>
                            <tr>
                                <td colspan="2">
                                    <label>' . _lang('admin.content.form.ord') . '</label>
                                    <input type="number" name="ord"' . Form::disableInputUnless(User::hasPrivilege('adminpages')) . ' value="' . $query['ord'] . '" class="inputmax">
                                </td>
                            </tr>'
                            . ((!empty($custom_settings) || $editscript_enable_show_heading || $editscript_enable_visible) ?
                                ($editscript_enable_visible ? '<tr><td colspan="2"><label><input type="checkbox" name="visible" value="1"' . Form::activateCheckbox($query['visible']) . '> ' . _lang('admin.content.form.visible') . '</label></td></tr>' : '')
                                . ($editscript_enable_show_heading ? '<tr><td colspan="2"><label><input type="checkbox" name="show_heading" value="1"' . Form::activateCheckbox($query['show_heading']) . '> ' . _lang('admin.content.form.show_heading') . '</label></td></tr>' : '')
                                . $custom_settings : '')

                        . '</tbody>
                        </table>
                    </fieldset>'

                    . ($editscript_enable_layout ?
                    '<fieldset>
                        <legend>' . _lang('admin.content.form.layout') . '</legend>'
                        . Admin::templateLayoutSelect(
                            'layout',
                            $query['layout_inherit'] ? null : $query['layout'],
                            $query['layout_inherit']
                                ? _lang('admin.content.form.layout.inherited', ['%layout%' => TemplateService::getComponentLabelByUid($query['layout'], TemplateService::UID_TEMPLATE_LAYOUT)])
                                : _lang('admin.content.form.layout.inherit'),
                            null,
                            'inputmax'
                        )
                    . '</fieldset>' : '')

                    . ($editscript_enable_access ?
                    '<fieldset>
                        <legend>' . _lang('global.access') . '</legend>
                        <table>
                        <tbody>
                            <tr>
                                <td>
                                    <input type="number" min="0" max="' . User::MAX_LEVEL . '" name="level" value="' . ($query['level_inherit'] ? '' : $query['level']) . '" class="inputmax" maxlength="5">
                                </td>
                                <td>'
                                    . _lang('admin.content.form.level')
                                    . ($query['level_inherit'] ? '<br><small>(' . _lang('admin.content.form.inherited') . ': ' . $query['level'] . ')</small>' : '')
                                . '</td>
                            </tr>
                            <tr>
                                <td colspan="2"><input type="checkbox" name="public" value="1"' . Form::activateCheckbox($query['public']) . '> ' . _lang('admin.content.form.public') . '</td>
                            </tr>
                        </tbody>
                        </table>
                    </fieldset>' : '')
                . $editscript_setting_extra2
                . '</div>
                </td>
            </tr>
        </tbody>
    </table>
    ' . Xsrf::getInput() . '
</form>';
