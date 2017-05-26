<?php

require '../../bootstrap.php';
Sunlight\Core::init('../../../');

/* ---  hlasovani  --- */

// nacteni promennych
if (isset($_POST['pid']) && isset($_POST['option']) && _xsrfCheck()) {
    $pid = (int) _post('pid');
    $option = (int) _post('option');

    // ulozeni hlasu
    $query = DB::queryRow("SELECT locked,answers,votes FROM " . _polls_table . " WHERE id=" . $pid);
    if ($query !== false) {
        $answers = explode("#", $query['answers']);
        $votes = explode("-", $query['votes']);
        if (_priv_pollvote && $query['locked'] == 0 && _iplogCheck(_iplog_poll_vote, $pid) && isset($votes[$option])) {
            $votes[$option] += 1;
            $votes = implode("-", $votes);
            DB::update(_polls_table, 'id=' . $pid, array('votes' => $votes));
            _iplogUpdate(_iplog_poll_vote, $pid);
            Sunlight\Extend::call('poll.voted', array('id' => $pid, 'option' => $option));
        }
    }

}

// presmerovani
_returnHeader();
