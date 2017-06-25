<?php

use Sunlight\Extend;

if (!defined('_root')) {
    exit;
}

/* ---  priprava  --- */

$message = "";

// vypis stranek
$ppages = Sunlight\Page\PageManager::getPluginTypes();
$type_array = Sunlight\Page\PageManager::getTypes();

if (_priv_adminsection || _priv_admincategory || _priv_adminbook || _priv_adminseparator || _priv_admingallery || _priv_adminlink || _priv_admingroup || _priv_adminforum || _priv_adminpluginpage) {

    // akce
    if (isset($_POST['ac'])) {
        $ac = (int) _post('ac');

        switch ($ac) {
            // vytvoreni stranky
            case 'new':
                $is_ppage = false;
                if (is_numeric(_post('type'))) {
                    $type = (int) _post('type');
                } else {
                    $type = _page_plugin;
                    $type_idt = strval(_post('type'));
                    if (!isset($ppages[$type_idt])) {
                        break;
                    }
                    $is_ppage = true;
                }
                if (isset($type_array[$type])) {
                    if (_userHasPriv('admin' . $type_array[$type])) {
                        $admin_redirect_to = 'index.php?p=content-edit' . $type_array[$type] . ($is_ppage ? '&idt=' . rawurlencode($type_idt) : '');

                        return;
                    }
                }
                break;
        }
    }

    // horni panel

    // seznam typu stranek
    $create_list = "";
    if (_priv_adminroot) {
        foreach ($type_array as $type => $name) {
            if ($type != _page_plugin && _userHasPriv('admin' . $name)) {
                $create_list .= "<option value='" . $type . "'>" . _lang('page.type.' . $name) . "</option>\n";
            }
        }

        // seznam pluginovych typu stranek
        if (_priv_adminpluginpage && !empty($ppages)) {
            $create_list .= "<option value='' disabled>---</option>\n";
            foreach($ppages as $ppage_idt => $ppage_label) {
                $create_list .= "<option value='" . $ppage_idt . "'>" . $ppage_label . "</option>\n";
            }
        }
    }

    $rootitems = '
    <td class="contenttable-box" style="' . ((_priv_adminart || _priv_adminconfirm || _priv_admincategory || _priv_adminpoll || _priv_adminsbox || _priv_adminbox) ? 'width: 75%; ' : 'border-right: none;') . 'padding-bottom: 0px;">

    ' . (_priv_adminroot ? '
    <form action="index.php?p=content" method="post" class="inline">
    <input type="hidden" name="ac" value="new">
    <img src="images/icons/new.png" alt="new" class="icon">
    <select name="type">
    ' . $create_list . '
    </select>
    <input class="button" type="submit" value="' . _lang('global.create') . '">
    ' . _xsrfProtect() . '</form>

    <span class="inline-separator"></span>
    ' : '' ) . '

    ' . (_priv_adminroot ? '
    <a class="button" href="index.php?p=content-setindex"><img src="images/icons/home.png" alt="act" class="icon">' . _lang('admin.content.setindex') . '</a>

    <span class="inline-separator"></span>

    <a class="button" href="index.php?p=content-sort"><img src="images/icons/action.png" alt="move" class="icon">' . _lang('admin.content.sort') . '</a>
    <a class="button" href="index.php?p=content-titles"><img src="images/icons/action.png" alt="titles" class="icon">' . _lang('admin.content.titles') . '</a>
    <a class="button" href="index.php?p=content-redir"><img src="images/icons/action.png" alt="redir" class="icon">' . _lang('admin.content.redir') . '</a>

    <span class="inline-separator"></span>
    ' : '' ) . '

    <a class="button" href="index.php?p=content&amp;list_mode=tree"' . (Sunlight\Admin\PageLister::MODE_FULL_TREE == Sunlight\Admin\PageLister::getConfig('mode') ? ' class="active-link"' : '') . '><img src="images/icons/tree.png" alt="move" class="icon">' . _lang('admin.content.mode.tree') . '</a>
    <a class="button" href="index.php?p=content&amp;list_mode=single"' . (Sunlight\Admin\PageLister::MODE_SINGLE_LEVEL == Sunlight\Admin\PageLister::getConfig('mode') ? ' class="active-link"' : '') . '><img src="images/icons/list.png" alt="move" class="icon">' . _lang('admin.content.mode.single') . '</a>

    <div class="hr"><hr></div>

    ';

    // tabulka polozek
    if (
        _priv_adminroot
        && Sunlight\Admin\PageLister::getConfig('mode') == Sunlight\Admin\PageLister::MODE_SINGLE_LEVEL
    ) {
        $sortable = true;
    } else {
        $sortable = false;
    }

    $rootitems .= Sunlight\Admin\PageLister::render(array(
        'type' => true,
        'flags' => true,
        'sortable' => $sortable,
    ));

    $rootitems .= '
</td>
';
} else {
    $rootitems = '';
}

