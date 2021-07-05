<?php

use Sunlight\Extend;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\Util\StringManipulator;

defined('SL_ROOT') or exit;

$_index->url = Router::module($_index->slug, $_url->getQueryString());

// presmerovani na hezkou verzi adresy
if (Settings::get('pretty_urls') && !$_index->isRewritten) {
    $_url->remove('m');
    $_index->redirect(Router::module($_index->slug, $_url->getQueryString(), true), true);
    return;
}

// nalezeni modulu
$script = null;
if (preg_match('{[a-zA-Z_\-.]+$}AD', $_index->slug)) {
    // test, zda se jedna o systemovy modul
    $systemModule = SL_ROOT . 'system/action/modules/' . $_index->slug . '.php';

    if (is_file($systemModule)) {
        $script = $systemModule;
    } else {
        // systemovy modul nenalezen
        // umoznit implementaci pluginem
        Extend::call('mod.custom.' . $_index->slug, [
            'script' => &$script,
        ]);
    }
}

// spusteni modulu
if ($script !== null) {
    $_index->bodyClasses[] = 't-module';
    $_index->bodyClasses[] = 'm-' . StringManipulator::slugify($_index->slug, true, '_');

    $extend_args = Extend::args($output, ['id' => $_index->slug, 'script' => &$script]);

    Extend::call('mod.all.before', $extend_args);
    Extend::call('mod.' . $_index->slug . '.before', $extend_args);

    $extend_args = Extend::args($output, ['id' => $_index->slug]);

    require $script;

    Extend::call('mod.' . $_index->slug . '.after', $extend_args);
    Extend::call('mod.all.after', $extend_args);
} else {
    $_index->notFound();
}
