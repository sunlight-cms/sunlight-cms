<?php

use Sunlight\Admin\Admin;
use Sunlight\Admin\AdminState;
use Sunlight\Core;
use Sunlight\Exception\PrivilegeException;
use Sunlight\Extend;
use Sunlight\GenericTemplates;
use Sunlight\Logger;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\Request;
use Sunlight\Util\Response;
use Sunlight\Xsrf;

require __DIR__ . '/../system/bootstrap.php';
Core::init('../', [
    'env' => Core::ENV_ADMIN,
]);

/* ----  prepare  ---- */

$_admin = new AdminState();
$_admin->access = (User::isLoggedIn() && User::hasPrivilege('administration'));
$_admin->currentModule = Request::get('p', 'index');

$output = &$_admin->output;

// load modules
$_admin->modules = require SL_ROOT . 'admin/modules.php';
Extend::call('admin.init', ['admin' => $_admin]);

foreach ($_admin->modules as $module => $module_options) {
    if (isset($module_options['menu']) && $module_options['menu']) {
        $_admin->menu[$module] = $module_options['menu_order'] ?? 15;
    }
}

asort($_admin->menu, SORT_NUMERIC);

/* ---- prepare content ---- */

if (empty($_POST) || Xsrf::check()) {
    if ($_admin->access) {
        try {
            require SL_ROOT . 'admin/action/module.php';
        } catch (PrivilegeException $privException) {
            Logger::warning(
                'security',
                'User has caused a privilege exception',
                ['module' => $_admin->currentModule, 'exception' => $privException]
            );

            require SL_ROOT . 'admin/action/priv_error.php';
        }
    } else {
        require SL_ROOT . 'admin/action/login.php';
    }
} else {
    require SL_ROOT . 'admin/action/xsrf_error.php';
}

// assets
if ($_admin->loginLayout) {
    $_admin->assets = Admin::loginAssets();
} else {
    $_admin->dark = (bool) Settings::get('adminscheme_dark');
    $_admin->assets = Admin::assets($_admin);
}

/* ---- output content ---- */

// redirection?
if ($_admin->redirectTo !== null) {
    Response::redirect($_admin->redirectTo);
    exit;
}

// body classes
if ($_admin->loginLayout) {
    $_admin->bodyClasses[] = 'login-layout';
}

$_admin->bodyClasses[] = $_admin->dark ? 'dark' : 'light';

// output
echo _buffer(function () use ($_admin) {
    $replaceBody = false;

    ?>
<?= GenericTemplates::renderHead() ?>
<meta name="robots" content="noindex,nofollow"><?= GenericTemplates::renderHeadAssets($_admin->assets), "\n" ?>
<title><?= Settings::get('title'), ' - ', _lang('global.admintitle'), (!empty($_admin->title) ? ' - ' . $_admin->title : '') ?></title>
</head>

<body class="<?= implode(' ', $_admin->bodyClasses) ?>">

<?= Extend::buffer('admin.body.start', ['replace' => &$replaceBody]) ?>

<?php if (!$replaceBody): ?>
<div id="container">

    <div id="top">
        <div class="wrapper">
            <div id="header">
                <?= Admin::userMenu($_admin->dark) ?>
                <div id="title">
                    <?= Settings::get('title'), ' - ', _lang('global.admintitle') ?>
                </div>
            </div>

            <hr class="hidden">

            <?= Admin::menu() ?>
        </div>
    </div>

    <div id="page" class="wrapper">
        <div id="content" class="module-<?= _e($_admin->currentModule) ?>">
            <?= $_admin->output ?>

            <div class="cleaner"></div>
        </div>

        <hr class="hidden">
        <div id="footer">
            <div id="footer-links">
                <?php if ($_admin->access): ?>
                    <a href="<?= _e(Router::index()) ?>" target="_blank"><?= _lang('admin.link.site') ?></a>
                    <a href="<?= _e(Router::adminIndex()) ?>" target="_blank"><?= _lang('admin.link.newwin') ?></a>
                <?php else: ?>
                    <a href="<?= _e(Router::index()) ?>">&lt; <?= _lang('admin.link.home') ?></a>
                <?php endif ?>
            </div>

            <div id="footer-powered-by">
                <?= _lang('system.poweredby') ?> <a href="https://sunlight-cms.cz/" target="_blank">SunLight CMS</a>
            </div>
        </div>
    </div>

</div>
<?php endif ?>

<?= Extend::buffer('admin.body.end') ?>

</body>
</html>
<?php });
