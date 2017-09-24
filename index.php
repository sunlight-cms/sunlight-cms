<?php

use Sunlight\Core;
use Sunlight\Extend;
use Sunlight\Plugin\TemplatePlugin;
use Sunlight\Plugin\TemplateService;
use Sunlight\Util\Url;

require './system/bootstrap.php';
Core::init('./', array(
    'env' => Core::ENV_WEB,
));

// funkce motivu
require _root . 'system/functions-template.php';

/* ----  priprava  ---- */

// motiv
/** @var TemplatePlugin $_template */
$_template = null;
/** @var string $_template_layout */
$_template_layout = null;

// nacist vychozi motiv
if (!_templateSwitch(TemplateService::composeUid(_default_template, TemplatePlugin::DEFAULT_LAYOUT))) {
    Core::updateSetting('default_template', 'default');

    Core::systemFailure(
        'Motiv "%s" nebyl nalezen.',
        'Template "%s" was not found.',
        array(_default_template)
    );
}

// nacist adresy
$_url = Url::current();
$_system_url = Url::parse(Core::$url);
$_url_path = $_url->path;
$_system_url_path = $_system_url->path;

// zkontrolovat aktualni cestu
if (strncmp($_url_path, $_system_url_path, strlen($_system_url_path)) === 0) {
    $_subpath = substr($_url_path, strlen($_system_url_path));

    // presmerovat /index.php na /
    if ($_subpath === '/index.php' && empty($_url->query)) {
        _redirectHeader(Core::$url . '/');
        exit;
    }
} else {
    // neplatna cesta
    header('Content-Type: text/plain; charset=UTF-8');
    _notFoundHeader();

    echo _lang('global.error404.title');
    exit;
}

// konfiguracni pole webu
$_index = array(
    // atributy
    'id' => null, // ciselne ID
    'slug' => null, // identifikator (string)
    'segment' => null, // cast identifikatoru, ktera byla rozpoznana jako segment (string)
    'url' => _link(''), // zakladni adresa
    'title' => null, // titulek - <title>
    'heading' => null, // nadpis - <h1> (pokud je null, pouzije se title)
    'heading_enabled' => true, // vykreslit nadpis 1/0
    'output' => '', // obsah
    'backlink' => null, // url zpetneho odkazu
    'rsslink' => null, // url rss zdroje

    // drobecky spadajici POD aktualni stranku
    // format je: array(array('title' => 'titulek', 'url' => 'url'), ...)
    'crumbs' => array(),

    // typ stranky
    'is_module' => false,
    'is_page' => false,
    'is_plugin' => false,

    // stav stranky
    'is_found' => true, // stranka nebyla nenalezena 1/0
    'is_accessible' => true, // ke strance je pristup 1/0
    'is_guest_only' => false, // stranka je pristupna pouze pro neprihl. uziv 1/0
    'is_rewritten' => false, // skutecny stav prepisu URL
    'is_successful' => false, // uspesny pristup ke strance 1/0

    // presmerovani
    'redirect_to' => null, // adresa, kam presmerovat
    'redirect_to_permanent' => false, // permanentni presmerovani 1/0

    // motiv
    'template_enabled' => true, // pouzit motiv
);


/* ---- priprava obsahu ---- */

Extend::call('index.init', array('index' => &$_index));

$output = &$_index['output'];

if (empty($_POST) || _xsrfCheck()) {
    // zjisteni typu
    if (isset($_GET['m'])) {

        // modul
        $_index['slug'] = _get('m');
        $_index['is_rewritten'] = !$_url->has('m');
        $_index['is_module'] = true;

        Extend::call('mod.init');

        require _root . 'system/action/module.php';

    } elseif (!_login && _notpublicsite) {

        // neverejne stranky
        $_index['is_rewritten'] = _pretty_urls;
        $_index['is_accessible'] = false;

    } else do {

        // stranka / plugin
        if (_pretty_urls && isset($_GET['_rwp'])) {
            // hezka adresa
            $_index['slug'] = _get('_rwp');
            $_index['is_rewritten'] = true;
        } elseif (isset($_GET['p'])) {
            // parametr
            $_index['slug'] = _get('p');
        }

        if ($_index['slug'] !== null) {
            $segments = explode('/', $_index['slug']);
        } else {
            $segments = array();
        }

        if (!empty($segments) && $segments[sizeof($segments) - 1] === '') {
            // presmerovat identifikator/ na identifikator
            $_url->path = rtrim($_url_path, '/');

            $_index['redirect_to'] = $_url->generateAbsolute();
            break;
        }

        // extend
        Extend::call('index.plugin', array(
            'index' => &$_index,
            'segments' => $segments,
        ));

        Extend::call('page.init');

        if ($_index['is_plugin']) {
            break;
        }

        // vykreslit stranku
        $_index['is_page'] = true;
        require _root . 'system/action/page.php';

    } while (false);
} else {
    // spatny XSRF token
    require _root . 'system/action/xsrf_error.php';
}

/* ----  vystup  ---- */

Extend::call('index.prepare', array('index' => &$_index));

// zpracovani stavu
if ($_index['redirect_to'] !== null) {
    // presmerovani
    $_index['template_enabled'] = false;
    _redirectHeader($_index['redirect_to'], $_index['redirect_to_permanent']);
} elseif (!$_index['is_found']) {
    // stranka nenelezena
    require _root . 'system/action/not_found.php';
} elseif (!$_index['is_accessible']) {
    // pristup odepren
    _unauthorizedHeader();
    require _root . 'system/action/login_required.php';
} elseif ($_index['is_guest_only']) {
    // pristup pouze pro neprihl. uziv
    require _root . 'system/action/guest_required.php';
} else {
    // uspesny stav
    $_index['is_successful'] = true;
}

Extend::call('index.ready', array('index' => &$_index));

// vlozeni motivu
if ($_index['template_enabled']) {
    // nacist prvky motivu
    $_template->begin($_template_layout);
    $_template_boxes = $_template->getBoxes($_template_layout);
    $_template_path = $_template->getTemplate($_template_layout);

    Extend::call('index.template', array(
        'path' => &$_template_path,
        'boxes' => &$_template_boxes,
    ));

    // hlavicka
    require _root . 'system/html_start.php';
    _templateHead();

    ?>
</head>
<body<?php echo Extend::buffer('tpl.body_tag') ?>>

<?php require $_template_path ?>
<?php echo Extend::buffer('tpl.end') ?>

</body>
</html>
<?php
}

Extend::call('index.finish', array('index' => $_index));
