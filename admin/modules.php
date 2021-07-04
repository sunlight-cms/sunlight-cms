<?php

use Sunlight\User;

defined('_root') or exit;

/* --- definice systemovych modulu --- */

/*

    Format pole je:

    array(
        nazev-modulu => array(
            title                   => titulek modulu
            access                  => 1/0 nebo string (vyraz jako PHP kod)

            [script]                => cesta ke skriptu (vychozi je "module/*nazev*.php", false = zadny)
            [url]                   => vlastni URL modulu (vychozi je "index.php?p=*nazev*")
            [parent]                => nazev nadrazeneho modulu
            [children]              => pole jako seznam podrazenych modulu
            [custom_header] (0)     => 1/0 nevykreslovat titulek a zpetny odkaz

            [menu] (false)          => 1/0 zobrazovat modul v hlavnim menu
            [menu_order] (15)       => poradi v hlavnim menu

            [other] (false)         => 1/0 zobrazovat modul ve vypisu ostatnich funkci
            [other_order] (0)       => 1/0 poradi ve vypisu ostatnich funkci
            [other_system] (0)      => 1/0 zobrazovat mezi systemovymi moduly v ost. funkcich
            [other_icon]            => cesta k ikone tlacitka ve vypisu ost. funkci
            [other_new_window] (0)  => odkazovat do noveho okna ve vypisu ost. funkci.
        ),
        ...
    )

*/

