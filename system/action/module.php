<?php

use Sunlight\Extend;
use Sunlight\Router;
use Sunlight\Util\StringManipulator;

defined('_root') or exit;

$_index['url'] = Router::module($_index['slug'], $_url->getQueryString());

// presmerovani na hezkou verzi adresy
if (_pretty_urls && !$_index['is_rewritten']) {
    $_url->remove('m');
    $_index['redirect_to'] = Router::module($_index['slug'], $_url->getQueryString(), true);
    $_index['redirect_to_permanent'] = true;
    return;
}

// nalezeni modulu
$_index['is_found'] = false;
if (preg_match('{[a-zA-Z_\-.]+$}AD', $_index['slug'])) {

    // test, zda se jedna o systemovy modul
    $script = _root . 'system/action/modules/' . $_index['slug'] . '.php';
    if (!is_file($script)) {
        // systemovy modul nenalezen
        // umoznit implementaci pluginem
        $script = null;
        Extend::call('mod.custom.' . $_index['slug'], [
            'script' => &$script,
        ]);
    }

    // spusteni modulu
    if ($script !== null) {
        $_index['is_found'] = true;
        $_index['body_classes'][] = 't-module';
        $_index['body_classes'][] = 'm-' . StringManipulator::slugify($_index['slug'], true, '_');

        $extend_args = Extend::args($output, ['id' => $_index['slug'], 'script' => &$script]);

        Extend::call('mod.all.before', $extend_args);
        Extend::call('mod.' . $_index['slug'] . '.before', $extend_args);

        $extend_args = Extend::args($output, ['id' => $_index['slug']]);

        require $script;
        
        Extend::call('mod.' . $_index['slug'] . '.after', $extend_args);
        Extend::call('mod.all.after', $extend_args);
    }
}