// nabidka modulu
$content_modules = array(
    'layout' => array(
        'modules' => array(
            'boxes' => array(
                'url' => 'index.php?p=content-boxes',
                'icon' => 'images/icons/big-layout.png',
                'access' => _priv_adminbox,
            ),
        ),
    ),

    'articles' => array(
        'modules' => array(
            'newart' => array(
                'url' => 'index.php?p=content-articles-edit',
                'icon' => 'images/icons/big-new.png',
                'access' => _priv_adminart,
            ),
            'confirm' => array(
                'url' => 'index.php?p=content-confirm',
                'icon' => 'images/icons/big-check.png',
                'access' => _priv_adminconfirm,
            ),
            'movearts' => array(
                'url' => 'index.php?p=content-movearts',
                'icon' => 'images/icons/big-move.png',
                'access' => _priv_admincategory,
            ),
            'artfilter' => array(
                'url' => 'index.php?p=content-artfilter',
                'icon' => 'images/icons/big-filter.png',
                'access' => _priv_admincategory,
            ),
        ),
    ),

    'widgets' => array(
        'modules' => array(
            'polls' => array(
                'url' => 'index.php?p=content-polls',
                'icon' => 'images/icons/big-bars.png',
                'access' => _priv_adminpoll,
            ),
            'sboxes' => array(
                'url' => 'index.php?p=content-sboxes',
                'icon' => 'images/icons/big-bubbles.png',
                'access' => _priv_adminsbox,
            ),
        ),
    ),
);

Extend::call('admin.content.modules', array('modules' => &$content_modules));

$content_modules_str = '';
foreach ($content_modules as $category_alias => $category_data) {
    $buttons_str = '';
    foreach ($category_data['modules'] as $module_alias => $module_options) {
        if ($module_options['access']) {
            $module_label = isset($module_options['label']) ? $module_options['label'] : _lang('admin.content.' . $module_alias);
            $buttons_str .= '<a class="button block" href="' . _e($module_options['url']) . '"><img class="icon" alt="' . _e($module_label) . '" src="' . _e($module_options['icon']) . '">' . $module_label . "</a>\n";
        }
    }

    if ($buttons_str !== '') {
        $content_modules_str .= '<div class="content-' . $category_alias . '">
<h2>' . (isset($category_data['label']) ? $category_data['label'] : _lang('admin.content.' . $category_alias)) . '</h2>
' . $buttons_str;
    }
}

/* ---  vystup  --- */

// zprava
if (isset($_GET['done'])) {
    $message = _msg(_msg_ok, _lang('global.done'));
}

$output .= $message . '
<table id="contenttable">
<tr class="valign-top">
  ' . $rootitems . '
  ' . ($content_modules_str !== '' ? "<td class=\"contenttable-box\" id=\"content-modules\">{$content_modules_str}</td>" : '') . '
</tr>
</table>
';
