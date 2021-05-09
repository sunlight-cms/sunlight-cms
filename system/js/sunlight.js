var Sunlight = (function ($) {
    var matchHtmlRegExp;

    return {
        /**
         * Replace special characters by HTML entities
         *
         * @param {String} string
         * @return {String}
         */
        escapeHtml: function (string) {
            if (!matchHtmlRegExp) {
                matchHtmlRegExp = /["'&<>]/;
            }

            var str = '' + string;
            var match = matchHtmlRegExp.exec(str);

            if (!match) {
                return str;
            }

            var escape;
            var html = '';
            var index;
            var lastIndex = 0;

            for (index = match.index; index < str.length; ++index) {
                switch (str.charCodeAt(index)) {
                    case 34: // "
                        escape = '&quot;';
                        break;
                    case 38: // &
                        escape = '&amp;';
                        break;
                    case 39: // '
                        escape = '&#39;';
                        break;
                    case 60: // <
                        escape = '&lt;';
                        break;
                    case 62: // >
                        escape = '&gt;';
                        break;
                    default:
                        continue;
                }

                if (lastIndex !== index) {
                    html += str.substring(lastIndex, index);
                }

                lastIndex = index + 1;
                html += escape;
            }

            return lastIndex !== index
                ? html + str.substring(lastIndex, index)
                : html;
        },

        /**
         * Render system message box
         *
         * @param {String}  type   ok / warn / err
         * @param {String}  text   message text
         * @param {Boolean} isHtml render text as HTML
         * @return {HTMLElement}
         */
        msg: function (type, text, isHtml) {
            var msg = document.createElement('div');

            $(msg).addClass('message message-' + type);
            $(msg)[isHtml ? 'html' : 'text'](text);

            return msg;
        },

        /**
         * Activate or deactivate a form field
         *
         * @param {Boolean} disabled
         * @param {String}  formName
         * @param {String}  fieldName
         */
        toggleFormField: function (disabled, formName, fieldName) {
            document[formName][fieldName].disabled = disabled;
        },

        /**
         * Check or uncheck all checkboxes inside the given element
         *
         * @param {MouseEvent}  event
         * @param {boolean}     checked
         * @param {HTMLElement} element
         * @param {string}      selector
         */
        checkAll: function (event, checked, element, selector) {
            var invert = (event.shiftKey || event.ctrlKey || event.altKey);
            selector = selector || 'input[type=checkbox][name]';

            if (invert) {
                var checkboxes = $(selector, element);
                for (var i = 0; i < checkboxes.length; ++i) {
                    checkboxes[i].checked = !checkboxes[i].checked;
                }
            } else {
                $(selector, element).prop('checked', checked);
            }
        },

        /**
         * Show a confirmation dialog
         *
         * @returns {Boolean}
         */
        confirm: function () {
            return confirm(SunlightVars.labels.alertConfirm);
        },

        /**
         * Undo email address symbol replacement in a link
         *
         * @param {HTMLLinkElement} f
         * @returns {Boolean}
         */
        mai_lto: function (f) {
            var addr = f.innerHTML.replace(SunlightVars.settings.atReplace, '@');
            f.href = 'mai' + 'lt' + 'o:' + addr;

            return true;
        },

        /**
         * Add BBCode tag to the given textarea
         *
         * @param {String}  fid
         * @param {String}  aid
         * @param {String}  tag
         * @param {Boolean} pair
         * @returns {Boolean}
         */
        addBBCode: function (fid, aid, tag, pair) {
            // get textarea, set focus
            var txtarea = $(document[fid][aid]);
            txtarea.focus();

            var text = txtarea.extractSelectedText(); // get selected text
            var text = '[' + tag + ']' + (pair ? text + '[/' + tag + ']' : ''); // process text

            // insert text
            txtarea.insertText(text, txtarea.getSelection().start, true);
            if (pair) {
                var pos = txtarea.getSelection().start - 3 - tag.length;
                txtarea.setSelection(pos, pos);
            }

            return false;
        },

        /**
         * Limit textarea length
         *
         * @param {HTMLElement} area
         * @param {Number}      limit
         */
        limitTextarea: function (area, limit) {
            var text = $(area).val();
            if (text.length > limit) {
                $(area).val(text.substr(0, limit));
                $(area).focus();
                area.scrollTop = area.scrollHeight;
            }
        },

        /**
         * Show post preview
         *
         * @param {HTMLElement} button
         * @param {String}      formName
         * @param {String}      areaName
         */
        postPreview: function (button, formName, areaName) {
            var form = document[formName];
            var area = form[areaName];
            var container = $(form).children('p.post-form-preview');

            if (container.length !== 1) {
                // cara
                var hr = document.createElement('div');
                hr.className = 'hr';
                $(hr).appendTo(form);

                // container
                container = document.createElement('p');
                container.className = 'post-form-preview';
                container = $(container).appendTo(form);
            } else {
                container.empty();
            }

            $(button).attr('disabled', true);
            $(document.createTextNode(SunlightVars.labels.loading)).appendTo(container);

            container.load(
                SunlightVars.basePath + 'system/script/postprev.php?current_template=' + SunlightVars.currentTemplate + '&_' + (new Date().getTime()),
                {content: $(area).val()},
                function () {
                    $(button).attr('disabled', false);
                }
            );
        }
    };
})(jQuery);
