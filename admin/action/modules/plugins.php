<?php

use Sunlight\Core;
use Sunlight\Plugin\Plugin;
use Sunlight\Plugin\InactivePlugin;

if (!defined('_root')) {
    exit;
}

// vycisteni cache
if (isset($_GET['clear'])) {
    Core::$pluginManager->purgeCache();
    $admin_redirect_to = 'index.php?p=plugins&cleared';

    return;
} elseif (isset($_GET['cleared'])) {
    $output .= _msg(_msg_ok, $_lang['global.done']);
}

// pomocne funkce
$renderPluginAuthor = function ($author, $url) {
    $renderedAuthor = '';

    if (!empty($url)) {
        $renderedAuthor .= '<a href="' . _e($url) . '" target="_blank">';
    }
    if (!empty($author)) {
        $renderedAuthor .= _e($author);
    } else {
        $renderedAuthor .= _e(parse_url($url, PHP_URL_HOST));
    }
    if (!empty($url)) {
        $renderedAuthor .= '</a>';
    }

    if ('' !== $renderedAuthor) {
        $renderedAuthor = "<li><strong>{$GLOBALS['_lang']['admin.plugins.author']}:</strong> {$renderedAuthor}</li>\n";
    }

    return $renderedAuthor;
};

// tlacitka
$output .= '<p>
        <a class="button" href="index.php?p=plugins-upload"><img src="images/icons/plugin.png" alt="upload" class="icon">' . $_lang['admin.plugins.upload'] . '</a>
        <a class="button" href="index.php?p=plugins&amp;clear"><img src="images/icons/refresh.png" alt="clear" class="icon">' . $_lang['admin.plugins.clear_cache'] . '</a>
        <a class="button right" href="https://sunlight-cms.org/resource/get-plugins" target="_blank"><img src="images/icons/show.png" alt="get" class="icon">' . $_lang['admin.plugins.get'] . '</a>
</p>
';

// seznam pluginu
foreach (Core::$pluginManager->all() as $pluginType => $plugins) {
    $inactivePlugins = Core::$pluginManager->allInactive($pluginType);

    $output .= "<fieldset>\n";
    $output .= '<legend>' . $_lang['admin.plugins.title.' . $pluginType] . ' (' . (sizeof($plugins) + sizeof($inactivePlugins)) . ")</legend>\n";
    $output .= '<table class="list list-hover plugin-list">
<thead>
    <tr>
        <th>' . $_lang['admin.plugins.plugin'] . '</th>
        <th>' . $_lang['admin.plugins.description'] . '</th>
    </tr>
</thead>
<tbody>
';

    // vykreslit pluginy
    foreach (array_merge($plugins, $inactivePlugins) as $name => $plugin) {
        /* @var $plugin Plugin */

        $isInactive = $plugin instanceof InactivePlugin;

        // nacist data z pluginu
        $title = $plugin->getOption('name');
        $descr = $plugin->getOption('description');
        $version = $plugin->getOption('version');
        $author = $plugin->getOption('author');
        $url = $plugin->getOption('url');

        // urcit tridu radku
        if ($plugin->hasErrors()) {
            $rowClass = 'row-danger';
        } elseif ($plugin->needsInstallation()) {
            $rowClass = 'row-warning';
        } else {
            $rowClass = null;
        }

        // vykreslit radek
        $output .= '
    <tr' . (null !== $rowClass ? " class=\"{$rowClass}\"" : '') . '>
        <td>
            ' . ($isInactive
                ? '<h3><del>' . _e($title) . '</del> <small>(' . $_lang['admin.plugins.status.' . $plugin->getStatus()] . ')</small></h3>'
                : '<h3>' . _e($title) . '</h3>') . '
            <p>
                ' . _buffer(function () use ($plugin) {
                    foreach ($plugin->getActionList() as $action => $label) {
                        echo '<a class="button" href="' . _xsrfLink('index.php?p=plugins-action&amp;type=' . _e($plugin->getType()) . '&amp;name=' . _e($plugin->getId()) . '&amp;action=' . _e($action)) . '">' . _e($label) . "</a>\n";
                    }
                }) . '
            </p>
        </td>
        <td>
            ' . (!empty($descr) ? '<p>' . nl2br(_e($descr), false) . "</p>\n" : '') . '
            <ul class="inline-list">
                <li><strong>' . $_lang['admin.plugins.version'] . ':</strong> ' . _e($version) . '</li>
                ' . $renderPluginAuthor($author, $url) . '
            </ul>
        </td>
    </tr>
';
    }

    $output .= '</tbody>
</table>
</fieldset>
';
}
