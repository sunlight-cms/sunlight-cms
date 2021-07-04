<?php

use Sunlight\Admin\Admin;
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

$admin_title = null;
$admin_extra_css = [];
$admin_extra_js = [];
$admin_login_layout = false;
$admin_body_classes = [];
$admin_access = (User::isLoggedIn() && User::hasPrivilege('administration'));
$admin_current_module = Request::get('p', 'index');
$admin_redirect_to = null;
$admin_output = '';
$output = '';

// nacteni modulu
$admin_modules = require _root . 'admin/modules.php';
Extend::call('admin.init', [
    'modules' => &$admin_modules,
]);
$admin_menu_items = [];
foreach ($admin_modules as $module => $module_options) {
    if (isset($module_options['menu']) && $module_options['menu']) {
        $admin_menu_items[$module] = $module_options['menu_order'] ?? 15;
    }
}
asort($admin_menu_items, SORT_NUMERIC);

/* ---- priprava obsahu ---- */

// vystup
if (empty($_POST) || Xsrf::check()) {
    if ($admin_access) {
        try {
            require _root . 'admin/action/module.php';
        } catch (PrivilegeException $privException) {
            require _root . 'admin/action/priv_error.php';
        }
    } else {
        require _root . 'admin/action/login.php';
    }
} else {
    require _root . 'admin/action/xsrf_error.php';
}

// assets
if ($admin_login_layout) {
    $theme_dark = false;
    $assets = Admin::themeAssets(0, false);
} else {
    $theme_dark = Admin::themeIsDark();
    $assets = Admin::themeAssets(Settings::get('adminscheme'), $theme_dark);
}

if (!empty($admin_extra_css)) {
    $assets['css_after'] = "\n" . implode("\n", $admin_extra_css);
}
if (!empty($admin_extra_js)) {
    $assets['js_after'] = "\n" . implode("\n", $admin_extra_js);
}

/* ----  vystup  ---- */

// presmerovani?
if ($admin_redirect_to !== null) {
    Response::redirect($admin_redirect_to);
    exit;
}

// hlavicka a sablona
echo GenericTemplates::renderHead();

// body tridy
if ($admin_login_layout) {
    $admin_body_classes[] = 'login-layout';
}
$admin_body_classes[] = $theme_dark ? 'dark' : 'light';

?>
<meta name="robots" content="noindex,nofollow"><?= GenericTemplates::renderHeadAssets($assets), "\n" ?>
<title><?= Settings::get('title'), ' - ', _lang('global.admintitle'), (!empty($admin_title) ? ' - ' . $admin_title : '') ?></title>
</head>

<body class="<?= implode(' ', $admin_body_classes) ?>">

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
        <div id="content" class="module-<?= _e($admin_current_module) ?>">
            <?= $admin_output, $output ?>

            <div class="cleaner"></div>
        </div>

        <hr class="hidden">
        <div id="footer">
            <div id="footer-links">
                <?php if ($admin_access): ?>
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
