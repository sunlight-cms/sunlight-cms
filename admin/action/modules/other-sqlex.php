<?php

use Sunlight\Database\Database as DB;
use Sunlight\Database\SqlReader;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

$tables = DB::getTablesByPrefix();
$sql = Request::post('sql', '');

// form
$output .= '
<form method="post">
<table id="sqlex">
    <tr>
        <td>
            <ul>
';

foreach ($tables as $table) {
    $output .= '<li>
    <img class="icon" src="' . _e(Router::path('admin/images/icons/list.png')) . '" alt="table">
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

// result
$queries = (new SqlReader($sql))->read();

if (!empty($queries)) {
    // process queries
    $log = [];
    $lastResult = null;
    $error = false;

    for ($i = 0; isset($queries[$i]); ++$i) {
        $result = DB::query($queries[$i], true);

        if ($result instanceof mysqli_result) {
            // result
            $log[] = _lang('admin.other.sqlex.rows') . ': ' . DB::size($result);
            $lastResult = $result;
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

    // output log
    $output .= '
    <div id="sqlex-result">
        <h2>' . _lang('global.result') . '</h2>
        <ol>
';

    for ($i = 0; isset($log[$i]); ++$i) {
        $isError = ($error && !isset($log[$i + 1]));
        $output .= '<li' . ($isError ? ' class="important"' : '') . ">{$log[$i]}</li>\n";
    }

    $output .= "</ol>\n";

    // output results
    if ($error) {
        $output .= Message::error(DB::$mysqli->error);
    } elseif ($lastResult !== null) {
        $columns = DB::columns($lastResult);

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

        while ($row = DB::rown($lastResult)) {
            $output .= "<tr>\n";

            for ($j = 0; $j < $i; ++$j) {
                $output .= '<td>';

                if ($row[$j] === null) {
                    // null
                    $output .= '<code class="text-warning">NULL</code>';
                } elseif (strpos($row[$j], "\n") !== false) {
                    // string with newlines
                    $output .= '<textarea cols="60" rows="' . max(10, substr_count($row[$j], "\n")) . '">' . _e($row[$j]) . '</textarea>';
                } elseif (strlen($row[$j]) > 64) {
                    // long string
                    $output .= '<input size="64" value="' . _e($row[$j]) . '">';
                } else {
                    // short string
                    $output .= _e($row[$j]);
                }

                $output .= "</td>\n";
            }

            $output .= "</tr>\n";
        }

        $output .= '</tbody>
</table>
';
    }

    $output .= "</div>\n";
}
