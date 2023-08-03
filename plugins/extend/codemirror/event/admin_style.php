<?php

return function (array $args) {
    $args['output'] .= "/* codemirror */\n";
    $args['output'] .= "div.CodeMirror {\n";
    $args['output'] .= "resize: vertical;\n";

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
};
