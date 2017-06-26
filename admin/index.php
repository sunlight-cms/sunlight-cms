<?php

use Sunlight\Core;
use Sunlight\Exception\PrivilegeException;
use Sunlight\Extend;

require '../system/bootstrap.php';
Core::init('../', array(
    'env' => 'admin',
));
require _root . 'admin/functions.php';

/* ----  priprava  ---- */

$admin_title = null;
$admin_extra_css = array();
$admin_extra_js = array();
$admin_login_layout = false;
$admin_body_classes = array();
$admin_access = (_login && _priv_administration);
$admin_current_module = _get('p', 'index');
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
if (empty($_POST) || _xsrfCheck()) {
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
$scheme_dark = _adminIsDarkScheme();

$assets = array(
    'extend_event' => 'admin.head',
    'css' => array(
        'admin' => 'script/style.php?s=' . ($admin_login_layout ? 0 : _adminscheme) . ($scheme_dark && !$admin_login_layout ? '&amp;d' : ''),
    ),
    'css_after' => "
<!--[if lte IE 7]><link rel=\"stylesheet\" type=\"text/css\" href=\"css/ie7.css\"><![endif]-->
<!--[if IE 8]><link rel=\"stylesheet\" type=\"text/css\" href=\"css/ie8-9.css\"><![endif]-->
<!--[if IE 9]><link rel=\"stylesheet\" type=\"text/css\" href=\"css/ie8-9.css\"><![endif]-->",
    'js' => array(
        'jquery' => _link('system/js/jquery.js'),
        'sunlight' => _link('system/js/sunlight.js'),
        'rangyinputs' => _link('system/js/rangyinputs.js'),
        'scrollwatch' => _link('system/js/scrollwatch.js'),
        'scrollfix' => _link('system/js/scrollfix.js'),
        'jquery_ui_sortable' => _link('admin/js/jquery-ui-sortable.min.js'),
        'admin' => _link('admin/js/admin.js'),
    ),
    'js_before' => "\n" . Core::getJavascript(array(
        'admin' => array(
            'themeIsDark' => $scheme_dark,
        ),
        'labels' => array(
            'cancel' => _lang('global.cancel'),
            'fmanMovePrompt' => _lang('admin.fman.selected.move.prompt'),
            'fmanDeleteConfirm' => _lang('admin.fman.selected.delete.confirm'),
            'busyOverlayText' => _lang('admin.busy_overlay.text'),
        ),
    )),
);

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
require _root . 'system/html_start.php';

// body tridy
if ($admin_login_layout) {
    $admin_body_classes[] = 'login-layout';
}
$admin_body_classes[] = $scheme_dark ? 'dark' : 'light';

?>
<meta name="robots" content="noindex,follow"><?php echo _headAssets($assets), "\n" ?>
<title><?php echo _title, ' - ', _lang('global.admintitle'), (!empty($admin_title) ? ' - ' . $admin_title : '') ?></title>
</head>

<body class="<?php echo implode(' ', $admin_body_classes) ?>">

<div id="container">

    <div id="top">
        <div class="wrapper">
            <div id="header">
                <?php echo _adminUserMenu() ?>
                <div id="title">
                    <?php echo _title, ' - ', _lang('global.admintitle') ?>
                </div>
            </div>

            <hr class="hidden">

            <?php echo _adminMenu() ?>
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
                    <a href="<?php echo _link('') ?>" target="_blank"><?php echo _lang('admin.link.site') ?></a>
                    <a href="<?php echo _link('admin/') ?>" target="_blank"><?php echo _lang('admin.link.newwin') ?></a>
                <?php else: ?>
                    <a href="<?php echo _link('') ?>">&lt; <?php echo _lang('admin.link.home') ?></a>
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
