<?php

use Sunlight\Admin\Admin;
use Sunlight\Database\Database as DB;
use Sunlight\Hcm;
use Sunlight\IpLog;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Arr;
use Sunlight\Util\Form;
use Sunlight\Util\Html;
use Sunlight\Util\Request;
use Sunlight\Util\StringHelper;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

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

// save or create
if (isset($_POST['question'])) {
    $question = Html::cut(_e(trim(Request::post('question', ''))), 255);
    $query['question'] = $question;

    // answers
    $answers = explode("\n", Request::post('answers'));
    $answers_new = [];

    foreach ($answers as $answer) {
        $answers_new[] = _e(trim($answer));
    }

    $answers = Arr::removeValue($answers_new, '');
    $answers_count = count($answers);
    $answers = implode("\n", $answers);
    $query['answers'] = Html::cut($answers, DB::MAX_TEXT_LENGTH);

    if (User::hasPrivilege('adminpollall')) {
        $author = (int) Request::post('author');
    } else {
        $author = User::getId();
    }

    $locked = Form::loadCheckbox('locked');
    $reset = Form::loadCheckbox('reset');

    // check variables
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

    if (
        User::hasPrivilege('adminpollall')
        && DB::result(DB::query(
            'SELECT COUNT(*) FROM ' . DB::table('user')
            . ' WHERE id=' . $author
            . ' AND ('
                . 'id=' . User::getId()
                . ' OR (SELECT level FROM ' . DB::table('user_group') . ' WHERE id=' . DB::table('user') . '.group_id)<' . User::getLevel()
            . ')'
        )) == 0
    ) {
        $errors[] = _lang('admin.content.articles.edit.error3');
    }

    // save
    if (empty($errors)) {
        if (!$new) {
            DB::update('poll', 'id=' . $id, [
                'question' => $question,
                'answers' => $answers,
                'author' => $author,
                'locked' => $locked
            ]);

            // normalize votes
            if (!$reset) {
                $votes = explode('-', $query['votes']);
                $votes_count = count($votes);
                $newvotes = '';

                // too many items
                if ($votes_count > $answers_count) {
                    for ($i = 0; $i < $votes_count - $answers_count; $i++) {
                        array_pop($votes);
                    }

                    $newvotes = implode('-', $votes);
                }

                // not enough items
                if ($votes_count < $answers_count) {
                    $newvotes = implode('-', $votes) . str_repeat('-0', $answers_count - $votes_count);
                }

                // save normalized votes
                if ($newvotes != '') {
                    DB::update('poll', 'id=' . $id, ['votes' => $newvotes]);
                }
            }

            // reset
            if ($reset) {
                DB::update('poll', 'id=' . $id, ['votes' => trim(str_repeat('0-', $answers_count), '-')]);
                DB::delete('iplog', 'type=' . IpLog::POLL_VOTE . ' AND var=' . $id);
            }

            // redirect
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

// output
if ($continue) {
    // author select
    if (User::hasPrivilege('adminpollall')) {
        $author_select = '
    <tr>
    <th>' . _lang('article.author') . '</th>
    <td>' . Admin::userSelect('author', ['selected' => $query['author'], 'group_cond' => 'adminpoll=1', 'class' => 'selectmedium']) . '</td>
    </tr>
    ';
    } else {
        $author_select = '';
    }

    // message
    if (isset($_GET['saved'])) {
        $message = Message::ok(_lang('global.saved'));
    }

    if (isset($_GET['created'])) {
        $message = Message::ok(_lang('global.created'));
    }

    $output .= $message . '
  ' . Form::start('poll-edit', ['action' => Router::admin('content-polls-edit', $actionbonus)]) . '
  <table class="formtable">

  <tr>
  <th>' . _lang('admin.content.form.question') . '</th>
  <td>' . Form::input('text', 'question', $query['question'], ['class' => 'inputmedium', 'maxlength' => 255], false) . '</td>
  </tr>

  ' . $author_select . '

  <tr class="valign-top">
  <th>' . _lang('admin.content.form.answers') . '</th>
  <td>' . Form::textarea('answers', $query['answers'], ['class' => 'areamedium', 'rows' => 25, 'cols' => 94], false) . '</td>
  </tr>

  ' . (!$new ? '<tr>
  <th>' . _lang('admin.content.form.hcm') . '</th>
  <td>' . Form::input('text', 'hcm', Hcm::compose("poll,{$id}"), ['class' => 'inputmedium', 'readonly' => true, 'onclick' => 'this.select()']) . '</td>
  </tr>' : '') . '

  <tr>
  <th>' . _lang('admin.content.form.settings') . '</th>
  <td>
  <label>' . Form::input('checkbox', 'locked', '1', ['checked' => (bool) $query['locked']]) . ' ' . _lang('admin.content.form.locked') . '</label> 
  ' . (!$new ? '<label>' . Form::input('checkbox', 'reset', '1') . ' ' . _lang('admin.content.polls.reset') . '</label>' : '') . '
  </td>
  </tr>

  <tr><td></td>
  <td>
    ' . Form::input('submit', null, $submitcaption, ['class' => 'button bigger', 'accesskey' => 's'])
    . (!$new
            ? ' <a class="button bigger" href="' . _e(Xsrf::addToUrl(Router::admin('content-polls', ['query' => ['del' => $id]]))) . '" onclick="return Sunlight.confirm();">'
                . '<img src="' . _e(Router::path('admin/public/images/icons/delete.png')) . '" class="icon" alt="del">'
                . StringHelper::ucfirst(_lang('global.delete'))
                . '</a>'
            . '<span class="customsettings"><small>' . _lang('admin.content.form.thisid') . ' ' . $id . '</small></span>'
            : '')
. '</td>
  </tr>

  </table>
  ' . Form::end('poll-edit') . '
  ';
} else {
    $output .= Message::error(_lang('global.badinput'));
}