return [

    // index
    'index' => [
        'title' => _lang('admin.menu.index'),
        'access' => true,
        'parent' => null,
        'children' => ['index-edit'],
        'custom_header' => true,
        'menu' => true,
        'menu_order' => 0,
    ],
    'index-edit' => [
        'title' => _lang('admin.menu.index.edit.title'),
        'access' => User::$group['id'] == 1,
        'parent' => 'index',
    ],

    // content
    'content' => [
        'title' => _lang('admin.menu.content'),
        'access' => User::hasPrivilege('admincontent'),
        'parent' => null,
        'children' => [
            'content-sort',
            'content-titles',
            'content-redir',
            'content-articles',
            'content-confirm',
            'content-movearts',
            'content-polls',
            'content-polls-edit',
            'content-boxes',
            'content-editsection',
            'content-editcategory',
            'content-delete',
            'content-editgroup',
            'content-articles-list',
            'content-articles-edit',
            'content-articles-delete',
            'content-boxes-edit',
            'content-editbook',
            'content-editseparator',
            'content-editlink',
            'content-editpluginpage',
            'content-editgallery',
            'content-manageimgs',
            'content-artfilter',
        ],
        'menu' => true,
        'menu_order' => 10,
    ],
    'content-setindex' => [
        'title' => _lang('admin.content.setindex.title'),
        'access' => User::hasPrivilege('admincontent') && User::hasPrivilege('adminpages'),
        'parent' => 'content',
    ],
    'content-sort' => [
        'title' => _lang('admin.content.sort.title'),
        'access' => User::hasPrivilege('admincontent') && User::hasPrivilege('adminpages'),
        'parent' => 'content',
    ],
    'content-titles' => [
        'title' => _lang('admin.content.titles.title'),
        'access' => User::hasPrivilege('admincontent') && User::hasPrivilege('adminpages'),
        'parent' => 'content',
    ],
    'content-redir' => [
        'title' => _lang('admin.content.redir.title'),
        'access' => User::hasPrivilege('admincontent') && User::hasPrivilege('adminpages'),
        'parent' => 'content',
    ],
    'content-articles' => [
        'title' => _lang('admin.content.articles.title'),
        'access' => User::hasPrivilege('adminart'),
        'parent' => 'content',
    ],
    'content-articles-list' => ['title' => _lang('admin.content.articles.list.title'),
        'access' => User::hasPrivilege('adminart'),
        'parent' => 'content-articles',
    ],
    'content-articles-edit' => ['title' => _lang('admin.content.articles.edit.title'),
        'access' => User::hasPrivilege('adminart'),
        'parent' => 'content-articles',
        'custom_header' => true
    ],
    'content-articles-delete' => [
        'title' => _lang('admin.content.articles.delete.title'),
        'access' => User::hasPrivilege('adminart'),
        'parent' => 'content-articles',
        'custom_header' => true
    ],
    'content-confirm' => [
        'title' => _lang('admin.content.confirm.title'),
        'access' => User::hasPrivilege('adminconfirm'),
        'parent' => 'content',
    ],
    'content-movearts' => [
        'title' => _lang('admin.content.movearts.title'),
        'access' => User::hasPrivilege('admincategory'),
        'parent' => 'content',
    ],
    'content-artfilter' => [
        'title' => _lang('admin.content.artfilter.title'),
        'access' => User::hasPrivilege('admincategory'),
        'parent' => 'content',
    ],
    'content-polls' => [
        'title' => _lang('admin.content.polls.title'),
        'access' => User::hasPrivilege('adminpoll'),
        'parent' => 'content',
    ],
    'content-polls-edit' => [
        'title' => _lang('admin.content.polls.edit.title'),
        'access' => User::hasPrivilege('adminpoll'),
        'parent' => 'content-polls',
    ],
    'content-sboxes' => ['title' => _lang('admin.content.sboxes.title'),
        'access' => User::hasPrivilege('adminsbox'),
        'parent' => 'content',
    ],
    'content-boxes' => [
        'title' => _lang('admin.content.boxes.title'),
        'access' => User::hasPrivilege('adminbox'),
        'parent' => 'content',
    ],
    'content-boxes-edit' => [
        'title' => _lang('admin.content.boxes.edit.title'),
        'access' => User::hasPrivilege('adminbox'),
        'parent' => 'content-boxes',
    ],
    'content-delete' => [
        'title' => _lang('admin.content.delete.title'),
        'access' => true,
        'parent' => 'content',
    ],
    'content-editsection' => [
        'title' => _lang('admin.content.editsection.title'),
        'access' => User::hasPrivilege('adminsection'),
        'parent' => 'content',
    ],
    'content-editcategory' => [
        'title' => _lang('admin.content.editcategory.title'),
        'access' => User::hasPrivilege('admincategory'),
        'parent' => 'content',
    ],
    'content-editgroup' => [
        'title' => _lang('admin.content.editgroup.title'),
        'access' => User::hasPrivilege('admingroup'),
        'parent' => 'content',
    ],
    'content-editbook' => [
        'title' => _lang('admin.content.editbook.title'),
        'access' => User::hasPrivilege('adminbook'),
        'parent' => 'content',
    ],
    'content-editseparator' => [
        'title' => _lang('admin.content.editseparator.title'),
        'access' => User::hasPrivilege('adminseparator'),
        'parent' => 'content',
    ],
    'content-editlink' => [
        'title' => _lang('admin.content.editlink.title'),
        'access' => User::hasPrivilege('adminlink'),
        'parent' => 'content',
    ],
    'content-editgallery' => [
        'title' => _lang('admin.content.editgallery.title'),
        'access' => User::hasPrivilege('admingallery'),
        'parent' => 'content',
    ],
    'content-editforum' => [
        'title' => _lang('admin.content.editforum.title'),
        'access' => User::hasPrivilege('adminforum'),
        'parent' => 'content',
    ],
    'content-editpluginpage' => [
        'title' => _lang('admin.content.editpluginpage.title'),
        'access' => User::hasPrivilege('adminpluginpage'),
        'parent' => 'content',
    ],
    'content-manageimgs' => [
        'title' => _lang('admin.content.manageimgs.title'),
        'access' => User::hasPrivilege('admingallery'),
        'parent' => 'content',
        'custom_header' => true,
    ],

    // users
    'users' => [
        'title' => _lang('admin.menu.users'),
        'access' => User::hasPrivilege('adminusers') || User::hasPrivilege('admingroups'),
        'parent' => null,
        'children' => ['users-editgroup', 'users-delgroup', 'users-edit', 'users-delete', 'users-list', 'users-move'],
        'menu' => true,
        'menu_order' => 20,
    ],
    'users-editgroup' => [
        'title' => _lang('admin.users.groups.edittitle'),
        'access' => User::hasPrivilege('admingroups'),
        'parent' => 'users',
    ],
    'users-delgroup' => [
        'title' => _lang('admin.users.groups.deltitle'),
        'access' => User::hasPrivilege('admingroups'),
        'parent' => 'users',
    ],
    'users-list' => [
        'title' => _lang('admin.users.list'),
        'access' => User::hasPrivilege('adminusers'),
        'parent' => 'users',
        'children' => ['users-edit', 'users-delete'],
    ],
    'users-edit' => [
        'title' => _lang('admin.users.edit.title'),
        'access' => User::hasPrivilege('adminusers'),
        'parent' => 'users-list',
    ],
    'users-delete' => [
        'title' => _lang('admin.users.deleteuser'),
        'access' => User::hasPrivilege('adminusers'),
        'parent' => 'users-list',
    ],
    'users-move' => [
        'title' => _lang('admin.users.move'),
        'access' => User::hasPrivilege('adminusers'),
        'parent' => 'users',
    ],

    // fman
    'fman' => [
        'title' => _lang('admin.menu.fman'),
        'access' => User::hasPrivilege('fileaccess'),
        'parent' => null,
        'menu' => true,
        'menu_order' => 30,
    ],

    // plugins
    'plugins' => [
        'title' => _lang('admin.menu.plugins'),
        'access' => User::hasPrivilege('adminplugins'),
        'parent' => null,
        'children' => ['plugins-action', 'plugins-upload'],
        'menu' => true,
        'menu_order' => 40,
    ],
    'plugins-action' => [
        'title' => _lang('admin.plugins.action'),
        'access' => User::hasPrivilege('adminplugins'),
        'parent' => 'plugins',
        'custom_header' => true,
    ],
    'plugins-upload' => [
        'title' => _lang('admin.plugins.upload'),
        'access' => User::hasPrivilege('adminplugins'),
        'parent' => 'plugins',
    ],

    // settings
    'settings' => [
        'title' => _lang('admin.menu.settings'),
        'access' => User::hasPrivilege('adminsettings'),
        'parent' => null,
        'menu' => true,
        'menu_order' => 50,
    ],

    // backup
    'backup' => [
        'title' => _lang('admin.backup.title'),
        'access' => User::hasPrivilege('adminbackup'),
        'parent' => null,
        'menu' => true,
        'menu_order' => 60,
    ],

    // other
    'other' => [
        'title' => _lang('admin.menu.other'),
        'access' => User::hasPrivilege('adminother'),
        'parent' => null,
        'children' => ['other-massemail', 'other-cleanup', 'other-sqlex'],
        'menu' => true,
        'menu_order' => 70,
    ],
    'other-patch' => [
        'title' => _lang('admin.other.patch.title'),
        'access' => User::hasPrivilege('adminother') && User::isSuperAdmin(),
        'parent' => 'other',
        'other' => true,
        'other_system' => true,
        'other_order' => 0,
        'other_icon' => 'images/icons/big-update.png',
    ],
    'other-cleanup' => [
        'title' => _lang('admin.other.cleanup.title'),
        'access' => User::hasPrivilege('adminother') && User::isSuperAdmin(),
        'parent' => 'other',
        'other' => true,
        'other_system' => true,
        'other_order' => 10,
        'other_icon' => 'images/icons/big-broom.png',
    ],
    'other-sqlex' => [
        'title' => _lang('admin.other.sqlex.title'),
        'access' => User::hasPrivilege('adminother') && User::isSuperAdmin(),
        'parent' => 'other',
        'other' => true,
        'other_system' => true,
        'other_order' => 20,
        'other_icon' => 'images/icons/big-db.png',
    ],
    'other-php' => [
        'title' => _lang('admin.other.php.title'),
        'access' => User::hasPrivilege('adminother') && User::isSuperAdmin(),
        'url' => 'script/php.php',
        'parent' => 'other',
        'other' => true,
        'other_system' => true,
        'other_order' => 30,
        'other_new_window' => true,
        'other_icon' => 'images/icons/big-php.png',
    ],
    'other-massemail' => [
        'title' => _lang('admin.other.massemail.title'),
        'access' => User::hasPrivilege('adminother') && User::hasPrivilege('adminmassemail'),
        'parent' => 'other',
        'other' => true,
        'other_system' => true,
        'other_order' => 40,
        'other_icon' => 'images/icons/big-mail.png',
    ],
];
