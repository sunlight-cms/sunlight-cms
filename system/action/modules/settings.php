<?php

use Sunlight\Extend;
use Sunlight\Router;
use Sunlight\User;
use Sunlight\Util\Request;

defined('SL_ROOT') or exit;

if (!User::isLoggedIn()) {
    $_index->unauthorized();
    return;
}

$_index->title = _lang('mod.settings');

$actions = [
    'account' => ['title' => _lang('mod.settings.account'), 'script' => __DIR__ . '/include/settings-account.php'],
    'password' => ['title' => _lang('mod.settings.password'), 'script' => __DIR__ . '/include/settings-password.php'],
    'email' => ['title' => _lang('mod.settings.email'), 'script' => __DIR__ . '/include/settings-email.php'],
    'profile' => ['title' => _lang('mod.settings.profile'), 'script' => __DIR__ . '/include/settings-profile.php'],
    'download' => ['title' => _lang('mod.settings.download'), 'script' => __DIR__ . '/include/settings-download.php'],
];

if (User::hasPrivilege('selfremove')) {
    $actions['remove'] = ['title' => _lang('mod.settings.remove'), 'script' => __DIR__ . '/include/settings-remove.php'];
}

Extend::call('mod.settings.actions', ['actions' => &$actions]);

$action = Request::get('action');

if ($action !== null) {
    if (isset($actions[$action])) {
        $_index->backlink = Router::module('settings');

        require $actions[$action]['script'];
    } else {
        $_index->notFound();
    }

    return;
}

$output .= _buffer(function () use ($actions) { ?>
    <div class="user-settings-actions">
        <p><?= _lang('mod.settings.p') ?></p>

        <ul>
            <?php foreach ($actions as $action => $actionInfo): ?>
                <li class="user-settings-action-<?= _e($action) ?>"><a href="<?= _e(Router::module('settings', ['query' => ['action' => $action]])) ?>"><?= $actionInfo['title'] ?></a></li>
            <?php endforeach ?>
        </ul>
    </div>
<?php });
