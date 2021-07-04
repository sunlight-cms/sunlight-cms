<?php

use Sunlight\Admin\PageLister;
use Sunlight\Extend;
use Sunlight\Message;
use Sunlight\Page\PageManager;
use Sunlight\User;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('_root') or exit;

/* ---  priprava  --- */

$message = "";

// vypis stranek
$plugin_types = PageManager::getPluginTypes();
$type_array = PageManager::getTypes();

if (
    User::hasPrivilege('adminsection')
    || User::hasPrivilege('admincategory')
    || User::hasPrivilege('adminbook')
    || User::hasPrivilege('adminseparator')
    || User::hasPrivilege('admingallery')
    || User::hasPrivilege('adminlink')
    || User::hasPrivilege('admingroup')
    || User::hasPrivilege('adminforum')
    || User::hasPrivilege('adminpluginpage')
) {

    // akce
    if (isset($_POST['ac'])) {
        $ac = Request::post('ac');

        switch ($ac) {
            // vytvoreni stranky
            case 'new':
                $is_plugin_page = false;
                if (is_numeric(Request::post('type'))) {
                    $type = (int) Request::post('type');
                } else {
                    $type = _page_plugin;
                    $type_idt = strval(Request::post('type'));
                    if (!isset($plugin_types[$type_idt])) {
                        break;
                    }
                    $is_plugin_page = true;
                }
                if (isset($type_array[$type]) && User::hasPrivilege('admin' . $type_array[$type])) {
                    $admin_redirect_to = 'index.php?p=content-edit' . $type_array[$type] . ($is_plugin_page ? '&idt=' . rawurlencode($type_idt) : '');

                    return;
                }
                break;
        }
    }

    // horni panel

    // seznam typu stranek
    $create_list = "";
    if (User::hasPrivilege('adminpages')) {
        foreach ($type_array as $type => $name) {
            if ($type != _page_plugin && User::hasPrivilege('admin' . $name)) {
                $create_list .= "<option value='" . $type . "'>" . _lang('page.type.' . $name) . "</option>\n";
            }
        }

        // seznam pluginovych typu stranek
        if (User::hasPrivilege('adminpluginpage') && !empty($plugin_types)) {
            $create_list .= "<option value='' disabled>---</option>\n";
            foreach($plugin_types as $plugin_type => $plugin_label) {
                $create_list .= "<option value='" . $plugin_type . "'>" . $plugin_label . "</option>\n";
            }
        }
    }

    $pageitems = '
    <td class="contenttable-box" style="' . ((User::hasPrivilege('adminart') || User::hasPrivilege('adminconfirm') || User::hasPrivilege('admincategory') || User::hasPrivilege('adminpoll') || User::hasPrivilege('adminsbox') || User::hasPrivilege('adminbox')) ? 'width: 75%; ' : 'border-right: none;') . 'padding-bottom: 0px;">

    ' . (User::hasPrivilege('adminpages') ? '
    <form action="index.php?p=content" method="post" class="inline">
    <input type="hidden" name="ac" value="new">
    <img src="images/icons/new.png" alt="new" class="icon">
    <select name="type">
    ' . $create_list . '
    </select>
    <input class="button" type="submit" value="' . _lang('global.create') . '">
    ' . Xsrf::getInput() . '</form>

    <span class="inline-separator"></span>
    ' : '' ) . '

    ' . (User::hasPrivilege('adminpages') ? '
    <a class="button" href="index.php?p=content-setindex"><img src="images/icons/home.png" alt="act" class="icon">' . _lang('admin.content.setindex') . '</a>

    <span class="inline-separator"></span>

    <a class="button" href="index.php?p=content-sort"><img src="images/icons/action.png" alt="move" class="icon">' . _lang('admin.content.sort') . '</a>
    <a class="button" href="index.php?p=content-titles"><img src="images/icons/action.png" alt="titles" class="icon">' . _lang('admin.content.titles') . '</a>
    <a class="button" href="index.php?p=content-redir"><img src="images/icons/action.png" alt="redir" class="icon">' . _lang('admin.content.redir') . '</a>

    <span class="inline-separator"></span>
    ' : '' ) . '

    <a class="button" href="index.php?p=content&amp;list_mode=tree"' . (PageLister::MODE_FULL_TREE == PageLister::getConfig('mode') ? ' class="active-link"' : '') . '><img src="images/icons/tree.png" alt="move" class="icon">' . _lang('admin.content.mode.tree') . '</a>
    <a class="button" href="index.php?p=content&amp;list_mode=single"' . (PageLister::MODE_SINGLE_LEVEL == PageLister::getConfig('mode') ? ' class="active-link"' : '') . '><img src="images/icons/list.png" alt="move" class="icon">' . _lang('admin.content.mode.single') . '</a>

    <div class="hr"><hr></div>

    ';

    // tabulka polozek
    if (
        User::hasPrivilege('adminpages')
        && PageLister::getConfig('mode') == PageLister::MODE_SINGLE_LEVEL
    ) {
        $sortable = true;
    } else {
        $sortable = false;
    }

    $pageitems .= Extend::buffer('admin.content.pagelist.before');

    $pageitems .= PageLister::render([
        'type' => true,
        'flags' => true,
        'sortable' => $sortable,
    ]);

    $pageitems .= Extend::buffer('admin.content.pagelist.after');

    $pageitems .= '
</td>
';
} else {
    $pageitems = '';
}

