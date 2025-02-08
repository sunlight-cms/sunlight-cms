<?php

use Sunlight\Admin\Admin;
use Sunlight\Admin\AdminState;
use Sunlight\Core;
use Sunlight\Extend;
use Sunlight\GenericTemplates;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\Request;
use Sunlight\Util\Response;
use Sunlight\Xsrf;

require __DIR__ . '/../system/bootstrap.php';
Core::init(['env' => Core::ENV_ADMIN]);

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
        require SL_ROOT . 'admin/action/module.php';
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
<meta name="robots" content="noindex,nofollow">
<meta name="viewport" content="width=device-width, initial-scale=1"><?= GenericTemplates::renderHeadAssets($_admin->assets), "\n" ?>
<title><?= Settings::get('title'), ' - ', _lang('global.admintitle'), (!empty($_admin->title) ? ' - ' . $_admin->title : '') ?></title>
</head>

<body class="<?= implode(' ', $_admin->bodyClasses) ?>">

<?= Extend::buffer('admin.body.start', ['replace' => &$replaceBody]) ?>

<?php if (!$replaceBody): ?>
<div id="container">

    <header id="top">
        <div class="wrapper">
            <div id="header">
                <?= Admin::userMenu($_admin->dark) ?>
                <div id="title">
                    <label id="menu-toggle-button" for="menu-toggle" aria-label="<?= _lang('admin.open_menu') ?>">☰</label>
                    <?= Settings::get('title'), ' - ', _lang('global.admintitle') ?>
                </div>
            </div>

            <hr class="hidden">
            <input type="checkbox" class="hidden" id="menu-toggle">
            <?= Admin::menu() ?>
        </div>
    </header>

    <main id="page" class="wrapper">
        <div id="content" class="module-<?= _e($_admin->currentModule) ?>">
            <?= $_admin->output ?>

            <div class="cleaner"></div>
        </div>
    </main>

    <footer id="footer" class="wrapper">
        <hr class="hidden">

        <div id="footer-content">
            <div id="footer-links">
                <?php if ($_admin->access): ?>
                    <a href="<?= _e(Router::index()) ?>" target="_blank"><?= _lang('admin.link.site') ?></a>
                    <a href="<?= _e(Router::adminIndex()) ?>" target="_blank"><?= _lang('admin.link.newwin') ?></a>
                <?php else: ?>
                    <a href="<?= _e(Router::index()) ?>">&lt; <?= _lang('admin.link.home') ?></a>
                <?php endif ?>
            </div>

            <div id="footer-powered-by">
                <a href="https://sunlight-cms.cz/" target="_blank"><?= _lang('system.poweredby') ?> SunLight CMS</a>
            </div>
        </div>
    </footer>

</div>
<?php endif ?>

<?= Extend::buffer('admin.body.end') ?>

</body>
</html>
<?php });
