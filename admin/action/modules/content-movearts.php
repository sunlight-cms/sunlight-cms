<?php

use Sunlight\Admin\Admin;
use Sunlight\Database\Database as DB;
use Sunlight\Message;
use Sunlight\Page\Page;
use Sunlight\Router;
use Sunlight\Util\Form;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

/* ---  priprava promennych  --- */

$message = "";

/* ---  akce  --- */

if (isset($_POST['source'])) {

    // nacteni promennych
    $source = (int) Request::post('source');
    $target = (int) Request::post('target');
    $fullmove = Form::loadCheckbox("fullmove");

    // kontrola promennych
    $error_log = [];
    if (DB::count('page', 'id=' . DB::val($source) . ' AND type=' . Page::CATEGORY) === 0) {
        $error_log[] = _lang('admin.content.movearts.badsource');
    }
    if (DB::count('page', 'id=' . DB::val($target) . ' AND type=' . Page::CATEGORY) === 0) {
        $error_log[] = _lang('admin.content.movearts.badtarget');
    }
    if ($source == $target) {
        $error_log[] = _lang('admin.content.movearts.samecats');
    }

    // aplikace
    if (count($error_log) == 0) {

        if (!$fullmove) {
            $query = DB::query("SELECT id,home1,home2,home3 FROM " . DB::table('article') . " WHERE home1=" . $source . " OR home2=" . $source . " OR home3=" . $source);
            $counter = 0;
            while ($item = DB::row($query)) {
                if ($item['home1'] == $source) {
                    $homeid = 1;
                    $homecheck = [2, 3];
                }
                if ($item['home2'] == $source) {
                    $homeid = 2;
                    $homecheck = [1, 3];
                }
                if ($item['home3'] == $source) {
                    $homeid = 3;
                    $homecheck = [1, 2];
                }
                DB::update('article', 'id=' . $item['id'], ['home' . $homeid => $target]);
                foreach ($homecheck as $hc) {
                    if ($item['home' . $hc] == $target) {
                        $updatedata = [];
                        if ($hc != 1) {
                            $updatedata['home' . $hc] = -1;
                        } else {
                            $updatedata['home' . $homeid] = -1;
                        }
                        DB::update('article', 'id=' . $item['id'], $updatedata);
                    }
                }
                $counter++;
            }
        } else {
            DB::update('article', 'home1=' . $source . ' OR home2=' . $source. ' OR home3=' . $source, [
                'home1' => $target,
                'home2' => -1,
                'home3' => -1
            ]);
            $counter = DB::affectedRows();
        }

        $message = Message::ok(_lang('admin.content.movearts.done', ["%moved%" => $counter]));
    } else {
        $message = Message::list($error_log);
    }

}

/* ---  vystup  --- */

$output .= $message . "
<form class='cform' action='" . _e(Router::admin('content-movearts')) . "' method='post'>"
    . _lang('admin.content.movearts.text1')
    . " " . Admin::pageSelect("source", ['type' => Page::CATEGORY])
    . _lang('admin.content.movearts.text2')
    . " " . Admin::pageSelect("target", ['type' => Page::CATEGORY])
    . " <input class='button' type='submit' value='" . _lang('global.do') . "'>
<br><br>
<label><input type='checkbox' name='fullmove' value='1'> " . _lang('admin.content.movearts.fullmove') . "</label>
" . Xsrf::getInput() . "</form>
";
