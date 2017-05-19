<?php

if (!defined('_root')) {
    exit;
}

/* --- kontrola pristupu --- */

if (!$continue) {
    $output .= _msg(_msg_err, $_lang['global.badinput']);
    return;
}

/* --- priprava --- */

if ($query['slug_abs']) {
    $editable_slug = $query['slug'];
    $base_slug = '';
} else {
    $slug_last_slash = mb_strrpos($query['slug'], '/');
    if (false === $slug_last_slash) {
        $editable_slug = $query['slug'];
        $base_slug = '';
    } else {
        $editable_slug = mb_substr($query['slug'], $slug_last_slash + 1);
        $base_slug = mb_substr($query['slug'], 0, $slug_last_slash);
    }

}

/* ---  ulozeni  --- */

if (!empty($_POST)) {

    // kontroly
    if (!$editscript_enable_slug && _page_separator != $type) {
        throw new LogicException('Only separators are allowed to have disabled identifier');
    }

    // pole vstupu array(nazev => typ)
    $save_array = array(
        'title' => array('type' => 'html', 'length' => 255, 'nullable' => false),
        'heading' => array('type' => 'html', 'length' => 255, 'nullable' => false, 'enabled' => $editscript_enable_heading),
        'slug_abs' => array('type' => 'bool', 'nullable' => false, 'enabled' => $editscript_enable_slug),
        'slug' => array('type' => 'raw', 'nullable' => false, 'enabled' => $editscript_enable_slug),
        'keywords' => array('type' => 'html', 'nullable' => false, 'enabled' => $editscript_enable_meta),
        'description' => array('type' => 'html', 'nullable' => false, 'enabled' => $editscript_enable_meta),
        'node_parent' => array('type' => 'int', 'nullable' => true, 'enabled' => _priv_adminroot),
        'ord' => array('type' => 'raw', 'nullable' => false, 'enabled' => _priv_adminroot),
        'visible' => array('type' => 'bool', 'nullable' => false, 'enabled' => $editscript_enable_visible),
        'public' => array('type' => 'bool', 'nullable' => false, 'enabled' => $editscript_enable_access),
        'level' => array('type' => 'raw', 'nullable' => false, 'enabled' => $editscript_enable_access),
        'show_heading' => array('type' => 'bool', 'nullable' => false, 'enabled' => $editscript_enable_show_heading),
        'perex' => array('type' => 'raw', 'nullable' => false, 'enabled' => $editscript_enable_perex),
        'content' => array('type' => 'raw', 'nullable' => false, 'enabled' => $editscript_enable_content),
        'events' => array('type' => 'raw', 'nullable' => true, 'enabled' => $editscript_enable_events),
        'layout' => array('type' => 'raw', 'nullable' => true, 'enabled' => $editscript_enable_layout),
    );
    $save_array += $custom_save_array;

    // ulozeni
    $changeset = array();
    $refresh_tree = null;
    $refresh_slug = false;
    $refresh_levels = false;
    $refresh_layouts = false;
    $slug_abs = false;
    $actual_parent_id = $query['node_parent'];
    foreach ($save_array as $item => $item_opts) {

        $skip = false;

        // nacteni a zpracovani hodnoty
        if (!isset($item_opts['enabled']) || $item_opts['enabled']) {
            $val = _post($item);
            if (null !== $val) {
                $val = trim($val);
            } elseif (!$item_opts['nullable']) {
                $val = '';
            }
        } else {
            $val = $query[$item];
        }
        switch ($item_opts['type']) {
            case 'raw':
                if ($item_opts['nullable'] && '' === $val) {
                    $val = null;
                }
                break;
            case 'bool':
                $val = (empty($val) ? 0 : 1);
                break;
            case 'int':
                if ($item_opts['nullable'] && '' === $val) {
                    $val = null;
                } else {
                    $val = (int) $val;
                }
                break;
            case 'html':
                if ($item_opts['nullable'] && '' === $val) {
                    $val = null;
                } else {
                    $val = _e($val);
                }
                break;
        }

        // individualni akce
        switch ($item) {
            // content
            case 'content':
                $val = _filterUserContent($val);
                break;

            // node_parent
            case 'node_parent':
                if ($val == -1) {
                    $val = null;
                }

                $skip = true;
                if ($new || $val != $query['node_parent']) {
                    $pageTreeManager = Sunlight\Page\PageManager::getTreeManager();

                    if (null !== $val) {
                        // novy rodic
                        $parentData = Sunlight\Page\PageManager::getData($val, array('id', 'type'));
                        if (
                            false !== $parentData
                            && _page_separator != $parentData['type']
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
                        // zadny rodic
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
                if ('' === $val) {
                    $maxOrd = DB::queryRow('SELECT MAX(ord) max_ord FROM ' . _root_table . ' WHERE node_parent' . (null === $actual_parent_id ? ' IS NULL' : '=' . DB::val($actual_parent_id)));
                    if ($maxOrd && null !== $maxOrd['max_ord']) {
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
                if ('' === $val) {
                    $val = $_lang['global.novalue'];
                }
                $title = $val;
                break;

            // slug_abs
            case 'slug_abs':
                $slug_abs = $val;
                break;

            // slug
            case 'slug':
                if ($val === '') {
                    $val = $title;
                }
                if ($slug_abs) {
                    // absolutni slug
                    $val = _slugify($val, true, array('/' => 0));
                } else {
                    // pouze segment
                    $val = ('' !== $base_slug ? $base_slug . '/' : '') . _slugify($val);
                }
                if ($query['slug'] !== $val || $query['slug_abs'] != $slug_abs) {
                    $refresh_slug = true;
                }
                break;

            // level
            case 'level':
                if ('' === $val) {
                    if (!$new && $query['level_inherit']) {
                        $skip = true;
                    } else {
                        $val = 0;
                        $changeset['level_inherit'] = 1;
                    }
                } else {
                    $val = min(_priv_level, max(0, (int) $val));
                    $changeset['level_inherit'] = 0;
                }
                if (!$skip && ($val != $query['level'] || $query['level_inherit'] != $changeset['level_inherit'])) {
                    $refresh_levels = true;
                }
                break;

            // var1
            case 'var1':
                if (null === $val) {
                    break;
                }

                switch ($type) {
                    // zpusob razeni v kategoriich
                    case _page_category:
                        if ($val < 1 || $val > 4) {
                            $val = 1;
                        }
                        break;
                    // obrazku na radek v galerii
                    case _page_gallery:
                    if ($val <= 0 && $val != -1) {
                        $val = 1;
                    }
                        break;
                    // temat na stranu ve forech
                    case _page_forum:
                        if ($val <= 0) {
                            $val = 1;
                        }
                        break;
                }
                break;

            // var2
            case 'var2':
                if (null === $val) {
                    break;
                }

                switch ($type) {
                    // clanku na stranu v kategoriich, prispevku na stranu v knihach, obrazku na stranu v galeriich
                    case _page_category:
                    case _page_book:
                    case _page_gallery:
                        if ($val <= 0) {
                            $val = 1;
                        }
                        break;
                }
                break;

            // var3
            case 'var3':
                if (null === $val) {
                    break;
                }
                
                switch ($type) {
                    // vyska nahledu v galeriich
                    case _page_gallery:
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
                if (null === $val) {
                    break;
                }

                switch ($type) {
                    // sirka nahledu v galeriich
                    case _page_gallery:
                        if ($val <= 10) {
                            $val = 10;
                        }
                        elseif ($val > 1024) {
                            $val = 1024;
                        }
                        break;
                }
                break;

            // smazani komentaru v sekcich
            case 'delcomments':
                if ($type == _page_section && $val == 1 && !$new) {
                    DB::delete(_posts_table, 'home=' . $id . ' AND type=' . _post_section_comment);
                }
                $skip = true;
                break;

            // smazani prispevku v knihach
            case 'delposts':
                if ($val == 1 && !$new) {
                    $ptype = null;
                    switch ($type) {
                        case _page_book:
                            $ptype = _post_book_entry;
                            break;
                        case _page_forum:
                            $ptype = _post_forum_topic;
                            break;
                    }
                    if ($ptype != null) {
                        DB::delete(_posts_table, 'home=' . $id . ' AND type=' . $ptype);
                    }
                }
                $skip = true;
                break;

            // typ plugin stranky
            case 'type_idt':
                if ($type == _page_plugin && $new) {
                    $val = $type_idt;
                } else {
                    $skip = true;
                }
                break;

            // udalosti stranky
            case 'events':
                if ('' === $val) {
                    $val = null;
                }
                break;

            // layout
            case 'layout':
                if ('' === $val || !Sunlight\Plugin\TemplateHelper::validateLayoutUid($val)) {
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
            $changeset[$item] = $val;
        }

    }

    // vlozeni / ulozeni
    $action = ($new ? 'new' : 'edit');
    Sunlight\Extend::call('admin.root.' . $action . '.pre', array(
        'id' => $id,
        'page' => $new ? null : $query,
        'changeset' => &$changeset,
    ));

    if (!$new) {
        // ulozeni
        DB::update(_root_table, 'id=' . $id, $changeset);

    } else {
        // vytvoreni
        $changeset['type'] = $type;
        $id = $query['id'] = DB::insert(_root_table, $changeset, true);
    }

    Sunlight\Extend::call('admin.root.' . $action, array(
        'id' => $id,
        'page' => $query,
        'changeset' => $changeset,
    ));

    // obnovit urovne stromu
    if ($refresh_tree) {
        if ($new) {
            $pageTreeManager->refresh($id);
        } else {
            $pageTreeManager->refreshOnParentUpdate($id, $actual_parent_id, $query['node_parent']);
        }
    }

    // pregenerovat identifikatory
    if ($refresh_slug) {
        Sunlight\Page\PageManipulator::refreshSlugs($id);
    }

    // aktualizovat opravneni
    if ($refresh_levels) {
        Sunlight\Page\PageManipulator::refreshLevels($id);
    }

    // aktualizovat layouty
    if ($refresh_layouts) {
        Sunlight\Page\PageManipulator::refreshLayouts($id);
    }

    $admin_redirect_to = 'index.php?p=content-edit' . $type_array[$type] . '&id=' . $id . '&saved';

    return;
}

/* ---  vystup  --- */

// radky na celou sirku formulare
if (
    $editscript_enable_slug
    || $editscript_enable_meta
    || $editscript_enable_events
) {
    $colspan = " colspan='3'";
} else {
    $colspan = '';
}

// vyber rodice
if (_priv_adminroot) {
    $parent_row = "<tr>\n<th>" . $_lang['admin.content.form.node_parent'] . "</th><td" . $colspan . ">";
    $parent_row .= _adminRootSelect('node_parent', array(
        'empty_item' => $_lang['admin.content.form.node_parent.none'],
        'disabled_branches' => $new ? null : array($id),
        'maxlength' => null,
        'attrs' => 'class="inputmax"',
        'selected' => $query['node_parent'],
    ));
    $parent_row .=  "</td>\n</tr>\n";
} else {
    $parent_row = '';
}

// stylove oddeleni individualniho nastaveni
if ($custom_settings != "") {
    $custom_settings = "<span class='customsettings'>" . $custom_settings . "</span>";
}

// editacni pole
$editor = Sunlight\Extend::buffer('admin.root.editor');

if ('' === $editor) {
    // vychozi implementace 
    $editor = "<textarea name='content' rows='25' cols='94' class='areabig editor'>" . _e($query['content']) . "</textarea>";
}

// zpravy
if (isset($_GET['saved'])) {
    $output .= _msg(_msg_ok, $_lang['global.saved'] . " <small>(" . _formatTime(time()) . ")</small>");
}
if (!$new && $editscript_enable_slug && DB::count(_root_table, 'id!=' . DB::val($query['id']) . ' AND slug=' . DB::val($query['slug'])) !== 0) {
    $output .= _msg(_msg_warn, $_lang['admin.content.form.slug.collision']);
}
if (!$new && $id == _index_page_id) {
    $output .= _adminNote($_lang['admin.content.form.indexnote']);
}

// formular
$output .= "<form class='cform' action='index.php?p=content-edit" . $type_array[$type] . (!$new ? "&amp;id=" . $id : '') . (($type == _page_plugin && $new) ? '&amp;idt=' . $type_idt : '') . "' method='post'>

" . $editscript_extra . "

<table class='formtable'>

<tr>
<th>" . $_lang['admin.content.form.title'] . "</th>
<td><input type='text' name='title' value='" . $query['title'] . "' class='inputmax' maxlength='255'></td>

" . ($editscript_enable_slug ? "<th>" . $_lang['admin.content.form.slug'] . "</th>
<td><input type='text' name='slug' value='" . $editable_slug . "' maxlength='1024' class='inputmax'></td>" : '') . "
</tr>

<tr>
<th>" . $_lang['admin.content.form.ord'] . "</th>
<td><input type='number' name='ord'" . _inputDisableUnless(_priv_adminroot) ." value='" . $query['ord'] . "' class='inputmax'></td>

" . ($editscript_enable_slug ? "<td></td><td><label><input type='checkbox' name='slug_abs'" . _checkboxActivate($query['slug_abs']) . "> " . $_lang['admin.content.form.slug_abs.label'] . "</label></td>" : '') . "
</tr>

" . ($editscript_enable_meta ? "
<tr>
<th>" . $_lang['admin.content.form.description'] . "</th>
<td><input type='text' name='description' value='" . $query['description'] . "' maxlength='255' class='inputmax'></td>

<th>" . $_lang['admin.content.form.keywords'] . "</th>
<td><input type='text' name='keywords' value='" . $query['keywords'] . "' maxlength='255' class='inputmax'></td>
</tr>
" : '') . "

" . $parent_row . "

" . ($editscript_enable_layout ? "
<tr>
<th>" . $_lang['admin.content.form.layout'] . "</th>
<td " . $colspan . ">" . _adminTemplateLayoutSelect(
    'layout',
    $query['layout_inherit'] ? null : $query['layout'],
    $query['layout_inherit']
        ? sprintf($_lang['admin.content.form.layout.inherited'], Sunlight\Plugin\TemplateHelper::getLayoutUidLabel($query['layout']))
        : $_lang['admin.content.form.layout.inherit'],
    null,
    'inputmax'
) . "</td>
</tr>
" : '') . "

" . ($editscript_enable_perex ? "
<tr class='valign-top'>
<th>" . $_lang['admin.content.form.perex'] . "</th>
<td" . $colspan . "><textarea name='perex' rows='2' cols='94' class='arealine editor' data-editor-mode='lite'>" . _e($query['perex']) . "</textarea></td>
</tr>
" : '') . "

" . ($editscript_enable_heading ? "
<tr>
<th>" . $_lang['admin.content.form.heading'] . "</th>
<td" . $colspan . "><input type='text' name='heading' value='" . $query['heading'] . "' class='inputmax' maxlength='255'></td>
</tr>
" : '') . "

" . ($editscript_enable_content ? "
<tr class='valign-top'>
<th>" . $_lang['admin.content.form.content'] . (!$new ? " <a href='" . _linkRoot($query['id'], $query['slug']) . "' target='_blank'><img src='images/icons/loupe.png' alt='prev'></a>" : '') . "</th>
<td" . $colspan . ">
" . $editor . "
</td>
</tr>
" : '') . "

" . $editscript_extra_row . "

" . ($editscript_enable_events ? "<tr>
<th>" . $_lang['admin.content.form.events'] . "</th>
<td" . $colspan . "><input type='text' name='events' value='" . (isset($query['events']) ? _e($query['events']) : '') . "' class='inputmax' maxlength='255'></td>
</tr>
" : '') . "

" . $editscript_extra_row2 . "

" . ((!empty($custom_settings) || $editscript_enable_show_heading || $editscript_enable_visible) ? "
<tr>
<th>" . $_lang['admin.content.form.settings'] . "</th>
<td" . $colspan . ">
" . ($editscript_enable_visible ? "<label><input type='checkbox' name='visible' value='1'" . _checkboxActivate($query['visible']) . "> " . $_lang['admin.content.form.visible'] . "</label> " : '') . "
" . ($editscript_enable_show_heading ? "<label><input type='checkbox' name='show_heading' value='1'" . _checkboxActivate($query['show_heading']) . "> " . $_lang['admin.content.form.show_heading'] . "</label> " : '') . "
" . $custom_settings . "
</td>
</tr>
" : '') . "

" . ($editscript_enable_access ? "
<tr>
<th>" . $_lang['global.access'] . "</th>
<td" . $colspan . ">
<label><input type='checkbox' name='public' value='1'" . _checkboxActivate($query['public']) . "> " . $_lang['admin.content.form.public'] . "</label> 
<input type='number' min='0' max='" . _priv_max_level . "' name='level' value='" . ($query['level_inherit'] ? '' : $query['level']) . "' class='inputsmaller' maxlength='5'> " . $_lang['admin.content.form.level'] . "
" . ($query['level_inherit'] ? '<small>(' . $_lang['admin.content.form.inherited'] . ': ' . $query['level'] . ')</small>' : '') . "
</td>
</tr>
" : '') . "

<tr><td></td><td" . $colspan . "><br>
<input type='submit' value='" . ($new ? $_lang['global.create'] : $_lang['global.savechanges']) . "'>" . (!$new ? " <small>" . $_lang['admin.content.form.thisid'] . " " . $query['id'] . "</small>" : '') . "
</td></tr>

</table>
" . _xsrfProtect() . "</form>
";
