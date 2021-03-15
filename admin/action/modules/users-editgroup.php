<?php

use Sunlight\Admin\Admin;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\Image\ImageService;
use Sunlight\Message;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Html;
use Sunlight\Util\Math;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('_root') or exit;

/* ---  priprava promennych  --- */

$levelconflict = false;
$sysgroups_array = [_group_admin, _group_guests /*,_group_registered is not necessary*/ ];
$unregistered_useable = ['postcomments', 'artrate', 'pollvote'];

// id
$continue = false;
if (isset($_GET['id'])) {
    $id = (int) Request::get('id');
    $query = DB::queryRow("SELECT * FROM " . _user_group_table . " WHERE id=" . $id);
    if ($query !== false) {
        $systemitem = in_array($query['id'], $sysgroups_array);
        if (_priv_level > $query['level']) {
            $continue = true;
        } else {
            $levelconflict = true;
        }
    }
}

if ($continue) {

    // pole prav
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

    $rights = "";
    foreach ($rights_array as $section) {
        $rights .= "<fieldset><legend>" . $section['title'] . "</legend><table>\n";
        foreach ($section['rights'] as $item) {
            if (
                $id == _group_admin
                || $id == _group_guests && !in_array($item['name'], $unregistered_useable, true)
                || !User::hasPrivilege($item['name'])
            ) {
                $disabled = true;
            } else {
                $disabled = false;
            }

            $isText = isset($item['text']) && $item['text'];

            $rights .= "<tr>
    <th" . (isset($item['dangerous']) && $item['dangerous'] ? ' class="important"' : '') . ">
        <label for='setting_" . $item['name'] . "'>" . ($item['label'] ?? _lang('admin.users.groups.' . $item['name'])) . "</label>
    </th>
    <td>
        <label>
            <input type='" . ($isText ? 'text' : 'checkbox') . "' id='setting_" . $item['name'] . "' name='" . $item['name'] . "'" . ($isText ? " value='" . _e($query[$item['name']]) . "'" : " value='1'" . Form::activateCheckbox($query[$item['name']])) . Form::disableInputUnless(!$disabled) . ">
            " . ($item['help'] ?? _lang('admin.users.groups.' . $item['name'] . '.help')) . "
        </label>
    </td>
</tr>\n";
        }

        $rights .= "</table></fieldset>\n";
    }

    /* ---  ulozeni  --- */
    if (!empty($_POST)) {

        $changeset = [];

        // zakladni atributy
        $changeset['title'] = Html::cut(_e(trim(Request::post('title'))), 128);
        if ($changeset['title'] == "") {
            $changeset['title'] = _lang('global.novalue');
        }
        $changeset['descr'] = Html::cut(_e(trim(Request::post('descr'))), 255);
        if ($id != _group_guests) {
            $changeset['icon'] = Html::cut(_e(trim(Request::post('icon'))), 16);
        }
        $changeset['color'] = Admin::formatHtmlColor(Request::post('color', ''), false, '');
        if ($id > _group_guests) {
            $changeset['blocked'] = Form::loadCheckbox('blocked');
        }
        if ($id != _group_guests) {
            $changeset['reglist'] = Form::loadCheckbox('reglist');
        }

        // uroven, blokovani
        if ($id > _group_guests) {
            $changeset['level'] = Math::range((int) Request::post('level'), 0, min(_priv_level, _priv_max_assignable_level));
        }

        // prava
        if ($id != _group_admin) {
            foreach ($rights_array as $section) {
                foreach ($section['rights'] as $item) {
                    if (
                        $id == _group_guests && !in_array($item['name'], $unregistered_useable, true)
                        || !User::hasPrivilege($item['name'])
                    ) {
                        continue;
                    }

                    $isText = isset($item['text']) && $item['text'];

                    $changeset[$item['name']] = $isText ? trim(Request::post($item['name'])) : Form::loadCheckbox($item['name']);
                }
            }
        }

        // extend
        Extend::call('admin.editgroup.save', ['changeset' => &$changeset]);

        // ulozeni
        DB::update(_user_group_table, 'id=' . $id, $changeset);

        // reload stranky
        $admin_redirect_to = 'index.php?p=users-editgroup&id=' . $id . '&saved';

        return;

    }

    /* -- nacist existujici ikony */

    if ($id != _group_guests) {
        $icons = "<div class='radio-group'>\n";
        $icons .= "<label><input" . Form::activateCheckbox($query['icon'] === '') . " type='radio' name='icon' value=''> " . _lang('global.undefined') . "</label>\n";

        $icon_dir = _root . 'images/groupicons';
        foreach (scandir($icon_dir) as $file) {
            if (
                $file === '.'
                || $file === '..'
                || !is_file($icon_dir . '/' . $file)
                || !ImageService::isImage($file)
            ) {
                continue;
            }

            $icons .= "<label><input" . Form::activateCheckbox($file === $query['icon']) . " type='radio' name='icon' value='" . _e($file) . "'> <img class='icon' src='" . $icon_dir . '/' . _e($file) . "' alt='" . _e($file) . "'></label>\n";
        }
        $icons .= "<div class='cleaner'></div></div>\n";
    }

    /* ---  vystup  --- */
    $output .= "
  <p class='bborder'>" . _lang('admin.users.groups.editp') . "</p>
  " . (isset($_GET['saved']) ? Message::ok(_lang('global.saved')) : '') . "
  " . ($systemitem ? Admin::note(_lang('admin.users.groups.specialgroup.editnotice')) : '') . "
  <form action='index.php?p=users-editgroup&amp;id=" . $id . "' method='post'>
  <table>

  <tr>
  <th>" . _lang('global.name') . "</th>
  <td><input type='text' name='title' class='inputmedium' value='" . $query['title'] . "' maxlength='128'></td>
  </tr>

  <tr>
  <th>" . _lang('global.descr') . "</th>
  <td><input type='text' name='descr' class='inputmedium' value='" . $query['descr'] . "' maxlength='255'></td>
  </tr>

  <tr>
  <th>" . _lang('admin.users.groups.level') . "</th>
  <td><input type='number' min='0' max='" . min(_priv_level -1, _priv_max_assignable_level) . "' name='level' class='inputmedium' value='" . $query['level'] . "'" . Form::disableInputUnless(!$systemitem) . "></td>
  </tr>

  " . (($id != _group_guests) ? "
  <tr><th><dfn title='" . _lang('admin.users.groups.icon.help', ['%dir%' => $icon_dir]) . "'>" . _lang('admin.users.groups.icon') . "</dfn></th><td>" . $icons . "</td></tr>
  <tr><th>" . _lang('admin.users.groups.color') . "</th><td><input type='text' name='color' class='inputsmall' value='" . $query['color'] . "' maxlength='16'> <input type='color' value='" . Admin::formatHtmlColor($query['color']) . "' onchange='this.form.elements.color.value=this.value'></td></tr>
  <tr><th>" . _lang('admin.users.groups.reglist') . "</th><td><input type='checkbox' name='reglist' value='1'" . Form::activateCheckbox($query['reglist']) . "></td></tr>
  " : '') . "

  <tr>
  <th>" . _lang('admin.users.groups.blocked') . "</th>
  <td><input type='checkbox' name='blocked' value='1'" . Form::activateCheckbox($query['blocked']) . Form::disableInputUnless($id != _group_admin && $id != _group_guests) . "></td>
  </tr>

  </table>

  " . Message::ok(_lang('admin.users.groups.dangernotice'), true) . "
  " . $rights . "
  " . Extend::buffer('admin.editgroup.form') . "

  <input type='submit' class='button bigger' value='" . _lang('global.save') . "' accesskey='s'> <small>" . _lang('admin.content.form.thisid') . " " . $id . "</small>

  " . Xsrf::getInput() . "</form>
  ";

} else {
    if ($levelconflict == false) {
        $output .= Message::error(_lang('global.badinput'));
    } else {
        $output .= Message::error(_lang('global.disallowed'));
    }
}