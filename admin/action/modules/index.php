<?php

use Sunlight\Admin\Admin;
use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\Log\LogQuery;
use Sunlight\Logger;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\Cookie;
use Sunlight\Util\Environment;
use Sunlight\Util\Json;
use Sunlight\VersionChecker;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

if (isset($_POST['hide_recent_log_errors']) && Admin::moduleAccess('log')) {
    Settings::update('admin_index_log_since', (string) time(), false);
}

$admin_index_cfg = Settings::getMultiple([
    'admin_index_custom',
    'admin_index_custom_pos',
    'admin_index_log_since',
]);

$version_data = VersionChecker::check();

$mysqlver = DB::$mysqli->server_info;

if ($mysqlver != null && mb_substr_count($mysqlver, '-') != 0) {
    $mysqlver = mb_substr($mysqlver, 0, strpos($mysqlver, '-'));
}

// prepare custom content
$custom = '';

if ($admin_index_cfg['admin_index_custom'] !== '') {
    $custom = $admin_index_cfg['admin_index_custom'];
}

Extend::call('admin.index.custom', [
    'custom' => &$custom,
    'position' => &$admin_index_cfg['admin_index_custom_pos'],
]);

// logout warning
$logout_warning = '';
$maxltime = ini_get('session.gc_maxlifetime');

if (!empty($maxltime) && !Cookie::exists(User::COOKIE_PERSISTENT_LOGIN)) {
    $logout_warning = Admin::note(_lang('admin.index.logoutwarn', ['%minutes%' => _num(round($maxltime / 60))]));
}

// output
$output .= '
<table id="index-table">

<tr class="valign-top">

<td>' . (($custom !== '' && $admin_index_cfg['admin_index_custom_pos'] == 0) ? $custom : '
  <h1>' . _lang('admin.menu.index') . '</h1>
  <p>' . _lang('admin.index.p') . '</p>
  ' . $logout_warning . '
  ') . '
</td>

<td>
  <h2>' . _lang('admin.index.box') . '</h2>
  <table>
    <tr>
      <th>' . _lang('global.version') . ':</th>
      <td>' . Core::VERSION . ' <small>(' . Core::DIST . ')</small></td>
    </tr>

    ' . _buffer(function () use ($version_data) { ?>
        <tr>
            <th><?= _lang('admin.index.box.latest') ?></th>
            <td>
                <?php if ($version_data === null): ?>
                    ---
                <?php else: ?>
                    <a class="latest-version latest-version-age-<?= _e($version_data['localAge']) ?>" href="<?= _e($version_data['url']) ?>" target="_blank">
                        <?= _e($version_data['latestVersion']) ?>
                    </a>
                <?php endif ?>
            </td>
        </tr>
    <?php }) . '

    <tr>
      <th>PHP:</th>
      <td>
        ' . (User::isSuperAdmin() ? '<a href="' . _e(Router::path('admin/script/phpinfo.php')) . '" target="_blank">' : '') . '
        ' . _e(Environment::getPhpVersion()) . '
        ' . (User::isSuperAdmin() ? '</a>' :'') . '
    </td>
    </tr>
    <tr>
      <th>MySQL:</th>
      <td>' . $mysqlver . '</td>
    </tr>
  </table>
</td>

</tr>

' . (($custom !== '' && $admin_index_cfg['admin_index_custom_pos'] == 1) ? '<tr><td colspan="2">' . $custom . '</td></tr>' : '') . '

</table>
';

// extend
$output .= Extend::buffer('admin.index.after_table');

// messages
$messages = [];

if (Core::DIST === 'BETA') {
    // beta warning
    $messages[] = Message::warning(_lang('admin.index.betawarn'));
}

if (Core::$debug) {
    // debug mode
    $messages[] = Message::warning(_lang('admin.index.debugwarn'));
}

if (Core::$safeMode) {
    $messages[] = Message::warning(_lang('admin.plugins.safe_mode_warning'));
}

if (($version_data !== null) && $version_data['localAge'] >= 0) {
    // old version
    if ($version_data['localAge'] === 0) {
        $messages[] = Message::ok(_lang('admin.index.version.latest'));
    } else {
        $messages[] = Message::warning(
            _lang('admin.index.version.old', ['%version%' => $version_data['latestVersion'], '%link%' => $version_data['url']]),
            true
        );
    }
}

if (Admin::moduleAccess('log')) {
    $log_query = new LogQuery();
    $log_query->maxLevel = Logger::ERROR;
    $log_query->since = $admin_index_cfg['admin_index_log_since'] !== '0' ? (int) $admin_index_cfg['admin_index_log_since'] : null;
    $recent_log_errors = Logger::getTotalResults($log_query);

    if ($recent_log_errors > 0) {
        $messages[] = Message::warning(
            _buffer(function () use ($log_query, $recent_log_errors) {
                ?>
    <?= _lang('admin.index.recent_log_errors', ['%count%' => _num($recent_log_errors)]) ?><br><br>

    <a class="button" href="<?= _e(Router::admin('log', ['query' => ['maxLevel' => $log_query->maxLevel, 'since' => $log_query->since !== null ? "@{$log_query->since}" : null, 'desc' => '1', 'search' => '1']])) ?>">
        <?= _lang('global.show') ?>
    </a>

    <form method="post" class="inline">
        <input class="button" type="submit" name="hide_recent_log_errors" value="<?= _lang('global.hide') ?>">
        <?= Xsrf::getInput() ?>
    </form>
<?php
            }),
            true
        );
    }
}

Extend::call('admin.index.messages', [
    'messages' => &$messages,
]);

$output .= '<div id="index-messages" class="well' . (empty($messages) ? ' hidden' : '') . "\">\n";
$output .= '<h2>' . _lang('admin.index.messages') . "</h2>\n";
$output .= implode($messages);
$output .= "</div>\n";

// edit link
if (User::$group['id'] == User::ADMIN_GROUP_ID) {
    $output .= '<p class="text-right"><a class="button" href="' . _e(Router::admin('index-edit')) . '"><img src="' . _e(Router::path('admin/public/images/icons/edit.png')) . '" alt="edit" class="icon">' . _lang('admin.index.edit.link') . '</a></p>';
}

// .htaccess check
$output .= '<script>
Sunlight.admin.indexCheckHtaccess(
    ' . Json::encodeForInlineJs(Core::getBaseUrl()->getPath() . '/vendor/autoload.php?_why=this_is_a_test_if_htaccess_works') . ',
    ' . Json::encodeForInlineJs(_lang('admin.index.htaccess_check_failure', ['%link%' => 'https://sunlight-cms.cz/resource/8.x/no-htaccess'])) . "
);
</script>\n";
