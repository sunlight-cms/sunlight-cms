<?php

use Sunlight\Admin\Admin;
use Sunlight\Core;
use Sunlight\Exception\PrivilegeException;
use Sunlight\Extend;
use Sunlight\Generic;
use Sunlight\Router;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

require '../system/bootstrap.php';
Core::init('../', array(
    'env' => Core::ENV_ADMIN,
));

/* ----  priprava  ---- */

$admin_title = null;
$admin_extra_css = array();
$admin_extra_js = array();
$admin_login_layout = false;
$admin_body_classes = array();
$admin_access = (_logged_in && _priv_administration);
$admin_current_module = Request::get('p', 'index');
$admin_redirect_to = null;
$admin_output = '';
$output = '';

// nacteni modulu
$admin_modules = require _root . 'admin/modules.php';
Extend::call('admin.init', array(
    'modules' => &$admin_modules,
));
$admin_menu_items = array();
foreach ($admin_modules as $module => $module_options) {
    if (isset($module_options['menu']) && $module_options['menu']) {
        $admin_menu_items[$module] = isset($module_options['menu_order']) ? $module_options['menu_order'] : 15;
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
    $assets = Admin::themeAssets(_adminscheme, $theme_dark);
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
    header('Location: ' . $admin_redirect_to);
    exit;
}

// hlavicka a sablona
echo Generic::renderHead();

// body tridy
if ($admin_login_layout) {
    $admin_body_classes[] = 'login-layout';
}
$admin_body_classes[] = $theme_dark ? 'dark' : 'light';

?>
<meta name="robots" content="noindex,follow"><?php echo Generic::renderHeadAssets($assets), "\n" ?>
<title><?php echo _title, ' - ', _lang('global.admintitle'), (!empty($admin_title) ? ' - ' . $admin_title : '') ?></title>
</head>

<body class="<?php echo implode(' ', $admin_body_classes) ?>">

<div id="container">

    <div id="top">
        <div class="wrapper">
            <div id="header">
                <?php echo Admin::userMenu() ?>
                <div id="title">
                    <?php echo _title, ' - ', _lang('global.admintitle') ?>
                </div>
            </div>

            <hr class="hidden">

            <?php echo Admin::menu() ?>
        </div>
    </div>

    <div id="page" class="wrapper">
        <div id="content" class="module-<?php echo _e($admin_current_module) ?>">
            <?php echo $admin_output, $output; ?>

            <div class="cleaner"></div>
        </div>

        <hr class="hidden">
        <div id="footer">
            <div id="footer-links">
                <?php if ($admin_access): ?>
                    <a href="<?php echo Router::link('') ?>" target="_blank"><?php echo _lang('admin.link.site') ?></a>
                    <a href="<?php echo Router::link('admin/') ?>" target="_blank"><?php echo _lang('admin.link.newwin') ?></a>
                <?php else: ?>
                    <a href="<?php echo Router::link('') ?>">&lt; <?php echo _lang('admin.link.home') ?></a>
                <?php endif ?>
            </div>

            <div id="footer-powered-by">
                <?php echo _lang('system.poweredby') ?> <a href="https://sunlight-cms.org/" target="_blank">SunLight CMS</a>
            </div>
        </div>
    </div>

</div>

<?php echo Extend::buffer('admin.end') ?>

</body>
</html>