// nabidka modulu
$content_modules = [
    'layout' => [
        'modules' => [
            'boxes' => [
                'url' => 'index.php?p=content-boxes',
                'icon' => 'images/icons/big-layout.png',
                'access' => User::hasPrivilege('adminbox'),
            ],
        ],
    ],

    'articles' => [
        'modules' => [
            'newart' => [
                'url' => 'index.php?p=content-articles-edit',
                'icon' => 'images/icons/big-new.png',
                'access' => User::hasPrivilege('adminart'),
            ],
            'manage' => [
                'url' => 'index.php?p=content-articles',
                'icon' => 'images/icons/big-list.png',
                'access' => User::hasPrivilege('adminart'),
                'label' => _lang('admin.content.manage'),
            ],
            'confirm' => [
                'url' => 'index.php?p=content-confirm',
                'icon' => 'images/icons/big-check.png',
                'access' => User::hasPrivilege('adminconfirm'),
            ],
            'movearts' => [
                'url' => 'index.php?p=content-movearts',
                'icon' => 'images/icons/big-move.png',
                'access' => User::hasPrivilege('admincategory'),
            ],
            'artfilter' => [
                'url' => 'index.php?p=content-artfilter',
                'icon' => 'images/icons/big-filter.png',
                'access' => User::hasPrivilege('admincategory'),
            ],
        ],
    ],

    'widgets' => [
        'modules' => [
            'polls' => [
                'url' => 'index.php?p=content-polls',
                'icon' => 'images/icons/big-bars.png',
                'access' => User::hasPrivilege('adminpoll'),
            ],
            'sboxes' => [
                'url' => 'index.php?p=content-sboxes',
                'icon' => 'images/icons/big-bubbles.png',
                'access' => User::hasPrivilege('adminsbox'),
            ],
        ],
    ],
];

Extend::call('admin.content.modules', ['modules' => &$content_modules]);

$content_modules_str = '';
foreach ($content_modules as $category_alias => $category_data) {
    $buttons_str = '';
    foreach ($category_data['modules'] as $module_alias => $module_options) {
        if ($module_options['access']) {
            $module_label = $module_options['label'] ?? _lang('admin.content.' . $module_alias);
            $buttons_str .= '<a class="button block" href="' . _e($module_options['url']) . '"><img class="icon" alt="' . _e($module_label) . '" src="' . _e($module_options['icon']) . '">' . $module_label . "</a>\n";
        }
    }

    if ($buttons_str !== '') {
        $content_modules_str .= '<div class="content-' . $category_alias . '">
<h2>' . ($category_data['label'] ?? _lang('admin.content.' . $category_alias)) . '</h2>
' . $buttons_str;
    }
}

/* ---  vystup  --- */

// zprava
if (isset($_GET['done'])) {
    $message = Message::ok(_lang('global.done'));
}

$output .= $message . '
<table id="contenttable">
<tr class="valign-top">
  ' . $pageitems . '
  ' . ($content_modules_str !== '' ? "<td class=\"contenttable-box\" id=\"content-modules\">{$content_modules_str}</td>" : '') . '
</tr>
</table>
';
