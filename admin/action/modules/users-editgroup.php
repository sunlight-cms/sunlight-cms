<?php

use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\Util\Math;

defined('_root') or exit;

/* ---  priprava promennych  --- */

$levelconflict = false;
$sysgroups_array = array(_group_admin, _group_guests /*,_group_registered is not necessary*/ );
$unregistered_useable = array('postcomments', 'artrate', 'pollvote');

// id
$continue = false;
if (isset($_GET['id'])) {
    $id = (int) \Sunlight\Util\Request::get('id');
    $query = DB::queryRow("SELECT * FROM " . _groups_table . " WHERE id=" . $id);
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
    $rights_array = array(
        array(
            'title' => _lang('admin.users.groups.commonrights'),
            'rights' => array(
                array('name' => 'changeusername'),
                array('name' => 'selfremove'),
                array('name' => 'artrate'),
                array('name' => 'pollvote'),
            ),
        ),
        array(
            'title' => _lang('admin.users.groups.postrights'),
            'rights' => array(
                array('name' => 'postcomments'),
                array('name' => 'locktopics'),
                array('name' => 'stickytopics'),
                array('name' => 'movetopics'),
                array('name' => 'adminposts'),
                array('name' => 'unlimitedpostaccess'),
            ),
        ),
        array(
            'title' => _lang('admin.users.groups.filerights'),
            'rights' => array(
                array('name' => 'fileaccess'),
                array('name' => 'fileglobalaccess'),
                array('name' => 'fileadminaccess', 'dangerous' => true),
            ),
        ),
        array(
            'title' => _lang('admin.users.groups.adminrights'),
            'rights' => array(
                array('name' => 'administration'),
                array('name' => 'adminusers'),
                array('name' => 'admingroups'),
                array('name' => 'adminplugins', 'dangerous' => true),
                array('name' => 'adminsettings', 'dangerous' => true),
                array('name' => 'adminbackup', 'dangerous' => true),
            ),
        ),
        array(
            'title' => _lang('admin.users.groups.adminotherrights'),
            'rights' => array(
                array('name' => 'adminother'),
                array('name' => 'adminmassemail'),
            ),
        ),
        array(
            'title' => _lang('admin.users.groups.adminhcmrights'),
            'rights' => array(
                array('name' => 'adminhcm', 'text' => true, 'dangerous' => true),
                array('name' => 'adminhcmphp', 'dangerous' => true),
            ),
        ),
        array(
            'title' => _lang('admin.users.groups.admincontentrights'),
            'rights' => array(
                array('name' => 'admincontent'),
                array('name' => 'adminroot'),
                array('name' => 'adminsection'),
                array('name' => 'admincategory'),
                array('name' => 'adminbook'),
                array('name' => 'adminseparator'),
                array('name' => 'admingallery'),
                array('name' => 'adminlink'),
                array('name' => 'admingroup'),
                array('name' => 'adminforum'),
                array('name' => 'adminpluginpage'),
            ),
        ),
        array(
            'title' => _lang('admin.users.groups.admincontentarticlerights'),
            'rights' => array(
                array('name' => 'adminart'),
                array('name' => 'adminallart'),
                array('name' => 'adminchangeartauthor'),
                array('name' => 'adminconfirm'),
                array('name' => 'adminautoconfirm'),
            ),
        ),
        array(
            'title' => _lang('admin.users.groups.admincontentotherrights'),
            'rights' => array(
                array('name' => 'adminpoll'),
                array('name' => 'adminpollall'),
                array('name' => 'adminsbox'),
                array('name' => 'adminbox'),
            ),
        ),
    );

    Extend::call('admin.editgroup.rights', array(
        'rights' => &$rights_array,
        'unregistered_rights' => &$unregistered_useable,
    ));

    $rights = "";
    foreach ($rights_array as $section) {
        $rights .= "<fieldset><legend>" . $section['title'] . "</legend><table>\n";
        foreach ($section['rights'] as $item) {
            if (
                $id == _group_admin
                || $id == _group_guests && !in_array($item['name'], $unregistered_useable, true)
                || !\Sunlight\User::hasPrivilege($item['name'])
            ) {
                $disabled = true;
            } else {
                $disabled = false;
            }

            $isText = isset($item['text']) && $item['text'];

            $rights .= "<tr>
    <th" . (isset($item['dangerous']) && $item['dangerous'] ? ' class="important"' : '') . ">
        <label for='setting_" . $item['name'] . "'>" . (isset($item['label']) ? $item['label'] : _lang('admin.users.groups.' . $item['name'])) . "</label>
    </th>
    <td>
        <label>
            <input type='" . ($isText ? 'text' : 'checkbox') . "' id='setting_" . $item['name'] . "' name='" . $item['name'] . "'" . ($isText ? " value='" . _e($query[$item['name']]) . "'" : " value='1'" . \Sunlight\Util\Form::activateCheckbox($query[$item['name']])) . \Sunlight\Util\Form::disableInputUnless(!$disabled) . ">
            " . (isset($item['help']) ? $item['help'] : _lang('admin.users.groups.' . $item['name'] . '.help')) . "
        </label>
    </td>
</tr>\n";
        }

        $rights .= "</table></fieldset>\n";
    }

    /* ---  ulozeni  --- */
    if (!empty($_POST)) {

        $changeset = array();

        // zakladni atributy
        $changeset['title'] = \Sunlight\Util\Html::cut(_e(trim(\Sunlight\Util\Request::post('title'))), 128);
        if ($changeset['title'] == "") {
            $changeset['title'] = _lang('global.novalue');
        }
        $changeset['descr'] = \Sunlight\Util\Html::cut(_e(trim(\Sunlight\Util\Request::post('descr'))), 255);
        if ($id != _group_guests) {
            $changeset['icon'] = \Sunlight\Util\Html::cut(_e(trim(\Sunlight\Util\Request::post('icon'))), 16);
        }
        $changeset['color'] = \Sunlight\Admin\Admin::formatHtmlColor(\Sunlight\Util\Request::post('color', ''), false, '');
        if ($id > _group_guests) {
            $changeset['blocked'] = \Sunlight\Util\Form::loadCheckbox('blocked');
        }
        if ($id != _group_guests) {
            $changeset['reglist'] = \Sunlight\Util\Form::loadCheckbox('reglist');
        }

        // uroven, blokovani
        if ($id > _group_guests) {
            $changeset['level'] = Math::range((int) \Sunlight\Util\Request::post('level'), 0, min(_priv_level, _priv_max_assignable_level));
        }

        // prava
        if ($id != _group_admin) {
            foreach ($rights_array as $section) {
                foreach ($section['rights'] as $item) {
                    if (
                        $id == _group_guests && !in_array($item['name'], $unregistered_useable, true)
                        || !\Sunlight\User::hasPrivilege($item['name'])
                    ) {
                        continue;
                    }

                    $isText = isset($item['text']) && $item['text'];

                    $changeset[$item['name']] = $isText ? trim(\Sunlight\Util\Request::post($item['name'])) : \Sunlight\Util\Form::loadCheckbox($item['name']);
                }
            }
        }

        // extend
        Extend::call('admin.editgroup.save', array('changeset' => &$changeset));

        // ulozeni
        DB::update(_groups_table, 'id=' . $id, $changeset);

        // reload stranky
        $admin_redirect_to = 'index.php?p=users-editgroup&id=' . $id . '&saved';

        return;

    }

    /* -- nacist existujici ikony */

    if ($id != _group_guests) {
        $icons = "<div class='radio-group'>\n";
        $icons .= "<label><input" . \Sunlight\Util\Form::activateCheckbox($query['icon'] === '') . " type='radio' name='icon' value=''> " . _lang('global.undefined') . "</label>\n";

        $icon_dir = _root . 'images/groupicons';
        foreach (scandir($icon_dir) as $file) {
            if (
                $file === '.'
                || $file === '..'
                || !is_file($icon_dir . '/' . $file)
                || !in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), Core::$imageExt, true)
            ) {
                continue;
            }

            $icons .= "<label><input" . \Sunlight\Util\Form::activateCheckbox($file === $query['icon']) . " type='radio' name='icon' value='" . _e($file) . "'> <img class='icon' src='" . $icon_dir . '/' . _e($file) . "' alt='" . _e($file) . "'></label>\n";
        }
        $icons .= "<div class='cleaner'></div></div>\n";
    }

    /* ---  vystup  --- */
    $output .= "
  <p class='bborder'>" . _lang('admin.users.groups.editp') . "</p>
  " . (isset($_GET['saved']) ? \Sunlight\Message::render(_msg_ok, _lang('global.saved')) : '') . "
  " . ($systemitem ? \Sunlight\Admin\Admin::note(_lang('admin.users.groups.specialgroup.editnotice')) : '') . "
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
  <td><input type='number' min='0' max='" . min(_priv_level -1, _priv_max_assignable_level) . "' name='level' class='inputmedium' value='" . $query['level'] . "'" . \Sunlight\Util\Form::disableInputUnless(!$systemitem) . "></td>
  </tr>

  " . (($id != _group_guests) ? "
  <tr><th><dfn title='" . _lang('admin.users.groups.icon.help', array('%dir%' => $icon_dir)) . "'>" . _lang('admin.users.groups.icon') . "</dfn></th><td>" . $icons . "</td></tr>
  <tr><th>" . _lang('admin.users.groups.color') . "</th><td><input type='text' name='color' class='inputsmall' value='" . $query['color'] . "' maxlength='16'> <input type='color' value='" . \Sunlight\Admin\Admin::formatHtmlColor($query['color']) . "' onchange='this.form.elements.color.value=this.value'></td></tr>
  <tr><th>" . _lang('admin.users.groups.reglist') . "</th><td><input type='checkbox' name='reglist' value='1'" . \Sunlight\Util\Form::activateCheckbox($query['reglist']) . "></td></tr>
  " : '') . "

  <tr>
  <th>" . _lang('admin.users.groups.blocked') . "</th>
  <td><input type='checkbox' name='blocked' value='1'" . \Sunlight\Util\Form::activateCheckbox($query['blocked']) . \Sunlight\Util\Form::disableInputUnless($id != _group_admin && $id != _group_guests) . "></td>
  </tr>

  </table>

  " . \Sunlight\Message::render(_msg_ok, _lang('admin.users.groups.dangernotice')) . "
  " . $rights . "
  " . Extend::buffer('admin.editgroup.form') . "

  <input type='submit' class='button bigger' value='" . _lang('global.save') . "' accesskey='s'> <small>" . _lang('admin.content.form.thisid') . " " . $id . "</small>

  " . \Sunlight\Xsrf::getInput() . "</form>
  ";

} else {
    if ($levelconflict == false) {
        $output .= \Sunlight\Message::render(_msg_err, _lang('global.badinput'));
    } else {
        $output .= \Sunlight\Message::render(_msg_err, _lang('global.disallowed'));
    }
}