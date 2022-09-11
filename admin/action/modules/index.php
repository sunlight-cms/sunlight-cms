<?php

use Sunlight\Admin\Admin;
use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\Environment;
use Sunlight\VersionChecker;

defined('SL_ROOT') or exit;

$admin_index_cfg = Settings::getMultiple([
    'admin_index_custom',
    'admin_index_custom_pos',
]);

$version_data = VersionChecker::check();

$mysqlver = DB::$mysqli->server_info;
if ($mysqlver != null && mb_substr_count($mysqlver, '-') != 0) {
    $mysqlver = mb_substr($mysqlver, 0, strpos($mysqlver, '-'));
}

$software = getenv('SERVER_SOFTWARE');
if (mb_strlen($software) > 16) {
    $software = substr($software, 0, 13) . '...';
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
if (!empty($maxltime) && !isset($_COOKIE[Core::$appId . '_persistent_key'])) {
    $logout_warning = Admin::note(_lang('admin.index.logoutwarn', ['%minutes%' => round($maxltime / 60)]));
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
    $messages[] = Message::warning(_lang('admin.index.betawarn'));
}

if (Core::$debug) {
    // debug mode
    $messages[] = Message::warning(_lang('admin.index.debugwarn'));
}

if (($version_data !== null) && $version_data['localAge'] >= 0) {
    if ($version_data['localAge'] === 0) {
        $messages[] = Message::ok(_lang('admin.index.version.latest'));
    } else {
        $messages[] = Message::warning(
            _lang('admin.index.version.old', ['%version%' => $version_data['latestVersion'], '%link%' => $version_data['url']]),
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
    $output .= '<p class="text-right"><a class="button" href="' . _e(Router::admin('index-edit')) . '"><img src="' . _e(Router::path('admin/images/icons/edit.png')) . '" alt="edit" class="icon">' . _lang('admin.index.edit.link') . '</a></p>';
}

// .htaccess check
$output .= '<script>
Sunlight.admin.indexCheckHtaccess(
    ' . json_encode(Core::getBaseUrl()->getPath() . '/vendor/autoload.php?_why=this_is_a_test_if_htaccess_works') . ',
    ' . json_encode(_lang('admin.index.htaccess_check_failure', ['%link%' => 'https://sunlight-cms.cz/resource/no-htaccess'])) . "
);
</script>\n";
