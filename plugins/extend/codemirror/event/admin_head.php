<?php

use Sunlight\Settings;

return function (array $args) {
    $basePath = $this->getAssetPath('public');

    $args['css']['codemirror'] = $basePath . '/lib/codemirror.css';
    $args['css']['codemirror_theme'] = $basePath . '/theme/' . (Settings::get('adminscheme_dark') ? 'ambiance' : 'eclipse') . '.css';
    $args['css']['codemirror_dialog'] = $basePath . '/addon/dialog/dialog.css';

    $args['js']['codemirror'] = $basePath . '/lib/codemirror.js';
    $args['js']['codemirror_css'] = $basePath . '/mode/css/css.js';
    $args['js']['codemirror_htmlmixed'] = $basePath . '/mode/htmlmixed/htmlmixed.js';
    $args['js']['codemirror_javascript'] = $basePath . '/mode/javascript/javascript.js';
    $args['js']['codemirror_clike'] = $basePath . '/mode/clike/clike.js';
    $args['js']['codemirror_php'] = $basePath . '/mode/php/php.js';
    $args['js']['codemirror_sql'] = $basePath . '/mode/sql/sql.js';
    $args['js']['codemirror_xml'] = $basePath . '/mode/xml/xml.js';
    $args['js']['codemirror_overlay'] = $basePath . '/addon/mode/multiplex.js';
    $args['js']['codemirror_search'] = $basePath . '/addon/search/search.js';
    $args['js']['codemirror_searchcursor'] = $basePath . '/addon/search/searchcursor.js';
    $args['js']['codemirror_dialog'] = $basePath . '/addon/dialog/dialog.js';
    $args['js']['codemirror_activeline'] = $basePath . '/addon/selection/active-line.js';
    $args['js']['codemirror_matchbrackets'] = $basePath . '/addon/edit/matchbrackets.js';
    $args['js']['codemirror_init'] = $basePath . '/lib/codemirror-init.js';
};
