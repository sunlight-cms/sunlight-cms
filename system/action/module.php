<?php

use Sunlight\Extend;
use Sunlight\Util\StringManipulator;

defined('SL_ROOT') or exit;

// nalezeni modulu
$module = null;
$script = null;

if (preg_match('{m/([a-zA-Z_\-.]+)$}AD', $_index->slug, $match)) {
    $module = $match[1];
    $_index->url = clone $_url;

    // test, zda se jedna o systemovy modul
    $systemModule = SL_ROOT . 'system/action/modules/' . $module . '.php';

    if (is_file($systemModule)) {
        $script = $systemModule;
    } else {
        // systemovy modul nenalezen
        // umoznit implementaci pluginem
        Extend::call('mod.custom.' . $module, [
            'script' => &$script,
        ]);
    }
}

// spusteni modulu
if (isset($module, $script)) {
    $_index->bodyClasses[] = 't-module';
    $_index->bodyClasses[] = 'm-' . StringManipulator::slugify($module, true, '_');

    $extend_args = Extend::args($output, ['id' => $module, 'script' => &$script]);

    Extend::call('mod.all.before', $extend_args);
    Extend::call('mod.' . $_index->slug . '.before', $extend_args);

    $extend_args = Extend::args($output, ['id' => $module]);

    require $script;

    Extend::call('mod.' . $module . '.after', $extend_args);
    Extend::call('mod.all.after', $extend_args);
} else {
    $_index->notFound();
}
