<?php

use Kuria\RequestInfo\RequestInfo;
use Sunlight\Core;
use Sunlight\Extend;
use Sunlight\GenericTemplates;
use Sunlight\Plugin\PluginRouter;
use Sunlight\Plugin\TemplatePlugin;
use Sunlight\Plugin\TemplateService;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\Template;
use Sunlight\User;
use Sunlight\Util\Response;
use Sunlight\WebState;
use Sunlight\Xsrf;

require './system/bootstrap.php';
Core::init('./', [
    'env' => Core::ENV_WEB,
]);

/* ----  prepare  ---- */

// current URL
$_url = Core::getCurrentUrl();
$pretty_urls_enabled = (bool) Settings::get('pretty_urls');
$is_pretty_url = (RequestInfo::getBaseDir() === RequestInfo::getBasePath());

// init web state
$_index = new WebState();
$_index->template = TemplateService::getDefaultTemplate();
$_index->templateLayout = TemplatePlugin::DEFAULT_LAYOUT;
$_index->slug = RequestInfo::getPathInfo();

// normalize slug
if (strncmp($_index->slug, '/', 1) === 0) {
    $_index->slug = substr($_index->slug, 1);
}

if ($_index->slug === '') {
    $_index->slug = null;
}

// redirect between URL types
if (
    $pretty_urls_enabled !== $is_pretty_url
    && ($pretty_urls_enabled || $_index->slug !== null) // don't redirect / to /index.php
) {
    Response::redirect(Router::slug($_index->slug ?? '', ['absolute' => true, 'query' => $_url->getQuery()]));
    exit;
}

// redirect "/index.php/" to "/"
if (!$pretty_urls_enabled && !$is_pretty_url && $_index->slug === null) {
    Response::redirect(Router::slug('', ['absolute' => true, 'query' => $_url->getQuery()]));
    exit;
}

/* ---- prepare content ---- */

Extend::call('index.init', ['index' => $_index]);

$output = &$_index->output;

do {
    // plugin routes
    if (PluginRouter::handle($_index)) {
        break;
    }

    // XSRF check
    if (!empty($_POST) && !Xsrf::check()) {
        require SL_ROOT . 'system/action/xsrf_error.php';
        break;
    }

    // module
    if ($_index->slug !== null && strncmp($_index->slug, 'm/', 2) === 0) {
        $_index->type = WebState::MODULE;
        Extend::call('mod.init');
        require SL_ROOT . 'system/action/module.php';
        break;
    }

    // enforce login if site is not public
    if (!User::isLoggedIn() && Settings::get('notpublicsite')) {
        $_index->type = WebState::UNAUTHORIZED;
        break;
    }

    // page
    if ($_index->slug !== null) {
        $segments = explode('/', $_index->slug);
    } else {
        $segments = [];
    }

    Extend::call('page.init', [
        'index' => $_index,
        'segments' => $segments,
    ]);

    if ($_index->type !== null) {
        break;
    }

    if (!empty($segments) && $segments[count($segments) - 1] === '') {
        // redirect trailing slash
        $_index->redirect(Router::slug(rtrim($_index->slug, '/'), ['absolute' => true]));
        break;
    }

    // render page
    $_index->type = WebState::PAGE;
    require SL_ROOT . 'system/action/page.php';
} while(false);

/* ---- handle content ---- */

Extend::call('index.handle', ['index' => $_index]);

switch ($_index->type) {
    case WebState::REDIR:
        Response::redirect($_index->redirectTo, $_index->redirectToPermanent);
        exit;

    case WebState::NOT_FOUND:
        require SL_ROOT . 'system/action/not_found.php';
        break;

    case WebState::UNAUTHORIZED:
        require SL_ROOT . 'system/action/login_required.php';
        break;
}

/* ---- insert template ---- */

Extend::call('tpl.start', ['index' => $_index]);

$_index->template->begin($_index->templateLayout);
$_index->templateBoxes = $_index->template->getBoxes($_index->templateLayout);
$_index->templatePath = $_index->template->getTemplate($_index->templateLayout);

Extend::call('tpl.ready', ['index' => $_index]);

echo _buffer(function () use ($_index) { ?>
<?= GenericTemplates::renderHead() ?>
<?= Template::head() ?>
</head>
<body<?php if ($_index->bodyClasses): ?> class="<?= _e(implode(' ', $_index->bodyClasses)) ?>"<?php endif ?><?= Extend::buffer('tpl.body.tag') ?>>
<?= Extend::buffer('tpl.body.start') ?>
<?php require $_index->templatePath ?>
<?= Extend::buffer('tpl.body.end') ?>
</body>
</html>
<?php });

Extend::call('tpl.end', ['index' => $_index]);
