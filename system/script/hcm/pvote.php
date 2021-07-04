<?php

use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\IpLog;
use Sunlight\User;
use Sunlight\Util\Response;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

require '../../bootstrap.php';
Core::init('../../../');

/* ---  hlasovani  --- */

// nacteni promennych
if (isset($_POST['pid'], $_POST['option']) && Xsrf::check()) {
    $pid = (int) Request::post('pid');
    $option = (int) Request::post('option');

    // ulozeni hlasu
    $query = DB::queryRow("SELECT locked,answers,votes FROM " . _poll_table . " WHERE id=" . $pid);
    if ($query !== false) {
        $answers = explode("#", $query['answers']);
        $votes = explode("-", $query['votes']);
        if (User::hasPrivilege('pollvote') && $query['locked'] == 0 && IpLog::check(_iplog_poll_vote, $pid) && isset($votes[$option])) {
            ++$votes[$option];
            $votes = implode("-", $votes);
            DB::update(_poll_table, 'id=' . $pid, ['votes' => $votes]);
            IpLog::update(_iplog_poll_vote, $pid);
            Extend::call('poll.voted', ['id' => $pid, 'option' => $option]);
        }
    }

}

// presmerovani
Response::redirectBack();
