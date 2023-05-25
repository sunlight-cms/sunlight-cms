<?php

namespace SunlightExtend\Codemirror;

use Sunlight\Plugin\ExtendPlugin;
use Sunlight\Settings;
use Sunlight\User;

class CodemirrorPlugin extends ExtendPlugin
{
    private const SUPPORTED_FORMATS = [
        'xml' => true,
        'css' => true,
        'js' => true,
        'json' => true,
        'php' => true,
        'php-raw' => true,
        'html' => true,
    ];

    function onAdminEditor(array $args): void
    {
        global $_admin;

        if (
            // format is supported
            isset(self::SUPPORTED_FORMATS[$args['options']['format']])

            // and should use a code editor and not a wysiwyg editor
            && (
                $args['options']['mode'] === 'code'
                || !$_admin->wysiwygAvailable
                || !User::isLoggedIn()
                || !User::$data['wysiwyg']
            )
        ) {
            $this->enableEventGroup('codemirror');
        }
    }

    function onAdminHead(array $args): void
    {
        $basePath = $this->getWebPath() . '/public';

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
        $args['js']['codemirror_overlay'] = $basePath . '/addon/mode/overlay.js';
        $args['js']['codemirror_search'] = $basePath . '/addon/search/search.js';
        $args['js']['codemirror_searchcursor'] = $basePath . '/addon/search/searchcursor.js';
        $args['js']['codemirror_dialog'] = $basePath . '/addon/dialog/dialog.js';
        $args['js']['codemirror_activeline'] = $basePath . '/addon/selection/active-line.js';
        $args['js']['codemirror_matchbrackets'] = $basePath . '/addon/edit/matchbrackets.js';
        $args['js']['codemirror_init'] = $basePath . '/lib/codemirror-init.js';
    }

    function onAdminStyle(array $args): void
    {
        $args['output'] .= "/* codemirror */\n";
        $args['output'] .= "div.CodeMirror {\n";

        if ($GLOBALS['dark']) {
            $args['output'] .= "border: 1px solid {$GLOBALS['scheme_smoke_dark']};\n";
        } else {
            $args['output'] .= "outline: 1px solid  {$GLOBALS['scheme_white']};\n";
            $args['output'] .= "border-width: 1px;\n";
            $args['output'] .= "border-style: solid;\n";
            $args['output'] .= "border-color: {$GLOBALS['scheme_smoke_dark']} {$GLOBALS['scheme_smoke']} {$GLOBALS['scheme_smoke']} {$GLOBALS['scheme_smoke_dark']};\n";
        }

        $args['output'] .= "line-height: 1.5;\n";
        $args['output'] .= "cursor: text;\n";
        $args['output'] .= "background-color: #fff;\n";
        $args['output'] .= "}\n";
        $args['output'] .= 'div.CodeMirror span.cm-hcm {color: ' . ($GLOBALS['dark'] ? '#ff0' : '#f60') . ";}\n";
    }
}
