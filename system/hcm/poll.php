<?php

use Sunlight\Database\Database as DB;
use Sunlight\Hcm;
use Sunlight\IpLog;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Xsrf;

return function ($id = 0) {
    Hcm::normalizeArgument($id, 'int');

    // fetch poll data
    $vpolldata = DB::queryRow('SELECT * FROM ' . DB::table('poll') . ' WHERE id=' . DB::val($id));

    if ($vpolldata !== false) {
        $rcontinue = true;
    } else {
        $rcontinue = false;
    }

    // render
    if ($rcontinue) {
        $ranswers = explode("\n", $vpolldata['answers']);
        $rvotes = explode('-', $vpolldata['votes']);
        $rvotes_sum = array_sum($rvotes);

        if (User::hasPrivilege('pollvote') == 1 && $vpolldata['locked'] != 1 && IpLog::check(IpLog::POLL_VOTE, $id)) {
            $rallowvote = true;
        } else {
            $rallowvote = false;
        }

        if ($rallowvote) {
            $ranswers_code = '<form'
                . ' action="' . _e(Router::path('system/script/hcm/pvote.php', ['query' => ['_return=' => $GLOBALS['_index']->url], 'fragment' => 'hcm_poll_' . Hcm::$uid])) . '"'
                . ' method="post"'
                . '>'
                . '<input type="hidden" name="pid" value="' . $vpolldata['id'] . '">';
        } else {
            $ranswers_code = '';
        }

        $ranswer_id = 0;

        foreach ($ranswers as $item) {
            if ($rvotes_sum != 0 && $rvotes[$ranswer_id] != 0) {
                $rpercent = round($rvotes[$ranswer_id] / $rvotes_sum * 100);
            } else {
                $rpercent = 0;
            }

            if ($rallowvote) {
                $item = '<label>'
                    . '<input type="radio" name="option" value="' . $ranswer_id . '"> '
                    . $item
                    . ' [' . $rvotes[$ranswer_id] . '/' . $rpercent . '%]'
                    . '</label>';
            } else {
                $item .= ' [' . $rvotes[$ranswer_id] . '/' . $rpercent . '%]';
            }

            $ranswers_code .= '<div class="poll-answer">' . $item . '<div style="width:' . $rpercent . "%;\"></div></div>\n";
            ++$ranswer_id;
        }

        $ranswers_code .= '<div class="poll-answer">';

        if ($rallowvote) {
            $ranswers_code .= '<input type="submit" value="' . _lang('hcm.poll.vote') . '" class="votebutton">';
        }

        $ranswers_code .= _lang('hcm.poll.votes') . ': ' . $rvotes_sum . '</div>';

        if ($rallowvote) {
            $ranswers_code .= Xsrf::getInput() . "</form>\n";
        }

        return '
<div id="hcm_poll_' . Hcm::$uid . '" class="poll">
<div class="poll-content">

<div class="poll-question">
' . $vpolldata['question'] . '
' . (($vpolldata['locked'] == 1) ? '<div>(' . _lang('hcm.poll.locked') . ')</div>' : '') . '
</div>

' . $ranswers_code . "

</div>
</div>\n
";
    }
};
