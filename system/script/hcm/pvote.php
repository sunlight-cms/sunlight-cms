<?php

require '../../bootstrap.php';
Sunlight\Core::init('../../../');

/* ---  hlasovani  --- */

// nacteni promennych
if (isset($_POST['pid']) && isset($_POST['option']) && _xsrfCheck()) {
    $pid = (int) _post('pid');
    $option = (int) _post('option');

    // ulozeni hlasu
    $query = DB::query("SELECT locked,answers,votes FROM " . _polls_table . " WHERE id=" . $pid);
    if (DB::size($query) != 0) {
        $query = DB::row($query);
        $answers = explode("#", $query['answers']);
        $votes = explode("-", $query['votes']);
        if (_priv_pollvote && $query['locked'] == 0 && _iplogCheck(_iplog_poll_vote, $pid) && isset($votes[$option])) {
            $votes[$option] += 1;
            $votes = implode("-", $votes);
            DB::query("UPDATE " . _polls_table . " SET votes='" . $votes . "' WHERE id=" . $pid);
            _iplogUpdate(_iplog_poll_vote, $pid);
            Sunlight\Extend::call('poll.voted', array('id' => $pid, 'option' => $option));
        }
    }

}

// presmerovani
_returnHeader();
