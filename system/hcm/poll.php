<?php

if (!defined('_root')) {
    exit;
}

function _HCM_poll($id = null)
{
    // nacteni promennych
    $id = (int) $id;

    // nacteni dat ankety
    $vpolldata = DB::queryRow("SELECT * FROM " . _polls_table . " WHERE id=" . DB::val($id));
    if ($vpolldata !== false) {
        $rcontinue = true;
    } else {
        $rcontinue = false;
    }

    // sestaveni kodu
    if ($rcontinue) {

        // odpovedi
        $ranswers = explode("\n", $vpolldata['answers']);
        $rvotes = explode("-", $vpolldata['votes']);
        $rvotes_sum = array_sum($rvotes);
        if (_priv_pollvote == 1 && $vpolldata['locked'] != 1 && _iplogCheck(_iplog_poll_vote, $id)) {
            $rallowvote = true;
        } else {
            $rallowvote = false;
        }

        if ($rallowvote) {
            $ranswers_code = "<form action='" . _link('system/script/hcm/pvote.php?_return=' . rawurlencode($GLOBALS['_index']['url']) . "#hcm_poll_" . Sunlight\Core::$hcmUid) . "' method='post'>\n<input type='hidden' name='pid' value='" . $vpolldata['id'] . "'>";
        } else {
            $ranswers_code = "";
        }

        $ranswer_id = 0;
        foreach ($ranswers as $item) {
            if ($rvotes_sum != 0 && $rvotes[$ranswer_id] != 0) {
                $rpercent = round($rvotes[$ranswer_id] / $rvotes_sum * 100);
            } else {
                $rpercent = 0;
            }
            if ($rallowvote) {
                $item = "<label><input type='radio' name='option' value='" . $ranswer_id . "'> " . $item . " [" . $rvotes[$ranswer_id] . "/" . $rpercent . "%]</label>";
            } else {
                $item .= " [" . $rvotes[$ranswer_id] . "/" . $rpercent . "%]";
            }
            $ranswers_code .= "<div class='poll-answer'>" . $item . "<div style='width:" . $rpercent . "%;'></div></div>\n";
            ++$ranswer_id;
        }

        $ranswers_code .= "<div class='poll-answer'>";
        if ($rallowvote) {
            $ranswers_code .= "<input type='submit' value='" . $GLOBALS['_lang']['hcm.poll.vote'] . "' class='votebutton'>";
        }
        $ranswers_code .= $GLOBALS['_lang']['hcm.poll.votes'] . ": " . $rvotes_sum . "</div>";
        if ($rallowvote) {
            $ranswers_code .= _xsrfProtect() . "</form>\n";
        }

        return "
<div id='hcm_poll_" . Sunlight\Core::$hcmUid . "' class='poll'>
<div class='poll-content'>

<div class='poll-question'>
" . $vpolldata['question'] . "
" . (($vpolldata['locked'] == 1) ? "<div>(" . $GLOBALS['_lang']['hcm.poll.locked'] . ")</div>" : '') . "
</div>

" . $ranswers_code . "

</div>
</div>\n
";

    }
}
