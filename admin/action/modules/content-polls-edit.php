<?php

use Sunlight\Admin\Admin;
use Sunlight\Database\Database as DB;
use Sunlight\IpLog;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Arr;
use Sunlight\Util\Form;
use Sunlight\Util\Html;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

/* ---  priprava promennych  --- */

$continue = false;
$message = '';
if (isset($_GET['id'])) {
    $id = (int) Request::get('id');
    $query = DB::queryRow('SELECT p.* FROM ' . DB::table('poll') . ' p WHERE p.id=' . $id . Admin::pollAccess());
    if ($query !== false) {
        $new = false;
        $actionbonus = ['query' => ['id' => $id]];
        $submitcaption = _lang('global.save');
        $continue = true;
    }
} else {
    $id = -1;
    $query = ['author' => User::getId(), 'question' => '', 'answers' => '', 'locked' => 0];
    $new = true;
    $actionbonus = null;
    $submitcaption = _lang('global.create');
    $continue = true;
}

/* ---  ulozeni / vytvoreni  --- */

if (isset($_POST['question'])) {
    // nacteni promennych
    $question = Html::cut(_e(trim(Request::post('question', ''))), 255);
    $query['question'] = $question;

    // odpovedi
    $answers = explode("\n", Request::post('answers'));
    $answers_new = [];
    foreach ($answers as $answer) {
        $answers_new[] = _e(trim($answer));
    }
    $answers = Arr::removeValue($answers_new, '');
    $answers_count = count($answers);
    $answers = implode("\n", $answers);
    $query['answers'] = $answers;

    if (User::hasPrivilege('adminpollall')) {
        $author = (int) Request::post('author');
    } else {
        $author = User::getId();
    }
    $locked = Form::loadCheckbox('locked');
    $reset = Form::loadCheckbox('reset');

    // kontrola promennych
    $errors = [];
    if ($question == '') {
        $errors[] = _lang('admin.content.polls.edit.error1');
    }
    if ($answers_count == 0) {
        $errors[] = _lang('admin.content.polls.edit.error2');
    }
    if ($answers_count > 20) {
        $errors[] = _lang('admin.content.polls.edit.error3');
    }
    if (User::hasPrivilege('adminpollall') && DB::result(DB::query('SELECT COUNT(*) FROM ' . DB::table('user') . ' WHERE id=' . $author . ' AND (id=' . User::getId() . ' OR (SELECT level FROM ' . DB::table('user_group') . ' WHERE id=' . DB::table('user') . '.group_id)<' . User::getLevel() . ')')) == 0) {
        $errors[] = _lang('admin.content.articles.edit.error3');
    }

    // ulozeni
    if (count($errors) == 0) {

        if (!$new) {
            DB::update('poll', 'id=' . $id, [
                'question' => $question,
                'answers' => $answers,
                'author' => $author,
                'locked' => $locked
            ]);

            // korekce seznamu hlasu
            if (!$reset) {
                $votes = explode('-', $query['votes']);
                $votes_count = count($votes);
                $newvotes = '';

                // prilis mnoho polozek
                if ($votes_count > $answers_count) {
                    for ($i = 0; $i < $votes_count - $answers_count; $i++) {
                        array_pop($votes);
                    }
                    $newvotes = implode('-', $votes);
                }

                // malo polozek
                if ($votes_count < $answers_count) {
                    $newvotes = implode('-', $votes) . str_repeat('-0', $answers_count - $votes_count);
                }

                // ulozeni korekci
                if ($newvotes != '') {
                    DB::update('poll', 'id=' . $id, ['votes' => $newvotes]);
                }

            }

            // vynulovani
            if ($reset) {
                DB::update('poll', 'id=' . $id, ['votes' => trim(str_repeat('0-', $answers_count), '-')]);
                DB::delete('iplog', 'type=' . IpLog::POLL_VOTE . ' AND var=' . $id);
            }

            // presmerovani
            $_admin->redirect(Router::admin('content-polls-edit', ['query' => ['id' => $id, 'saved' => 1]]));

            return;

        }

        $newid = DB::insert('poll', [
            'author' => $author,
            'question' => $question,
            'answers' => $answers,
            'locked' => $locked,
            'votes' => trim(str_repeat('0-', $answers_count), '-')
        ], true);
        $_admin->redirect(Router::admin('content-polls-edit', ['query' => ['id' => $newid, 'created' => 1]]));

        return;

    }

    $message = Message::list($errors);
}

/* ---  vystup  --- */

if ($continue) {

    // vyber autora
    if (User::hasPrivilege('adminpollall')) {
        $author_select = '
    <tr>
    <th>' . _lang('article.author') . '</th>
    <td>' . Admin::userSelect('author', $query['author'], 'adminpoll=1', 'selectmedium') . '</td></tr>
    ';
    } else {
        $author_select = '';
    }

    // zprava
    if (isset($_GET['saved'])) {
        $message = Message::ok(_lang('global.saved'));
    }
    if (isset($_GET['created'])) {
        $message = Message::ok(_lang('global.created'));
    }

    $output .= $message . "
  <form action='" . _e(Router::admin('content-polls-edit', $actionbonus)) . "' method='post'>
  <table class='formtable'>

  <tr>
  <th>" . _lang('admin.content.form.question') . "</th>
  <td><input type='text' name='question' class='inputmedium' value='" . $query['question'] . "' maxlength='255'></td>
  </tr>

  " . $author_select . "

  <tr class='valign-top'>
  <th>" . _lang('admin.content.form.answers') . "</th>
  <td><textarea name='answers' rows='25' cols='94' class='areamedium'>" . $query['answers'] . '</textarea></td>
  </tr>

  ' . (!$new ? '<tr>
  <th>' . _lang('admin.content.form.hcm') . "</th>
  <td><input type='text' name='hcm' value='[hcm]poll," . $id . "[/hcm]' readonly='readonly' onclick='this.select();' class='inputmedium'></td>
  </tr>" : '') . '

  <tr>
  <th>' . _lang('admin.content.form.settings') . "</th>
  <td>
  <label><input type='checkbox' name='locked' value='1'" . Form::activateCheckbox($query['locked']) . '> ' . _lang('admin.content.form.locked') . '</label> 
  ' . (!$new ? "<label><input type='checkbox' name='reset' value='1'> " . _lang('admin.content.polls.reset') . '</label>' : '') . "
  </td>
  </tr>

  <tr><td></td>
  <td><input type='submit' value='" . $submitcaption . "' accesskey='s'>" . (!$new ? ' <small>' . _lang('admin.content.form.thisid') . ' ' . $id . "</small> <span class='customsettings'><a class='button' href='" . _e(Xsrf::addToUrl(Router::admin('content-polls', ['query' => ['del' => $id]]))) . "' onclick='return Sunlight.confirm();'><img src='" . _e(Router::path('admin/images/icons/delete.png')) . "' class='icon' alt='del'> " . _lang('global.delete') . '</a>' : '') . '</span></td>
  </tr>

  </table>
  ' . Xsrf::getInput() . '</form>
  ';

} else {
    $output .= Message::error(_lang('global.badinput'));
}
