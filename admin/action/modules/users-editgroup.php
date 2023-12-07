<?php

use Sunlight\Admin\Admin;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\Image\ImageService;
use Sunlight\Logger;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Html;
use Sunlight\Util\Math;
use Sunlight\Util\Request;
use Sunlight\Util\StringHelper;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

$levelconflict = false;
$sysgroups_array = [User::ADMIN_GROUP_ID, User::GUEST_GROUP_ID /*,User::REGISTERED_GROUP_ID is not necessary*/ ];
$unregistered_useable = ['postcomments', 'artrate', 'pollvote'];

// load group
$continue = false;

if (isset($_GET['id'])) {
    $id = (int) Request::get('id');
    $query = DB::queryRow('SELECT * FROM ' . DB::table('user_group') . ' WHERE id=' . $id);

    if ($query !== false) {
        $systemitem = in_array($query['id'], $sysgroups_array);

        if (User::getLevel() > $query['level']) {
            $continue = true;
        } else {
            $levelconflict = true;
        }
    }
}

if ($continue) {
    $rights_array = [
        [
            'title' => _lang('admin.users.groups.commonrights'),
            'rights' => [
                ['name' => 'changeusername'],
                ['name' => 'selfremove'],
                ['name' => 'artrate'],
                ['name' => 'pollvote'],
            ],
        ],
        [
            'title' => _lang('admin.users.groups.postrights'),
            'rights' => [
                ['name' => 'postcomments'],
                ['name' => 'locktopics'],
                ['name' => 'stickytopics'],
                ['name' => 'movetopics'],
                ['name' => 'adminposts'],
                ['name' => 'unlimitedpostaccess'],
            ],
        ],
        [
            'title' => _lang('admin.users.groups.filerights'),
            'rights' => [
                ['name' => 'fileaccess'],
                ['name' => 'fileglobalaccess'],
                ['name' => 'fileadminaccess', 'dangerous' => true],
            ],
        ],
        [
            'title' => _lang('admin.users.groups.adminrights'),
            'rights' => [
                ['name' => 'administration'],
                ['name' => 'adminusers'],
                ['name' => 'admingroups'],
                ['name' => 'adminplugins', 'dangerous' => true],
                ['name' => 'adminsettings', 'dangerous' => true],
                ['name' => 'adminbackup', 'dangerous' => true],
            ],
        ],
        [
            'title' => _lang('admin.users.groups.adminotherrights'),
            'rights' => [
                ['name' => 'adminother'],
                ['name' => 'adminmassemail'],
            ],
        ],
        [
            'title' => _lang('admin.users.groups.adminhcmrights'),
            'rights' => [
                ['name' => 'adminhcm', 'text' => true, 'dangerous' => true],
                ['name' => 'adminhcmphp', 'dangerous' => true],
            ],
        ],
        [
            'title' => _lang('admin.users.groups.admincontentrights'),
            'rights' => [
                ['name' => 'admincontent'],
                ['name' => 'adminpages'],
                ['name' => 'adminsection'],
                ['name' => 'admincategory'],
                ['name' => 'adminbook'],
                ['name' => 'adminseparator'],
                ['name' => 'admingallery'],
                ['name' => 'adminlink'],
                ['name' => 'admingroup'],
                ['name' => 'adminforum'],
                ['name' => 'adminpluginpage'],
                ['name' => 'rawhtml', 'dangerous' => true],
            ],
        ],
        [
            'title' => _lang('admin.users.groups.admincontentarticlerights'),
            'rights' => [
                ['name' => 'adminart'],
                ['name' => 'adminallart'],
                ['name' => 'adminchangeartauthor'],
                ['name' => 'adminconfirm'],
                ['name' => 'adminautoconfirm'],
            ],
        ],
        [
            'title' => _lang('admin.users.groups.admincontentotherrights'),
            'rights' => [
                ['name' => 'adminpoll'],
                ['name' => 'adminpollall'],
                ['name' => 'adminsbox'],
                ['name' => 'adminbox'],
            ],
        ],
    ];

    Extend::call('admin.editgroup.rights', [
        'rights' => &$rights_array,
        'unregistered_rights' => &$unregistered_useable,
    ]);

    $rights = '';

    foreach ($rights_array as $section) {
        $editable_rights = [];

        foreach ($section['rights'] as $item) {
            if (
                $id == User::ADMIN_GROUP_ID
                || $id == User::GUEST_GROUP_ID && !in_array($item['name'], $unregistered_useable, true)
                || empty($item['text']) && !User::hasPrivilege($item['name'])
            ) {
                continue;
            }

            $editable_rights[] = $item;
        }

        if (empty($editable_rights)) {
            continue;
        }

        $rights .= '<fieldset><legend>' . $section['title'] . "</legend><table>\n";

        foreach ($editable_rights as $item) {
            $isText = !empty($item['text']);

            $rights .= '<tr>
    <th' . (!empty($item['dangerous']) ? ' class="important"' : '') . '>
        <label for="setting_' . $item['name'] . '">' . ($item['label'] ?? _lang('admin.users.groups.' . $item['name'])) . '</label>
    </th>
    <td>
        <label>
             ' . Form::input(
                    $isText ? 'text' : 'checkbox',
                    $item['name'],
                    $isText ? $query[$item['name']] : '1',
                   ['id' => 'setting_' . $item['name']] + ($isText ? [] : ['checked' => (bool) $query[$item['name']]])
                ) . '
            ' . ($item['help'] ?? _lang('admin.users.groups.' . $item['name'] . '.help')) . "
        </label>
    </td>
</tr>\n";
        }

        $rights .= "</table></fieldset>\n";
    }

    // save
    if (!empty($_POST)) {
        $changeset = [];

        // base data
        $changeset['title'] = Html::cut(_e(trim(Request::post('title', ''))), 128);

        if ($changeset['title'] == '') {
            $changeset['title'] = _lang('global.novalue');
        }

        $changeset['descr'] = Html::cut(_e(trim(Request::post('descr', ''))), 255);

        if ($id != User::GUEST_GROUP_ID) {
            $changeset['icon'] = StringHelper::cut(trim(Request::post('icon', '')), 16);
        }

        $changeset['color'] = Admin::formatHtmlColor(Request::post('color', ''), false, '');

        if ($id > User::GUEST_GROUP_ID) {
            $changeset['blocked'] = Form::loadCheckbox('blocked');
        }

        if ($id != User::GUEST_GROUP_ID) {
            $changeset['reglist'] = Form::loadCheckbox('reglist');
        }

        if ($id > User::GUEST_GROUP_ID) {
            $changeset['level'] = Math::range((int) Request::post('level'), 0, min(User::getLevel(), User::MAX_ASSIGNABLE_LEVEL));
        }

        // privileges
        if ($id != User::ADMIN_GROUP_ID) {
            foreach ($rights_array as $section) {
                foreach ($section['rights'] as $item) {
                    if (
                        $id == User::GUEST_GROUP_ID && !in_array($item['name'], $unregistered_useable, true)
                        || empty($item['text']) && !User::hasPrivilege($item['name'])
                    ) {
                        continue;
                    }

                    $isText = !empty($item['text']);

                    $changeset[$item['name']] = $isText ? trim(Request::post($item['name'])) : Form::loadCheckbox($item['name']);
                }
            }
        }

        // extend
        Extend::call('admin.editgroup.save', ['changeset' => &$changeset]);

        // save
        DB::update('user_group', 'id=' . $id, $changeset);

        // log
        Logger::notice(
            'user',
            sprintf('User group "%s" updated via admin module', $query['title']),
            ['group_id' => $id, 'diff' => array_diff_assoc($changeset, $query)]
        );

        // redirect
        $_admin->redirect(Router::admin('users-editgroup', ['query' => ['id' => $id, 'saved' => 1]]));

        return;
    }

    // load icons
    if ($id != User::GUEST_GROUP_ID) {
        $icons = "<div class=\"radio-group\">\n";
        $icons .= '<label>' . Form::input('radio', 'icon', '', ['checked' => ($query['icon'] === '')]) . ' ' . _lang('global.undefined') . "</label>\n";

        $icon_dir = SL_ROOT . 'images/groupicons';

        foreach (scandir($icon_dir) as $file) {
            if (
                $file === '.'
                || $file === '..'
                || !is_file($icon_dir . '/' . $file)
                || !ImageService::isImage($file)
            ) {
                continue;
            }

            $icons .= '<label>' . Form::input('radio', 'icon', $file, ['checked' => ($file === $query['icon'])])
                . ' <img class="icon" src="' . Router::file($icon_dir . '/' . $file) . '" alt="' . _e($file) . '">'
                . "</label>\n";
        }

        $icons .= "<div class=\"cleaner\"></div></div>\n";
    }

    // output
    $output .= '
  <p class="bborder">' . _lang('admin.users.groups.editp') . '</p>
  ' . (isset($_GET['saved']) ? Message::ok(_lang('global.saved')) : '') . '
  ' . ($systemitem ? Admin::note(_lang('admin.users.groups.specialgroup.editnotice')) : '') . '
  <form action="' . _e(Router::admin('users-editgroup', ['query' => ['id' => $id]])) . '" method="post">
  <table>

  <tr>
  <th>' . _lang('global.name') . '</th>
  <td>' . Form::input('text', 'title', $query['title'], ['class' => 'inputmedium', 'maxlength' => 128], false) . '</td>
  </tr>

  <tr>
  <th>' . _lang('global.descr') . '</th>
  <td>' . Form::input('text', 'descr', $query['descr'], ['class' => 'inputmedium', 'maxlength' => 255], false) . '</td>
  </tr>

  <tr>
  <th>' . _lang('admin.users.groups.level') . '</th>
  <td>' . Form::input('number', 'level', $query['level'], ['class' => 'inputmedium', 'min' => 0, 'max' => min(User::getLevel() - 1, User::MAX_ASSIGNABLE_LEVEL), 'disabled' => $systemitem]) . '</td>        
  </tr>

  ' . (($id != User::GUEST_GROUP_ID) ? '
  <tr><th><dfn title="' . _lang('admin.users.groups.icon.help', ['%dir%' => $icon_dir]) . '">' . _lang('admin.users.groups.icon') . '</dfn></th><td>' . $icons . '</td></tr>
  <tr>
    <th>' . _lang('admin.users.groups.color') . '</th>
    <td>
        ' . Form::input('text', 'color', $query['color'], ['class' => 'inputsmall', 'maxlength' => 16]) . '
        ' . Form::input('color', null, Admin::formatHtmlColor($query['color']), ['onchange' => 'this.form.elements.color.value=this.value']) . '
    </td>
  </tr>
  <tr><th>' . _lang('admin.users.groups.reglist') . '</th><td>' . Form::input('checkbox', 'reglist', '1', ['checked' => (bool) $query['reglist']]) . '</td></tr>
  ' : '') . '

  <tr>
  <th>' . _lang('admin.users.groups.blocked') . '</th>
  <td>' . Form::input('checkbox', 'blocked', '1', ['checked' => (bool) $query['blocked'], 'disabled' => !($id != User::ADMIN_GROUP_ID && $id != User::GUEST_GROUP_ID)]) . '</td>
  </tr>

  </table>

  ' . ($id != User::ADMIN_GROUP_ID ? Message::ok(_lang('admin.users.groups.dangernotice'), true) : '') . '
  ' . ($rights !== '' ? $rights : '<br>') . '
  ' . Extend::buffer('admin.editgroup.form') . '

  ' . Form::input('submit', null, _lang('global.save'), ['class' => 'button bigger', 'accesskey' => 's']) . ' <small>' . _lang('admin.content.form.thisid') . ' ' . $id . '</small>

  ' . Xsrf::getInput() . '</form>
  ';
} elseif ($levelconflict == false) {
    $output .= Message::error(_lang('global.badinput'));
} else {
    $output .= Message::error(_lang('global.disallowed'));
}
