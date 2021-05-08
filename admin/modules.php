<?php

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
        'access' => _user_group == 1,
        'parent' => 'index',
    ],

    // content
    'content' => [
        'title' => _lang('admin.menu.content'),
        'access' => _priv_admincontent,
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
        'access' => _priv_admincontent && _priv_adminpages,
        'parent' => 'content',
    ],
    'content-sort' => [
        'title' => _lang('admin.content.sort.title'),
        'access' => _priv_admincontent && _priv_adminpages,
        'parent' => 'content',
    ],
    'content-titles' => [
        'title' => _lang('admin.content.titles.title'),
        'access' => _priv_admincontent && _priv_adminpages,
        'parent' => 'content',
    ],
    'content-redir' => [
        'title' => _lang('admin.content.redir.title'),
        'access' => _priv_admincontent && _priv_adminpages,
        'parent' => 'content',
    ],
    'content-articles' => [
        'title' => _lang('admin.content.articles.title'),
        'access' => _priv_adminart,
        'parent' => 'content',
    ],
    'content-articles-list' => ['title' => _lang('admin.content.articles.list.title'),
        'access' => _priv_adminart,
        'parent' => 'content-articles',
    ],
    'content-articles-edit' => ['title' => _lang('admin.content.articles.edit.title'),
        'access' => _priv_adminart,
        'parent' => 'content-articles',
        'custom_header' => true
    ],
    'content-articles-delete' => [
        'title' => _lang('admin.content.articles.delete.title'),
        'access' => _priv_adminart,
        'parent' => 'content-articles',
        'custom_header' => true
    ],
    'content-confirm' => [
        'title' => _lang('admin.content.confirm.title'),
        'access' => _priv_adminconfirm,
        'parent' => 'content',
    ],
    'content-movearts' => [
        'title' => _lang('admin.content.movearts.title'),
        'access' => _priv_admincategory,
        'parent' => 'content',
    ],
    'content-artfilter' => [
        'title' => _lang('admin.content.artfilter.title'),
        'access' => _priv_admincategory,
        'parent' => 'content',
    ],
    'content-polls' => [
        'title' => _lang('admin.content.polls.title'),
        'access' => _priv_adminpoll,
        'parent' => 'content',
    ],
    'content-polls-edit' => [
        'title' => _lang('admin.content.polls.edit.title'),
        'access' => _priv_adminpoll,
        'parent' => 'content-polls',
    ],
    'content-sboxes' => ['title' => _lang('admin.content.sboxes.title'),
        'access' => _priv_adminsbox,
        'parent' => 'content',
    ],
    'content-boxes' => [
        'title' => _lang('admin.content.boxes.title'),
        'access' => _priv_adminbox,
        'parent' => 'content',
    ],
    'content-boxes-edit' => [
        'title' => _lang('admin.content.boxes.edit.title'),
        'access' => _priv_adminbox,
        'parent' => 'content-boxes',
    ],
    'content-delete' => [
        'title' => _lang('admin.content.delete.title'),
        'access' => true,
        'parent' => 'content',
    ],
    'content-editsection' => [
        'title' => _lang('admin.content.editsection.title'),
        'access' => _priv_adminsection,
        'parent' => 'content',
    ],
    'content-editcategory' => [
        'title' => _lang('admin.content.editcategory.title'),
        'access' => _priv_admincategory,
        'parent' => 'content',
    ],
    'content-editgroup' => [
        'title' => _lang('admin.content.editgroup.title'),
        'access' => _priv_admingroup,
        'parent' => 'content',
    ],
    'content-editbook' => [
        'title' => _lang('admin.content.editbook.title'),
        'access' => _priv_adminbook,
        'parent' => 'content',
    ],
    'content-editseparator' => [
        'title' => _lang('admin.content.editseparator.title'),
        'access' => _priv_adminseparator,
        'parent' => 'content',
    ],
    'content-editlink' => [
        'title' => _lang('admin.content.editlink.title'),
        'access' => _priv_adminlink,
        'parent' => 'content',
    ],
    'content-editgallery' => [
        'title' => _lang('admin.content.editgallery.title'),
        'access' => _priv_admingallery,
        'parent' => 'content',
    ],
    'content-editforum' => [
        'title' => _lang('admin.content.editforum.title'),
        'access' => _priv_adminforum,
        'parent' => 'content',
    ],
    'content-editpluginpage' => [
        'title' => _lang('admin.content.editpluginpage.title'),
        'access' => _priv_adminpluginpage,
        'parent' => 'content',
    ],
    'content-manageimgs' => [
        'title' => _lang('admin.content.manageimgs.title'),
        'access' => _priv_admingallery,
        'parent' => 'content',
        'custom_header' => true,
    ],

    // users
    'users' => [
        'title' => _lang('admin.menu.users'),
        'access' => _priv_adminusers || _priv_admingroups,
        'parent' => null,
        'children' => ['users-editgroup', 'users-delgroup', 'users-edit', 'users-delete', 'users-list', 'users-move'],
        'menu' => true,
        'menu_order' => 20,
    ],
    'users-editgroup' => [
        'title' => _lang('admin.users.groups.edittitle'),
        'access' => _priv_admingroups,
        'parent' => 'users',
    ],
    'users-delgroup' => [
        'title' => _lang('admin.users.groups.deltitle'),
        'access' => _priv_admingroups,
        'parent' => 'users',
    ],
    'users-list' => [
        'title' => _lang('admin.users.list'),
        'access' => _priv_adminusers,
        'parent' => 'users',
        'children' => ['users-edit', 'users-delete'],
    ],
    'users-edit' => [
        'title' => _lang('admin.users.edit.title'),
        'access' => _priv_adminusers,
        'parent' => 'users-list',
    ],
    'users-delete' => [
        'title' => _lang('admin.users.deleteuser'),
        'access' => _priv_adminusers,
        'parent' => 'users-list',
    ],
    'users-move' => [
        'title' => _lang('admin.users.move'),
        'access' => _priv_adminusers,
        'parent' => 'users',
    ],

    // fman
    'fman' => [
        'title' => _lang('admin.menu.fman'),
        'access' => _priv_fileaccess,
        'parent' => null,
        'menu' => true,
        'menu_order' => 30,
    ],

    // plugins
    'plugins' => [
        'title' => _lang('admin.menu.plugins'),
        'access' => _priv_adminplugins,
        'parent' => null,
        'children' => ['plugins-action', 'plugins-upload'],
        'menu' => true,
        'menu_order' => 40,
    ],
    'plugins-action' => [
        'title' => _lang('admin.plugins.action'),
        'access' => _priv_adminplugins,
        'parent' => 'plugins',
        'custom_header' => true,
    ],
    'plugins-upload' => [
        'title' => _lang('admin.plugins.upload'),
        'access' => _priv_adminplugins,
        'parent' => 'plugins',
    ],

    // settings
    'settings' => [
        'title' => _lang('admin.menu.settings'),
        'access' => _priv_adminsettings,
        'parent' => null,
        'menu' => true,
        'menu_order' => 50,
    ],

    // backup
    'backup' => [
        'title' => _lang('admin.backup.title'),
        'access' => _priv_adminbackup,
        'parent' => null,
        'menu' => true,
        'menu_order' => 60,
    ],

    // other
    'other' => [
        'title' => _lang('admin.menu.other'),
        'access' => _priv_adminother,
        'parent' => null,
        'children' => ['other-massemail', 'other-cleanup', 'other-sqlex'],
        'menu' => true,
        'menu_order' => 70,
    ],
    'other-patch' => [
        'title' => _lang('admin.other.patch.title'),
        'access' => _priv_adminother && _priv_super_admin,
        'parent' => 'other',
        'other' => true,
        'other_system' => true,
        'other_order' => 0,
        'other_icon' => 'images/icons/big-update.png',
    ],
    'other-cleanup' => [
        'title' => _lang('admin.other.cleanup.title'),
        'access' => _priv_adminother && _priv_super_admin,
        'parent' => 'other',
        'other' => true,
        'other_system' => true,
        'other_order' => 10,
        'other_icon' => 'images/icons/big-broom.png',
    ],
    'other-sqlex' => [
        'title' => _lang('admin.other.sqlex.title'),
        'access' => _priv_adminother && _priv_super_admin,
        'parent' => 'other',
        'other' => true,
        'other_system' => true,
        'other_order' => 20,
        'other_icon' => 'images/icons/big-db.png',
    ],
    'other-php' => [
        'title' => _lang('admin.other.php.title'),
        'access' => _priv_adminother && _priv_super_admin,
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
        'access' => _priv_adminother && _priv_adminmassemail,
        'parent' => 'other',
        'other' => true,
        'other_system' => true,
        'other_order' => 40,
        'other_icon' => 'images/icons/big-mail.png',
    ],
];
