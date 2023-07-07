<?php

use Sunlight\Core;
use Sunlight\Message;
use Sunlight\Plugin\InactivePlugin;
use Sunlight\Plugin\Plugin;
use Sunlight\Router;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

// clear cache
if (isset($_GET['clear'])) {
    Core::$pluginManager->clearCache();
    $_admin->redirect(Router::admin('plugins', ['query' => ['cleared' => 1]]));

    return;
}

if (isset($_GET['cleared'])) {
    $output .= Message::ok(_lang('global.done'));
}

// buttons
$output .= '<p>
        <a class="button" href="' . _e(Router::admin('plugins-upload')) . '"><img src="' . _e(Router::path('admin/public/images/icons/plugin.png')) . '" alt="upload" class="icon">' . _lang('admin.plugins.upload') . '</a>
        <a class="button" href="' . _e(Router::admin('plugins', ['query' => ['clear' => 1]])) . '"><img src="' . _e(Router::path('admin/public/images/icons/refresh.png')) . '" alt="clear" class="icon">' . _lang('admin.plugins.clear_cache') . '</a>
        <a class="button right" href="https://sunlight-cms.cz/resource/get-plugins" target="_blank"><img src="' . _e(Router::path('admin/public/images/icons/show.png')) . '" alt="get" class="icon">' . _lang('admin.plugins.get') . '</a>
</p>
';

// plugin list
foreach (Core::$pluginManager->getTypes() as $type) {
    $plugins = Core::$pluginManager->getPlugins()->getByType($type->getName());
    $inactivePlugins = Core::$pluginManager->getPlugins()->getInactiveByType($type->getName());

    $output .= "<fieldset>\n";
    $output .= '<legend>' . _lang('admin.plugins.title.' . $type->getName()) . ' (' . (count($plugins) + count($inactivePlugins)) . ")</legend>\n";
    $output .= '<table class="list list-hover plugin-list">
<thead>
    <tr>
        <th>' . _lang('admin.plugins.plugin') . '</th>
        <th>' . _lang('admin.plugins.description') . '</th>
    </tr>
</thead>
<tbody>
';

    // list plugins
    foreach (array_merge($plugins, $inactivePlugins) as $name => $plugin) {
        /* @var $plugin Plugin */

        $isInactive = $plugin instanceof InactivePlugin;

        // get plugin data
        $title = $plugin->getOption('name');
        $descr = $plugin->getOption('description');
        $version = $plugin->getOption('version');

        // determine row class
        if ($plugin->hasStatus(Plugin::STATUS_ERROR)) {
            $rowClass = 'row-danger';
        } elseif ($plugin->hasStatus(Plugin::STATUS_NEEDS_INSTALLATION)) {
            $rowClass = 'row-warning';
        } else {
            $rowClass = null;
        }

        // output row
        $output .= '
    <tr' . ($rowClass !== null ? ' class="' . $rowClass . '"' : '') . '>
        <td>
            ' . ($isInactive
                ? '<h3><del>' . _e($title) . '</del> <small>(' . _lang('admin.plugins.status.' . $plugin->getStatus()) . ')</small></h3>'
                : '<h3>' . _e($title) . '</h3>') . '
            <p>
                ' . _buffer(function () use ($plugin) {
                    foreach ($plugin->getActions() as $name => $action) {
                        if ($action->isAllowed()) {
                            echo '<a class="button" href="' . _e(Xsrf::addToUrl(Router::admin('plugins-action', ['query' => ['id' => $plugin->getId(), 'action' => $name]]))) . '">'
                                . _e($action->getTitle())
                                . "</a>\n";
                        }
                    }
                }) . '
            </p>
        </td>
        <td>
            ' . (!empty($descr) ? '<p>' . nl2br(_e($descr), false) . "</p>\n" : '') . '
            <ul class="inline-list">
                <li><strong>' . _lang('admin.plugins.version') . ':</strong> ' . _e($version) . '</li>
                ' . _buffer(function () use ($plugin) {
                    $authors = $plugin->getOption('authors');

                    if (empty($authors)) {
                        return;
                    }

                    echo '<li><strong>' . _lang('admin.plugins.authors') . ':</strong> ';

                    $first = true;

                    foreach ($authors as $author) {
                        if ($first) {
                            $first = false;
                        } else {
                            echo ', ';
                        }

                        if ($author['url'] !== null) {
                            echo '<a href="' . _e($author['url']) . '" target="_blank" rel="noopener">';
                        }

                        echo _e($author['name']);

                        if ($author['url'] !== null) {
                            echo '</a>';
                        }
                    }

                    echo "</li>\n";
                }) . '
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
