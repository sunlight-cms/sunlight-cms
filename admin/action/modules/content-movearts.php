<?php

if (!defined('_root')) {
    exit;
}

/* ---  priprava promennych  --- */

$message = "";

/* ---  akce  --- */

if (isset($_POST['source'])) {

    // nacteni promennych
    $source = (int) _post('source');
    $target = (int) _post('target');
    $fullmove = _checkboxLoad("fullmove");

    // kontrola promennych
    $error_log = array();
    if (DB::result(DB::query("SELECT COUNT(*) FROM " . _root_table . " WHERE id=" . $source . " AND type=2"), 0) == 0) {
        $error_log[] = $_lang['admin.content.movearts.badsource'];
    }
    if (DB::result(DB::query("SELECT COUNT(*) FROM " . _root_table . " WHERE id=" . $target . " AND type=2"), 0) == 0) {
        $error_log[] = $_lang['admin.content.movearts.badtarget'];
    }
    if ($source == $target) {
        $error_log[] = $_lang['admin.content.movearts.samecats'];
    }

    // aplikace
    if (count($error_log) == 0) {

        if (!$fullmove) {
            $query = DB::query("SELECT id,home1,home2,home3 FROM " . _articles_table . " WHERE home1=" . $source . " OR home2=" . $source . " OR home3=" . $source);
            $counter = 0;
            while ($item = DB::row($query)) {
                if ($item['home1'] == $source) {
                    $homeid = 1;
                    $homecheck = array(2, 3);
                }
                if ($item['home2'] == $source) {
                    $homeid = 2;
                    $homecheck = array(1, 3);
                }
                if ($item['home3'] == $source) {
                    $homeid = 3;
                    $homecheck = array(1, 2);
                }
                DB::query("UPDATE " . _articles_table . " SET home" . $homeid . "=" . $target . " WHERE id=" . $item['id']);
                foreach ($homecheck as $hc) {
                    if ($item['home' . $hc] == $target) {
                        if ($hc != 1) {
                            DB::query("UPDATE " . _articles_table . " SET home" . $hc . "=-1 WHERE id=" . $item['id']);
                        } else {
                            DB::query("UPDATE " . _articles_table . " SET home" . $homeid . "=-1 WHERE id=" . $item['id']);
                        }
                    }
                }
                $counter++;
            }
        } else {
            DB::query("UPDATE " . _articles_table . " SET home1=" . $target . ",home2=-1,home3=-1 WHERE home1=" . $source . " OR home2=" . $source . " OR home3=" . $source);
            $counter = DB::affectedRows();
        }

        $message = _msg(_msg_ok, str_replace("*moved*", $counter, $_lang['admin.content.movearts.done']));
    } else {
        $message = _msg(_msg_warn, _msgList($error_log, 'errors'));
    }

}

/* ---  vystup  --- */

$output .= $message . "
<form class='cform' action='index.php?p=content-movearts' method='post'>"
    . $_lang['admin.content.movearts.text1']
    . " " . _adminRootSelect("source", array('type' => _page_category))
    . $_lang['admin.content.movearts.text2']
    . " " . _adminRootSelect("target", array('type' => _page_category))
    . " <input class='button' type='submit' value='" . $_lang['global.do'] . "'>
<br><br>
<label><input type='checkbox' name='fullmove' value='1'> " . $_lang['admin.content.movearts.fullmove'] . "</label>
" . _xsrfProtect() . "</form>
";
