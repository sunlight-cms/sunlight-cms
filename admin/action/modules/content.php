<?php

use Sunlight\Admin\PageLister;
use Sunlight\Extend;
use Sunlight\Message;
use Sunlight\Page\Page;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

$message = '';

/* ---- prepare content modules (sidebar)  ---- */
$content_modules = [
    'layout' => [
        'modules' => [
            'boxes' => [
                'url' => Router::admin('content-boxes'),
                'icon' => Router::path('admin/public/images/icons/big-layout.png'),
                'access' => User::hasPrivilege('adminbox'),
            ],
        ],
    ],

    'articles' => [
        'modules' => [
            'newart' => [
                'url' => Router::admin('content-articles-edit'),
                'icon' => Router::path('admin/public/images/icons/big-new.png'),
                'access' => User::hasPrivilege('adminart'),
            ],
            'manage' => [
                'url' => Router::admin('content-articles'),
                'icon' => Router::path('admin/public/images/icons/big-list.png'),
                'access' => User::hasPrivilege('adminart'),
                'label' => _lang('admin.content.manage'),
            ],
            'confirm' => [
                'url' => Router::admin('content-confirm'),
                'icon' => Router::path('admin/public/images/icons/big-check.png'),
                'access' => User::hasPrivilege('adminconfirm'),
            ],
            'movearts' => [
                'url' => Router::admin('content-movearts'),
                'icon' => Router::path('admin/public/images/icons/big-move.png'),
                'access' => User::hasPrivilege('admincategory'),
            ],
            'artfilter' => [
                'url' => Router::admin('content-artfilter'),
                'icon' => Router::path('admin/public/images/icons/big-filter.png'),
                'access' => User::hasPrivilege('admincategory'),
            ],
        ],
    ],

    'widgets' => [
        'modules' => [
            'polls' => [
                'url' => Router::admin('content-polls'),
                'icon' => Router::path('admin/public/images/icons/big-bars.png'),
                'access' => User::hasPrivilege('adminpoll'),
            ],
            'sboxes' => [
                'url' => Router::admin('content-sboxes'),
                'icon' => Router::path('admin/public/images/icons/big-bubbles.png'),
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
' . $buttons_str . '</div>';
    }
}

/* ---- prepare page list  ---- */
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
    // new page creation
    if (isset($_POST['new_page_type'])) {
        $type = Request::post('new_page_type', '');
        $type_idt = null;

        if (!isset(Page::TYPES[$type])) {
            $type_idt = $type;
            $type = Page::PLUGIN;
        }

        if (isset(Page::TYPES[$type]) && User::hasPrivilege('admin' . Page::TYPES[$type])) {
            $_admin->redirect(Router::admin('content-edit' . Page::TYPES[$type], ($type == Page::PLUGIN ? ['query' => ['idt' => $type_idt]] : null)));

            return;
        }
    }

    // page type list
    $create_choices = [];

    if (User::hasPrivilege('adminpages')) {
        foreach (Page::TYPES as $type => $name) {
            if ($type != Page::PLUGIN && User::hasPrivilege('admin' . $name)) {
                $create_choices[$type] = _lang('page.type.' . $name);
            }
        }

        // add plugin page types
        if (User::hasPrivilege('adminpluginpage')) {
            $create_choices += Page::getPluginTypes();
        }
    }

    $show_modules = (
        User::hasPrivilege('adminart')
        || User::hasPrivilege('adminconfirm')
        || User::hasPrivilege('admincategory')
        || User::hasPrivilege('adminpoll')
        || User::hasPrivilege('adminsbox')
        || User::hasPrivilege('adminbox')
    );

    $pageitems = '
    <td class="form-box' . ($show_modules ? ' main-box' : '') . '">

    <div id="contenttable-actions">
        ' . (User::hasPrivilege('adminpages') ? '
        <form action="' . _e(Router::admin('content')) . '" method="post" class="inline">
            <img src="' . _e(Router::path('admin/public/images/icons/new.png')) . '" alt="new" class="icon">
            ' . Form::select('new_page_type', $create_choices) . '
            ' . Form::input('submit', null, _lang('global.create'), ['class' => 'button']) . '
        ' . Xsrf::getInput() . '</form>
    
        <span class="inline-separator"></span>
        ' : '') . '
    
        ' . (User::hasPrivilege('adminpages') ? '
        <a class="button" href="' . _e(Router::admin('content-setindex')) . '"><img src="' . _e(Router::path('admin/public/images/icons/home.png')) . '" alt="act" class="icon">' . _lang('admin.content.setindex') . '</a>
    
        <span class="inline-separator"></span>
    
        <a class="button" href="' . _e(Router::admin('content-sort')) . '"><img src="' . _e(Router::path('admin/public/images/icons/action.png')) . '" alt="move" class="icon">' . _lang('admin.content.sort') . '</a>
        <a class="button" href="' . _e(Router::admin('content-titles')) . '"><img src="' . _e(Router::path('admin/public/images/icons/action.png')) . '" alt="titles" class="icon">' . _lang('admin.content.titles') . '</a>
        <a class="button" href="' . _e(Router::admin('content-chtype')) . '"><img src="' . _e(Router::path('admin/public/images/icons/action.png')) . '" alt="redir" class="icon">' . _lang('admin.content.chtype') . '</a>
        <a class="button" href="' . _e(Router::admin('content-redir')) . '"><img src="' . _e(Router::path('admin/public/images/icons/action.png')) . '" alt="redir" class="icon">' . _lang('admin.content.redir') . '</a>
    
        <span class="inline-separator"></span>
        ' : '') . '
    
        <a class="button" href="' . _e(Router::admin('content', ['query' => ['list_mode' => 'tree']])) . '"' . (PageLister::getConfig('mode') == PageLister::MODE_FULL_TREE ? ' class="active-link"' : '') . '>
            <img src="' . _e(Router::path('admin/public/images/icons/tree.png')) . '" alt="move" class="icon">' . _lang('admin.content.mode.tree') . '
        </a>
        <a class="button" href="' . _e(Router::admin('content', ['query' => ['list_mode' => 'single']])) . '"' . (PageLister::getConfig('mode') == PageLister::MODE_SINGLE_LEVEL ? ' class="active-link"' : '') . '>
            <img src="' . _e(Router::path('admin/public/images/icons/list.png')) . '" alt="move" class="icon">' . _lang('admin.content.mode.single') . '
        </a>
    </div>

    <div class="hr"><hr></div>

    ';

    // page table
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

/* ---- output  ---- */
if (isset($_GET['done'])) {
    $message = Message::ok(_lang('global.done'));
}

$output .= $message . '
<table id="contenttable" class="table-collapse">
<tr class="valign-top">
  ' . $pageitems . '
  ' . ($content_modules_str !== '' ? '<td class="form-box" id="content-modules">' . $content_modules_str . '</td>' : '') . '
</tr>
</table>
';
