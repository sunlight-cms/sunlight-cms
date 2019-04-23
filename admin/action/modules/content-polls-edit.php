<?php

use Sunlight\Admin\Admin;
use Sunlight\Database\Database as DB;
use Sunlight\Message;
use Sunlight\Util\Arr;
use Sunlight\Util\Form;
use Sunlight\Util\Html;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('_root') or exit;

/* ---  priprava promennych  --- */

$continue = false;
$message = "";
if (isset($_GET['id'])) {
    $id = (int) Request::get('id');
    $query = DB::queryRow("SELECT p.* FROM " . _poll_table . " p WHERE p.id=" . $id . Admin::pollAccess());
    if ($query !== false) {
        $new = false;
        $actionbonus = "&amp;id=" . $id;
        $submitcaption = _lang('global.save');
        $continue = true;
    }
} else {
    $id = -1;
    $query = array('author' => _user_id, 'question' => "", 'answers' => "", 'locked' => 0);
    $new = true;
    $actionbonus = "";
    $submitcaption = _lang('global.create');
    $continue = true;
}

/* ---  ulozeni / vytvoreni  --- */

if (isset($_POST['question'])) {

    // nacteni promennych
    $question = Html::cut(_e(trim(Request::post('question'))), 255);
    $query['question'] = $question;

    // odpovedi
    $answers = explode("\n", Request::post('answers'));
    $answers_new = array();
    foreach ($answers as $answer) {
        $answers_new[] = _e(trim($answer));
    }
    $answers = Arr::removeValue($answers_new, "");
    $answers_count = count($answers);
    $answers = implode("\n", $answers);
    $query['answers'] = $answers;

    if (_priv_adminpollall) {
        $author = (int) Request::post('author');
    } else {
        $author = _user_id;
    }
    $locked = Form::loadCheckbox("locked");
    $reset = Form::loadCheckbox("reset");

    // kontrola promennych
    $errors = array();
    if ($question == "") {
        $errors[] = _lang('admin.content.polls.edit.error1');
    }
    if ($answers_count == 0) {
        $errors[] = _lang('admin.content.polls.edit.error2');
    }
    if ($answers_count > 20) {
        $errors[] = _lang('admin.content.polls.edit.error3');
    }
    if (_priv_adminpollall && DB::result(DB::query("SELECT COUNT(*) FROM " . _user_table . " WHERE id=" . $author . " AND (id=" . _user_id . " OR (SELECT level FROM " . _user_group_table . " WHERE id=" . _user_table . ".group_id)<" . _priv_level . ")"), 0) == 0) {
        $errors[] = _lang('admin.content.articles.edit.error3');
    }

    // ulozeni
    if (count($errors) == 0) {

        if (!$new) {
            DB::update(_poll_table, 'id=' . $id, array(
                'question' => $question,
                'answers' => $answers,
                'author' => $author,
                'locked' => $locked
            ));

            // korekce seznamu hlasu
            if (!$reset) {
                $votes = explode("-", $query['votes']);
                $votes_count = count($votes);
                $newvotes = "";

                // prilis mnoho polozek
                if ($votes_count > $answers_count) {
                    for ($i = 0; $i < $votes_count - $answers_count; $i++) {
                        array_pop($votes);
                    }
                    $newvotes = implode("-", $votes);
                }

                // malo polozek
                if ($votes_count < $answers_count) {
                    $newvotes = implode("-", $votes) . str_repeat("-0", $answers_count - $votes_count);
                }

                // ulozeni korekci
                if ($newvotes != "") {
                    DB::update(_poll_table, 'id=' . $id, array('votes' => $newvotes));
                }

            }

            // vynulovani
            if ($reset) {
                DB::update(_poll_table, 'id=' . $id, array('votes' => trim(str_repeat("0-", $answers_count), "-")));
                DB::delete(_iplog_table, 'type=' . _iplog_poll_vote . ' AND var=' . $id);
            }

            // presmerovani
            $admin_redirect_to = 'index.php?p=content-polls-edit&id=' . $id . '&saved';

            return;

        } else {
            $newid = DB::insert(_poll_table, array(
                'author' => $author,
                'question' => $question,
                'answers' => $answers,
                'locked' => $locked,
                'votes' => trim(str_repeat("0-", $answers_count), "-")
            ), true);
            $admin_redirect_to = 'index.php?p=content-polls-edit&id=' . $newid . '&created';

            return;
        }

    } else {
        $message = Message::warning(Message::renderList($errors, 'errors'), true);
    }

}

/* ---  vystup  --- */

if ($continue) {

    // vyber autora
    if (_priv_adminpollall) {
        $author_select = "
    <tr>
    <th>" . _lang('article.author') . "</th>
    <td>" . Admin::userSelect("author", $query['author'], "adminpoll=1", "selectmedium") . "</td></tr>
    ";
    } else {
        $author_select = "";
    }

    // zprava
    if (isset($_GET['saved'])) {
        $message = Message::ok(_lang('global.saved'));
    }
    if (isset($_GET['created'])) {
        $message = Message::ok(_lang('global.created'));
    }

    $output .= $message . "
  <form action='index.php?p=content-polls-edit" . $actionbonus . "' method='post'>
  <table class='formtable'>

  <tr>
  <th>" . _lang('admin.content.form.question') . "</th>
  <td><input type='text' name='question' class='inputmedium' value='" . $query['question'] . "' maxlength='255'></td>
  </tr>

  " . $author_select . "

  <tr class='valign-top'>
  <th>" . _lang('admin.content.form.answers') . "</th>
  <td><textarea name='answers' rows='25' cols='94' class='areamedium'>" . $query['answers'] . "</textarea></td>
  </tr>

  " . (!$new ? "<tr>
  <th>" . _lang('admin.content.form.hcm') . "</th>
  <td><input type='text' name='hcm' value='[hcm]poll," . $id . "[/hcm]' readonly='readonly' onclick='this.select();' class='inputmedium'></td>
  </tr>" : '') . "

  <tr>
  <th>" . _lang('admin.content.form.settings') . "</th>
  <td>
  <label><input type='checkbox' name='locked' value='1'" . Form::activateCheckbox($query['locked']) . "> " . _lang('admin.content.form.locked') . "</label> 
  " . (!$new ? "<label><input type='checkbox' name='reset' value='1'> " . _lang('admin.content.polls.reset') . "</label>" : '') . "
  </td>
  </tr>

  <tr><td></td>
  <td><input type='submit' value='" . $submitcaption . "' accesskey='s'>" . (!$new ? " <small>" . _lang('admin.content.form.thisid') . " " . $id . "</small> <span class='customsettings'><a class='button' href='" . _e(Xsrf::addToUrl("index.php?p=content-polls&del=" . $id)) . "' onclick='return Sunlight.confirm();'><img src='images/icons/delete.png' class='icon' alt='del'> " . _lang('global.delete') . "</a>" : '') . "</span></td>
  </tr>

  </table>
  " . Xsrf::getInput() . "</form>
  ";

} else {
    $output .= Message::error(_lang('global.badinput'));
}
