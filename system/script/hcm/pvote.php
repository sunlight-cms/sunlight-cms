<?php

use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;

require '../../bootstrap.php';
Core::init('../../../');

/* ---  hlasovani  --- */

// nacteni promennych
if (isset($_POST['pid']) && isset($_POST['option']) && \Sunlight\Xsrf::check()) {
    $pid = (int) \Sunlight\Util\Request::post('pid');
    $option = (int) \Sunlight\Util\Request::post('option');

    // ulozeni hlasu
    $query = DB::queryRow("SELECT locked,answers,votes FROM " . _polls_table . " WHERE id=" . $pid);
    if ($query !== false) {
        $answers = explode("#", $query['answers']);
        $votes = explode("-", $query['votes']);
        if (_priv_pollvote && $query['locked'] == 0 && \Sunlight\IpLog::check(_iplog_poll_vote, $pid) && isset($votes[$option])) {
            $votes[$option] += 1;
            $votes = implode("-", $votes);
            DB::update(_polls_table, 'id=' . $pid, array('votes' => $votes));
            \Sunlight\IpLog::update(_iplog_poll_vote, $pid);
            Extend::call('poll.voted', array('id' => $pid, 'option' => $option));
        }
    }

}

// presmerovani
\Sunlight\Response::redirectBack();
