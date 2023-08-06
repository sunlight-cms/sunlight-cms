<?php

use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\IpLog;
use Sunlight\User;
use Sunlight\Util\Response;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

require __DIR__ . '/../../bootstrap.php';
Core::init('../../../');

if (isset($_POST['pid'], $_POST['option']) && Xsrf::check()) {
    $pid = (int) Request::post('pid');
    $option = (int) Request::post('option');

    $query = DB::queryRow('SELECT locked,answers,votes FROM ' . DB::table('poll') . ' WHERE id=' . $pid);

    if ($query !== false) {
        $answers = explode('#', $query['answers']);
        $votes = explode('-', $query['votes']);

        if (User::hasPrivilege('pollvote') && $query['locked'] == 0 && IpLog::check(IpLog::POLL_VOTE, $pid) && isset($votes[$option])) {
            ++$votes[$option];
            $votes = implode('-', $votes);
            DB::update('poll', 'id=' . $pid, ['votes' => $votes]);
            IpLog::update(IpLog::POLL_VOTE, $pid);
            Extend::call('poll.voted', ['id' => $pid, 'option' => $option]);
        }
    }
}

// redirect back
Response::redirectBack();
