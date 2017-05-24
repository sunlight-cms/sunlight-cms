Sunlight.admin = (function ($) {
    var fmanTotalFiles = 1;
    var busyOverlayActive = false;
    
    /**
     * Inicializace (ready)
     */
    function initializeOnReady()
    {
        initSubmitButtonMarks();
        initBusyOverlay();
        initHsFieldsets();
    }

    /**
     * Inicializace (load)
     */
    function initializeOnLoad()
    {
        initSortables();
    }
    
    /**
     * Inicializovat oznacovani pouzitych prvku pro odesilani formulare
     */
    function initSubmitButtonMarks()
    {
        var submitButtonSelector = 'input[type=submit], input[type=image] button[type=submit]';

        $(document.body).on('click', submitButtonSelector, function (e) {
            $(submitButtonSelector, e.target.form).removeClass('clicked-submit');
            $(e.target).addClass('clicked-submit');
        });
    }

    /**
     * Inicializovat modalni indikator
     */
    function initBusyOverlay()
    {
        // nacit obrazek spinneru predem
        var spinner = new Image();
        spinner.src = SunlightVars.basePath + 'admin/images/spinner.gif';

        // pri odeslani formulare
        $(document.body).on('submit', 'form', function (e) {
            var usedSubmitButton =  $('.clicked-submit', e.target);
            if (
                !e.target.target
                && (0 === usedSubmitButton.length || !usedSubmitButton.attr('formtarget'))
                && !$(e.target).hasClass('no-busy-overlay')
            ) {
                self.showBusyOverlay(true, 1000);
            }
        });
    }
    
    /**
     * Ziskat objekt modalniho indikatoru
     *
     * @param {Boolean} cancellable povolit zruseni 1/0
     * @returns {jQuery}
     */
    function getBusyOverlay(cancellable)
    {
        var overlay = $('#busy-overlay');

        if (overlay.length < 1) {
            overlay = $('<div id="busy-overlay">'
                + '<div><div>'
                    + '<p></p>'
                    + '<p><img src="' + SunlightVars.basePath + 'admin/images/spinner.gif"></p>'
                    + '<button onclick="void Sunlight.admin.hideBusyOverlay()"></button>'
                + '</div></div>'
                + '</div>')
                .appendTo(document.body)
            ;
            overlay.find('p:first-child').text(SunlightVars.labels.busyOverlayText);
            overlay.find('button').text(SunlightVars.labels.cancel);
        }

        overlay.find('button')[cancellable ? 'show' : 'hide']();

        return overlay;
    }

    /**
     * Inicializovat rozbalovaci fieldsety
     */
    function initHsFieldsets()
    {
        $('fieldset.hs_fieldset legend')
            .addClass('clickable')
            .click(function () {
                var fieldset = $(this).parents('fieldset')[0];
                if (fieldset) {
                    $(':not(legend)', fieldset).toggle();
                }
            })
        ;

        $('fieldset.hs_fieldset :not(legend)').hide();
    }

    /**
     * Inicializovat razeni tazenim
     */
    function initSortables()
    {
        $('.sortable').each(function () {
            self.initSortable(this, $(this).data());
        });
    }
    
    // verejne metody
    var self = {
        /**
         * Zobrazit modalni indikator
         *
         * @param {Boolean} cancellable povolit zruseni 1/0
         * @param {Number}  delay       prodleva pred zobrazenim
         */
        showBusyOverlay: function (cancellable, delay) {
            // get overlay
            var overlay = getBusyOverlay(cancellable);

            // show
            overlay.show();

            // fade-in visible
            if (delay > 0) {
                overlay.delay(delay);
            }
            overlay.queue(function () {
                overlay.addClass('busy-overlay-visible');

                $(this).dequeue();
            });

            // add body class
            $(document.body).addClass('busy-overlay-active');

            busyOverlayActive = true;
        },

        /**
         * Skryt modalni indikator
         *
         * @param {Boolean} stopLoading stop page's loading
         */
        hideBusyOverlay: function (stopLoading) {
            if (undefined === stopLoading) {
                stopLoading = true;
            }

            if (busyOverlayActive) {
                // hide and reset the overlay
                var overlay = getBusyOverlay();
                overlay.hide();
                overlay.removeClass('busy-overlay-visible');

                // remove body class
                $(document.body).removeClass('busy-overlay-active');

                // stop page loading
                if (stopLoading) {
                    if (window.stop) {
                        window.stop();
                    } else {
                        try { document.execCommand('Stop'); }
                        catch (e) {}
                    }
                }

                busyOverlayActive = false;
            }
        },

        /**
         * Soub. manazer - zaskrtnout vse, odskrtnout vse, invertovat
         *
         * @param {Number} number celkovy pocet souboru
         * @param {Number} action akce (1 = zaskrtnout, 2 = odskrtnout, 3 = invertovat)
         */
        fmanSelect: function (number, action) {
            var tmp = 1;
            while (tmp <= number) {
                switch (action) {
                    case 1:
                        document.filelist['f' + tmp].checked = true;
                        break;
                    case 2:
                        document.filelist['f' + tmp].checked = false;
                        break;
                    case 3:
                        document.filelist['f' + tmp].checked = !document.filelist['f' + tmp].checked;
                        break;
                }
                tmp += 1;
            }

            return false;
        },

        /**
         * Soub. manazer - presunout vybrane
         */
        fmanMoveSelected: function () {
            var newdir = prompt(SunlightVars.labels.fmanMovePrompt + ":", '');
            if ('' !== newdir && null !== null) {
                document.filelist.action.value = 'move';
                document.filelist.param.value = newdir;
                document.filelist.submit();
            }

            return false;
        },

        /**
         * Soub. manazer - smazat vybrane
         */
        fmanDeleteSelected: function () {
            if (confirm(SunlightVars.labels.fmanDeleteConfirm)) {
                document.filelist.action.value = 'deleteselected';
                document.filelist.submit();
            }

            return false;
        },

        /**
         * Soub. manazer - pridat vyber do galerie
         */
        fmanAddSelectedToGallery: function () {
            document.filelist.action.value = 'addtogallery_showform';
            document.filelist.submit();

            return false;
        },

        /**
         * Soub. manazer - upload souboru
         */
        fmanAddFile: function () {
            var newfile = document.createElement('span');
            newfile.id = "file" + fmanTotalFiles;
            newfile.innerHTML = "<br /><input type='file' name='upf" + fmanTotalFiles + "[]' multiple /> <a href=\"#\" onclick=\"return Sunlight.admin.fmanRemoveFile(" + fmanTotalFiles + ");\">" + SunlightVars.labels.cancel + "</a>";
            document.getElementById("fmanFiles").appendChild(newfile);
            fmanTotalFiles += 1;

            return false;
        },

        /**
         * Soub. manazer - smazat soubor
         *
         * @param {Number} id
         */
        fmanRemoveFile: function (id) {
            document.getElementById("fmanFiles").removeChild(document.getElementById("file" + id));
        },

        /**
         * Vyhodnoceni SQL - vlozit nazev tabulky
         *
         * @param {HTMLElement} link
         */
        sqlexInsertTableName: function (link) {
            $('textarea[name=sql]').focus().replaceSelectedText($(link).text());
        },

        /**
         * Iniciaizovat razeni v danem prvku
         *
         * @param {HTMLElement} container
         * @param {Object}      options
         */
        initSortable: function (container, options) {
            options = $.extend(
                {},
                {
                    itemSelector: '> *',
                    inputSelector: null,
                    stopperSelector: null,
                    handleSelector: null,
                    sortingClass: 'sorting',
                    placeholder: 'sortable-placeholder',
                    tolerance: null,
                    cancel: null,
                    start: 1,
                    hide: null,
                    autoGrid: false
                },
                options
            );

            if (options.hide) {
                $(options.hide, container).hide();
            }

            if (options.autoGrid) {
                var items = $(options.itemSelector, container);

                if (items.length > 0) {
                    var maxItemWidth = Math.max.apply(Math, items.map(function () { return $(this).width(); }).get());
                    var maxItemHeight = Math.max.apply(Math, items.map(function () { return $(this).height(); }).get());

                    items.css({
                        width: maxItemWidth + 'px',
                        height: maxItemHeight + 'px'
                    });
                }
            }

            if (options.inputSelector) {
                $(container).on('change', options.inputSelector, function () {
                    self.reorderSortable(container, this, options);
                    self.updateSortableInputs(container, options);
                });
            }

            $(container)
                .sortable({
                    items: options.itemSelector,
                    placeholder: options.placeholder,
                    tolerance: options.tolerance,
                    cancel: options.cancel,
                    forceHelperSize: true,
                    cursor: 'move',
                    handle: options.handleSelector || false
                })
                .on('sortstart', function (e, ui) {
                    $(container).addClass(options.sortingClass);
                    $(ui.item).addClass(options.sortingClass);
                })
                .on('sortstop', function (e, ui) {
                    $(container).removeClass(options.sortingClass);
                    $(ui.item).removeClass(options.sortingClass);

                    if (options.inputSelector) {
                        self.updateSortableInputs(container, options);
                    }

                    if ($(ui.item).is('.even, .odd')) {
                        self.updateParityClasses($(options.itemSelector, container));
                    }
                })
            ;
        },

        /**
         * Prepocitat poradova cisla
         *
         * @param {HTMLElement} container
         * @param {Object}      options
         */
        updateSortableInputs: function (container, options) {
            var currentOrd = null;

            $(options.inputSelector, container).each(function () {
                if (
                    null === currentOrd
                    || !options.stopperSelector
                    || 0 !== $(this).parents(options.stopperSelector).length
                ) {
                    var value = parseInt(this.value);

                    if (null === currentOrd) {
                        this.value = currentOrd = options.start;
                    } else if (value > currentOrd) {
                        currentOrd = value;
                    } else {
                        this.value = ++currentOrd;
                    }
                } else {
                    this.value = ++currentOrd;
                }
            });
        },

        /**
         * Seradit polozky
         *
         * @param {HTMLElement} container
         * @param {HTMLElement} changedInput
         * @param {Object}      options
         */
        reorderSortable: function (container, changedInput, options) {
            var items = $(options.itemSelector, container).toArray();

            items.sort(function (a, b) {
                var inputA = $(options.inputSelector, a)[0];
                var inputB = $(options.inputSelector, b)[0];

                if (!inputA) {
                    return -1;
                } else if (!inputB) {
                    return 1;
                }

                var valA = parseInt(inputA.value);
                var valB = parseInt(inputB.value);

                if (valA === valB) {
                    if (inputA === changedInput) {
                        return -1;
                    } else if (inputB === changedInput) {
                        return 1;
                    } else {
                        return 0;
                    }
                }

                return valA > valB ? 1 : -1;
            });

            for (var i = 0; i < items.length; ++i) {
                $(items[i]).appendTo(container);
            }
        },

        /**
         * Aktualizovat even / odd classy
         *
         * @param {jQuery} items
         */
        updateParityClasses: function (items) {
            var odd = false;
            
            $(items).each(function () {
                if (odd) {
                    $(this).addClass('odd').removeClass('even');
                } else {
                    $(this).addClass('even').removeClass('odd');
                }
                odd = !odd;
            });
        }
    };
    
    // inicializace
    $(document).ready(initializeOnReady);
    $(window).load(initializeOnLoad);
    
    return self;
})(jQuery);
