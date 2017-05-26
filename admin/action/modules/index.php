<?php

if (!defined('_root')) {
    exit;
}

/* ---  priprava promennych  --- */

$admin_index_cfg = Sunlight\Core::loadSettings(array(
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
Sunlight\Extend::call('admin.index.custom', array(
    'custom' => &$custom,
    'position' => &$admin_index_cfg['admin_index_custom_pos'],
));

// upozorneni na logout
$logout_warning = '';
$maxltime = ini_get('session.gc_maxlifetime');
if (!empty($maxltime) && !isset($_COOKIE[Sunlight\Core::$appId . '_persistent_key'])) {
    $logout_warning = _adminNote(sprintf($_lang['admin.index.logoutwarn'], round($maxltime / 60)));
}

// vystup
$output .= "
<table id='index-table'>

<tr class='valign-top'>

<td>" . (('' !== $custom && $admin_index_cfg['admin_index_custom_pos'] == 0) ? $custom : "
  <h1>" . $_lang['admin.menu.index'] . "</h1>
  <p>" . $_lang['admin.index.p'] . "</p>
  " . $logout_warning . "
  ") . "
</td>

<td width='200'>
  <h2>" . $_lang['admin.index.box'] . "</h2>
  <table>
    <tr>
      <th>" . $_lang['global.version'] . ":</th>
      <td>" . Sunlight\Core::VERSION . ' <small>' . Sunlight\Core::STATE . "</small></td>
    </tr>

    " . ($admin_index_cfg['latest_version_check'] ? "
    <tr>
      <th>" . $_lang['admin.index.box.latest'] . ":</th>
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

" . (('' !== $custom && $admin_index_cfg['admin_index_custom_pos'] == 1) ? '<tr><td colspan="2">' . $custom . '</td></tr>' : '') . "

</table>
";

// extend
$output .= Sunlight\Extend::buffer('admin.index.after_table');

// zpravy
$messages = array();

if ('STABLE' !== Sunlight\Core::STATE) {
    // nestabilni verze
    $messages[] = Sunlight\Message::warning(str_replace('*state*', Sunlight\Core::STATE, $_lang['admin.index.statewarn']));
}
if (_dev) {
    // vyvojovy rezim
    $messages[] = Sunlight\Message::warning($_lang['admin.index.devwarn']);
}

Sunlight\Extend::call('admin.index.messages', array(
   'messages' => &$messages,
));

$output .= "<div id='index-messages' class='well'>\n";
$output .= '<h2>' . $_lang['admin.index.messages'] . "</h2>\n";
$output .= join($messages);
$output .= "</div>\n";

// editace
if (_logingroup == _group_admin) {
    $output .= '<p align="right"><a class="button" href="index.php?p=index-edit"><img src="images/icons/edit.png" alt="edit" class="icon">' . $_lang['admin.index.edit.link'] . '</a></p>';
}

// nacteni a zobrazeni aktualni verze
if ($admin_index_cfg['latest_version_check']) {
    $versionApiUrl = Sunlight\Util\Url::parse('https://sunlight-cms.org/api/v2/version');
    $versionApiUrl->add(array(
        'ver' => Sunlight\Core::VERSION,
        'state' => Sunlight\Core::STATE,
        'php' => PHP_VERSION_ID,
        'referer' => sprintf('%s@%s', sha1(Sunlight\Core::$appId . '$' . Sunlight\Core::$secret), Sunlight\Util\Url::current()->host),
        'lang' => $_lang['langcode.iso639'],
    ));

    $output .= "<script type='text/javascript'>
$.ajax({
    url: " . json_encode($versionApiUrl->generate(true)) . ",
    dataType: 'jsonp',
    cache: false,
    success: function (response) {
        // update #latest-version
        var elem = document.createElement(response.url ? 'a' : 'span');
        var ageClass;

        switch (response.localAge) {
            case 1: ageClass = 'patch';  break;
            case 2: ageClass = 'minor'; break;
            case 3: ageClass = 'major'; break;
            default: ageClass = 'latest'; break;
        }
        
        if (response.url) {
            elem.href = response.url;
            elem.target = '_blank';
        }
        elem.className = 'version-' + ageClass;
        elem.appendChild(document.createTextNode(response.latestVersion));

        $('#latest-version').empty().append(elem);
        
        // add message to #index-messages
        if (response.localAge >= 0) {
            var messageType, message;
            
            if (response.localAge <= 0) {
                messageType = 'ok';
                message = " . json_encode($_lang['admin.index.version.latest']) . ";
            } else {
                messageType = 'warn';
                message = " . json_encode($_lang['admin.index.version.old']) . ";
                message = message
                    .replace('*version*', Sunlight.escapeHtml(response.latestVersion))
                    .replace('*link*', 'https://sunlight-cms.org/goto/update');
            }
            
            $(Sunlight.msg(messageType, message, true)).insertAfter('#index-messages > h2:first-child');
        }
    }
});
</script>\n";
}
