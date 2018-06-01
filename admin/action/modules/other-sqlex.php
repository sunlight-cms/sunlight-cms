<?php

use Sunlight\Database\Database as DB;
use Sunlight\Database\SqlReader;
use Sunlight\Message;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('_root') or exit;

/* ---  priprava  --- */

$tables = DB::getTablesByPrefix();

// nacist zaslany sql kod
$sql = Request::post('sql', '');

/* --- formular --- */

$output .= '
<form method="post">
<table id="sqlex">
    <tr>
        <td>
            <ul>
';

foreach ($tables as $table) {
    $output .= '<li>
    <img class="icon" src="images/icons/list.png" alt="table">
    <a href="javascript:void(0)" onclick="Sunlight.admin.sqlexInsertTableName(this)">' . _e($table) . "</a>
</li>\n";
}

$output .= '
            </ul>
        </td>
        <td>
            <textarea class="max-area" rows="15" cols="70" name="sql">' . _e($sql) . '</textarea>
        </td>
    </tr>
    <tr>
        <td></td>
        <td>
            <input class="inputfat" type="submit" value="' . _lang('admin.other.sqlex.run') . '">
            <span class="note">(' . _lang('admin.other.sqlex.hint') . ')</span>
        </td>
    </tr>
</table>
' . Xsrf::getInput() . '
</form>';

/* --- vysledek --- */

$queries = SqlReader::fromString($sql)->read();
if (!empty($queries)) {

    // zpracovat dotazy
    $log = array();
    $lastResource = null;
    $error = false;
    for ($i = 0; isset($queries[$i]); ++$i) {

        $result = DB::query($queries[$i], true);
        if ($result instanceof mysqli_result) {
            // resource
            $log[] = _lang('admin.other.sqlex.rows') . ': ' . DB::size($result);
            if ($lastResource !== null) {
                DB::free($lastResource);
            }
            $lastResource = $result;
        } elseif ($result) {
            // true
            $log[] = _lang('admin.other.sqlex.affected') . ': ' . DB::affectedRows();
        } else {
            // false
            $log[] = _lang('global.error');
            $error = true;
            break;
        }

    }

    // vypis logu
    $output .= '
    <div id="sqlex-result">
        <h2>' . _lang('global.result') . '</h2>
        <ol>
';
    for ($i = 0; isset($log[$i]); ++$i) {
        $isError = ($error && !isset($log[$i + 1]));
        $output .= "<li" . ($isError ? ' class="important"' : '') . ">{$log[$i]}</li>\n";
    }
    $output .= "</ol>\n";

    // vypis vysledku
    if ($error) {
        $output .= Message::render(_msg_err, _e(DB::error()));
    } elseif ($lastResource !== null) {

        $columns = DB::columns($lastResource);

        $output .= '<table class="list list-hover">
<thead>
    <tr>
';

        for ($i = 0; isset($columns[$i]); ++$i) {
            $output .= '<td>' . _e($columns[$i]) . "</td>\n";
        }

        $output .= '</thead>
<tbody>
';

        while ($row = DB::rown($lastResource)) {
            $output .= "<tr>\n";
            for ($j = 0; $j < $i; ++$j) {
                $output .= '<td>';
                if ($row[$j] === null) {
                    // null
                    $output .= '<code class="text-warning">NULL</code>';
                } elseif (strpos($row[$j], "\n") !== false) {
                    // s odradkovanim
                    $output .= '<textarea cols="60" rows="' . max(10, substr_count($row[$j], "\n")) . '">' . _e($row[$j]) . '</textarea>';
                } elseif (strlen($row[$j]) > 64) {
                    // dlouhy text
                    $output .= '<input size="64" value="' . _e($row[$j]) . '">';
                } else {
                    // kratky text
                    $output .= _e($row[$j]);
                }
                $output .= "</td>\n";
            }
            $output .= "</tr>\n";
        }

        DB::free($lastResource);

        $output .= '</tbody>
</table>
';

    }

    $output .= "</div>\n";

}
