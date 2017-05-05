var Sunlight = (function ($) {
    return {
        /**
         * Aplikovat lightbox funkcionalitu na obrazky a galerie
         */
        galleryScan: function () {
            // implementovano danym komponentem
        },

        /**
         * Aktivovat nebo deaktivovat prvek formulare
         *
         * @param {Boolean} disabled
         * @param {String}  formName
         * @param {String}  fieldName
         */
        toggleFormField: function (disabled, formName, fieldName) {
            document[formName][fieldName].disabled = disabled;
        },

        /**
         * Zaskrtnout/odskrtnout vsechny checkboxy v danem elementu
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
         * Zobrazit potvrzeni akce
         *
         * @returns {Boolean}
         */
        confirm: function () {
            return confirm(SunlightVars.labels.alertConfirm);
        },

        /**
         * Prepsat maskovani zavinace v odkazu
         * Pouziti v ramci "onclick" udalosti.
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
         * Pridat smajlika do daneho formulare a textarey
         *
         * @param {String} formName
         * @param {String} textareaName
         * @param {Number} smileyId
         * @returns {Boolean}
         */
        addSmiley: function (formName, textareaName, smileyId) {
            // get textarea, set focus
            var txtarea = $(document[formName][textareaName]);
            txtarea.focus();

            // insert text
            txtarea.replaceSelectedText(' *' + smileyId + '* ');

            return false;
        },

        /**
         * Pridat BBCode tag do daneho formulare a textarey
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
         * Omezit delku textarey
         * Pouziti v ramci "keyup", "mouseup", "mousedown" udalosti textarey.
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
         * Zobrazit nahled prispevku
         *
         * @param {HTMLElement} button
         * @param {String}      formName
         * @param {String}      areaName
         */
        postPreview: function (button, formName, areaName) {
            var form = document[formName];
            var area = form[areaName];
            var container = $(form).children('p.post-form-preview');

            if (1 !== container.length) {
                // cara
                var hr = document.createElement('div');
                hr.className = 'hr';
                $(hr).appendTo(form);

                // kontejner
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
