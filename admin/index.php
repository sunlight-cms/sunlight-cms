<?php

use Sunlight\Admin\Admin;
use Sunlight\Admin\AdminState;
use Sunlight\Core;
use Sunlight\Exception\PrivilegeException;
use Sunlight\Extend;
use Sunlight\GenericTemplates;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\Request;
use Sunlight\Util\Response;
use Sunlight\Xsrf;

require '../system/bootstrap.php';
Core::init('../', [
    'env' => Core::ENV_ADMIN,
]);

/* ----  priprava  ---- */

$_admin = new AdminState();
$_admin->access = (User::isLoggedIn() && User::hasPrivilege('administration'));
$_admin->currentModule = Request::get('p', 'index');

$output = '';

// nacteni modulu
$_admin->modules = require SL_ROOT . 'admin/modules.php';
Extend::call('admin.init', ['admin' => $_admin]);
foreach ($_admin->modules as $module => $module_options) {
    if (isset($module_options['menu']) && $module_options['menu']) {
        $_admin->menu[$module] = $module_options['menu_order'] ?? 15;
    }
}
asort($_admin->menu, SORT_NUMERIC);

/* ---- priprava obsahu ---- */

// vystup
if (empty($_POST) || Xsrf::check()) {
    if ($_admin->access) {
        try {
            require SL_ROOT . 'admin/action/module.php';
        } catch (PrivilegeException $privException) {
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
    $_admin->assets = Admin::themeAssets(0, false);
} else {
    $_admin->dark = (bool) Settings::get('adminscheme_dark');
    $_admin->assets = Admin::themeAssets(Settings::get('adminscheme'), $_admin->dark);
}

/* ----  vystup  ---- */

// presmerovani?
if ($_admin->redirectTo !== null) {
    Response::redirect($_admin->redirectTo);
    exit;
}

// hlavicka a sablona
echo GenericTemplates::renderHead();

// body tridy
if ($_admin->loginLayout) {
    $_admin->bodyClasses[] = 'login-layout';
}
$_admin->bodyClasses[] = $_admin->dark ? 'dark' : 'light';

?>
<meta name="robots" content="noindex,nofollow"><?= GenericTemplates::renderHeadAssets($_admin->assets), "\n" ?>
<title><?= Settings::get('title'), ' - ', _lang('global.admintitle'), (!empty($_admin->title) ? ' - ' . $_admin->title : '') ?></title>
</head>

<body class="<?= implode(' ', $_admin->bodyClasses) ?>">

<div id="container">

    <div id="top">
        <div class="wrapper">
            <div id="header">
                <?= Admin::userMenu() ?>
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
            <?= $output ?>

            <div class="cleaner"></div>
        </div>

        <hr class="hidden">
        <div id="footer">
            <div id="footer-links">
                <?php if ($_admin->access): ?>
                    <a href="<?= Router::generate('') ?>" target="_blank"><?= _lang('admin.link.site') ?></a>
                    <a href="<?= Router::generate('admin/') ?>" target="_blank"><?= _lang('admin.link.newwin') ?></a>
                <?php else: ?>
                    <a href="<?= Router::generate('') ?>">&lt; <?= _lang('admin.link.home') ?></a>
                <?php endif ?>
            </div>

            <div id="footer-powered-by">
                <?= _lang('system.poweredby') ?> <a href="https://sunlight-cms.cz/" target="_blank">SunLight CMS</a>
            </div>
        </div>
    </div>

</div>

<?= Extend::buffer('admin.end') ?>

</body>
</html>
