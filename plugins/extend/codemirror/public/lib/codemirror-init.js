// hcm mode
CodeMirror.defineMode("hcm", function (config, parserConfig) {
    var hcmOverlay = {
        token: function (stream, state) {
            if (stream.match("[hcm]")) {
                while (stream.next() != null)
                    if (stream.match("[/hcm]")) break;
                return "hcm";
            }
            while (stream.next() != null && !stream.match("[hcm]", false)) {
            }
            return null;
        }
    };
    return CodeMirror.overlayMode(CodeMirror.getMode(config, parserConfig.backdrop), hcmOverlay);
});

$(document).ready(function () {

    // apply to textareas
    $('textarea.editor').each(function () {

        var textarea = $(this);

        // abort if a wysiwyg editor should be used instead
        if (
            'code' !== textarea.data('editorMode')
            && (
                SunlightVars.admin.wysiwygAvailable
                && SunlightVars.admin.wysiwygEnabled
            )
        ) {
            return;
        }

        // remember original height
        var height = textarea.height();

        // determine mode
        var mode;
        var format = textarea.data('editorFormat');
        if ('undefined' !== typeof format) {
            switch (format) {
                case 'xml':
                    mode = 'application/xml';
                    break;
                case 'css':
                    mode = 'text/css';
                    break;
                case 'js':
                case 'json':
                    mode = 'text/javascript';
                    break;
                case 'php':
                    mode = 'application/x-httpd-php';
                    break;
                case 'php-raw':
                    mode = 'text/x-php';
                    break;
                case 'html':
                    mode = {name: 'hcm', backdrop: 'text/html'};
                    break;
            }
        } else {
            mode = {name: 'hcm', backdrop: 'text/html'};
        }

        // abort if no mode has been determined
        if (!mode) {
            return;
        }

        // init the editor
        var editor = CodeMirror.fromTextArea(this, {
            mode: mode,
            theme: SunlightVars.admin.themeIsDark ? 'ambiance' : 'eclipse',
            lineWrapping: true,
            lineNumbers: true,
            indentUnit: 4,
            smartIndent: true,
            electricChars: false,
            tabSize: 4,
            indentWithTabs: false,
            extraKeys: {
                Tab: function (cm) {
                    if (cm.somethingSelected()) {
                        var sel = editor.getSelection("\n");
                        // Indent only if there are multiple lines selected, or if the selection spans a full line
                        if (sel.length > 0 && (sel.indexOf("\n") > -1 || sel.length === cm.getLine(cm.getCursor().line).length)) {
                            cm.indentSelection("add");
                            return;
                        }
                    }

                    cm.execCommand("insertSoftTab");
                }
            },

            // addons
            matchBrackets: true,
            matchTags: true,
            styleActiveLine: true
        });

        editor.setSize(null, height);

    });
});
