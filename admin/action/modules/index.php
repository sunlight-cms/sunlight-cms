<?php

use Sunlight\Admin\Admin;
use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Extend;
use Sunlight\Message;
use Sunlight\Util\Url;

defined('_root') or exit;

/* ---  priprava promennych  --- */

$admin_index_cfg = Core::loadSettings(array(
    'admin_index_custom',
    'admin_index_custom_pos',
    'latest_version_check',
));

$mysqlver = DB::getMysqli()->server_info;
if ($mysqlver != null && mb_substr_count($mysqlver, "-") != 0) {
    $mysqlver = mb_substr($mysqlver, 0, strpos($mysqlver, "-"));
}

$software = getenv('SERVER_SOFTWARE');
if (mb_strlen($software) > 16) {
    $software = substr($software, 0, 13) . "...";
}

/* ---  vystup  --- */

// priprava vlastniho obsahu indexu
$custom = '';
if ($admin_index_cfg['admin_index_custom'] !== '') {
    $custom = $admin_index_cfg['admin_index_custom'];
}
Extend::call('admin.index.custom', array(
    'custom' => &$custom,
    'position' => &$admin_index_cfg['admin_index_custom_pos'],
));

// upozorneni na logout
$logout_warning = '';
$maxltime = ini_get('session.gc_maxlifetime');
if (!empty($maxltime) && !isset($_COOKIE[Core::$appId . '_persistent_key'])) {
    $logout_warning = Admin::note(sprintf(_lang('admin.index.logoutwarn'), round($maxltime / 60)));
}

// vystup
$output .= "
<table id='index-table'>

<tr class='valign-top'>

<td>" . (($custom !== '' && $admin_index_cfg['admin_index_custom_pos'] == 0) ? $custom : "
  <h1>" . _lang('admin.menu.index') . "</h1>
  <p>" . _lang('admin.index.p') . "</p>
  " . $logout_warning . "
  ") . "
</td>

<td width='200'>
  <h2>" . _lang('admin.index.box') . "</h2>
  <table>
    <tr>
      <th>" . _lang('global.version') . ":</th>
      <td>" . Core::VERSION . " <small>(" . Core::DIST . ")</small></td>
    </tr>

    " . ($admin_index_cfg['latest_version_check'] ? "
    <tr>
      <th>" . _lang('admin.index.box.latest') . ":</th>
      <td><span id='latest-version'>---</span></td>
    </tr>
    " : '') . "

    <tr>
      <th>PHP:</th>
      <td>" . PHP_VERSION . "</td>
    </tr>
    <tr>
      <th>MySQL:</th>
      <td>" . $mysqlver . "</td>
    </tr>
  </table>
</td>

</tr>

" . (($custom !== '' && $admin_index_cfg['admin_index_custom_pos'] == 1) ? '<tr><td colspan="2">' . $custom . '</td></tr>' : '') . "

</table>
";

// extend
$output .= Extend::buffer('admin.index.after_table');

// zpravy
$messages = array();

if (Core::DIST === 'BETA') {
    $messages[] = Message::warning(_lang('admin.index.betawarn'));
}

if (_debug) {
    // vyvojovy rezim
    $messages[] = Message::warning(_lang('admin.index.debugwarn'));
}

Extend::call('admin.index.messages', array(
   'messages' => &$messages,
));

$output .= "<div id='index-messages' class='well" . (empty($messages) ? ' hidden' : '') . "'>\n";
$output .= '<h2>' . _lang('admin.index.messages') . "</h2>\n";
$output .= join($messages);
$output .= "</div>\n";

// editace
if (_user_group == _group_admin) {
    $output .= '<p align="right"><a class="button" href="index.php?p=index-edit"><img src="images/icons/edit.png" alt="edit" class="icon">' . _lang('admin.index.edit.link') . '</a></p>';
}

// kontrola funcknosti htaccess
if (!_debug) {
    $output .= "<script>
Sunlight.admin.indexCheckHtaccess(
    " . json_encode(Core::$url . '/vendor/autoload.php?_why=this_is_a_test_if_htaccess_works') . ",
    " . json_encode(_lang('admin.index.htaccess_check_failure', array('*link*' => 'https://sunlight-cms.cz/resource/no-htaccess'))) . ",
);
</script>\n";
}

// nacteni a zobrazeni aktualni verze
if ($admin_index_cfg['latest_version_check']) {
    $versionApiUrl = Url::parse('https://api.sunlight-cms.cz/version');
    $versionApiUrl->add(array(
        'ver' => Core::VERSION,
        'dist' => Core::DIST,
        'php' => PHP_VERSION_ID,
        'checksum' => sha1(Core::$appId . '$' . Core::$secret),
        'lang' => _lang('langcode.iso639'),
    ));

    $output .= "<script>
Sunlight.admin.indexCheckLatestVersion(
    " . json_encode($versionApiUrl->generate(true)) . ",
    " . json_encode(Core::VERSION) . ",
    " . json_encode(_lang('admin.index.version.latest')) . ",
    " . json_encode(_lang('admin.index.version.old')) . "
);
</script>\n";
}
