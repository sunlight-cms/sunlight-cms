<?php

use Sunlight\Extend;
use Sunlight\Util\StringHelper;

defined('SL_ROOT') or exit;

// locate module
$module = null;
$script = null;

if (preg_match('{m/([a-zA-Z_\-.]+)$}AD', $_index->slug, $match)) {
    $module = $match[1];
    $_index->url = clone $_url;

    // check if it's a system module
    $systemModule = SL_ROOT . 'system/action/modules/' . $module . '.php';

    if (is_file($systemModule)) {
        $script = $systemModule;
    } else {
        // not a system module, allow plugin implementation
        Extend::call('mod.custom.' . $module, [
            'script' => &$script,
        ]);
    }
}

// run module
if (isset($module, $script)) {
    $_index->bodyClasses[] = 't-module';
    $_index->bodyClasses[] = 'm-' . StringHelper::slugify($module, ['extra' => '_']);

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
