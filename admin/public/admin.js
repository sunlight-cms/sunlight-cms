Sunlight.admin = (function ($) {
    var fmanAddedFiles = 1;
    var busyOverlayActive = false;
    
    /**
     * Initialize on ready
     */
    function initializeOnReady()
    {
        initBusyOverlay();
        initHsFieldsets();
    }

    /**
     * Initialize on load
     */
    function initializeOnLoad()
    {
        initSortables();
    }

    /**
     * Initialize busy overlay
     */
    function initBusyOverlay()
    {
        // preload spinner image
        var spinner = new Image();
        spinner.loading = 'eager';
        spinner.src = SunlightVars.basePath + 'admin/public/images/spinner.gif';

        // show after form submit
        $(document.body).on('submit', 'form', function (e) {
            var usedSubmitButton = e.originalEvent.submitter;
            if (
                !e.isDefaultPrevented()
                && !e.target.target
                && (!usedSubmitButton || !usedSubmitButton.formTarget)
                && !$(e.target).hasClass('no-busy-overlay')
            ) {
                self.showBusyOverlay(true, 1000);
            }
        });
    }
    
    /**
     * Get busy overlay object
     *
     * @param {Boolean} cancellable allow cancellation 1/0
     * @returns {jQuery}
     */
    function getBusyOverlay(cancellable)
    {
        var overlay = $('#busy-overlay');

        if (overlay.length < 1) {
            overlay = $('<div id="busy-overlay">'
                + '<div><div>'
                    + '<p></p>'
                    + '<p><img src="' + SunlightVars.basePath + 'admin/public/images/spinner.gif"></p>'
                    + '<button onclick="void Sunlight.admin.hideBusyOverlay()"></button>'
                + '</div></div>'
                + '</div>')
                .appendTo(document.body);
            overlay.find('p:first-child').text(SunlightVars.labels.busyOverlayText);
            overlay.find('button').text(SunlightVars.labels.cancel);
        }

        overlay.find('button')[cancellable ? 'show' : 'hide']();

        return overlay;
    }

    /**
     * Initialize hide-show fieldsets
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
            });

        $('fieldset.hs_fieldset :not(legend)').hide();
    }

    /**
     * Initialize sortable elements
     */
    function initSortables()
    {
        $('.sortable').each(function () {
            self.initSortable(this, $(this).data());
        });
    }
    
    // public methods
    var self = {
        /**
         * Show busy overlay
         *
         * @param {Boolean} cancellable allow cancellation 1/0
         * @param {Number}  delay       show delay
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
         * Hide busy overlay
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
         * Index - add a message
         *
         * @param {HTMLElement} msg
         */
        indexAddMessage: function (msg) {
            $(msg).insertAfter('#index-messages > h2:first-child');
            $('#index-messages').show();
        },

        indexCheckHtaccess: function (testUrl, failureMessage) {
            $.ajax({
                url: testUrl,
                dataType: 'text',
                cache: false,
                success: function () {
                    self.indexAddMessage(Sunlight.msg('err', failureMessage, true));
                }
            });
        },

        /**
         * File manager - select / unselect / invert file selections
         *
         * @param {Number} number total number of files
         * @param {Number} action action (1 = check, 2 = uncheck, 3 = invert)
         */
        fmanSelect: function (number, action) {
            var counter = 1;
            while (counter <= number) {
                switch (action) {
                    case 1:
                        document.filelist['file_' + counter].checked = true;
                        break;
                    case 2:
                        document.filelist['file_' + counter].checked = false;
                        break;
                    case 3:
                        document.filelist['file_' + counter].checked = !document.filelist['file_' + counter].checked;
                        break;
                }
                counter += 1;
            }

            return false;
        },

        /**
         * File manager - move selected files
         */
        fmanMoveSelected: function () {
            var newdir = prompt(SunlightVars.labels.fmanMovePrompt + ":", '');
            if (newdir !== '' && newdir !== null) {
                document.filelist.action.value = 'move';
                document.filelist.param.value = newdir;
                document.filelist.submit();
            }

            return false;
        },

        /**
         * File manager - delete selected files
         */
        fmanDeleteSelected: function () {
            if (confirm(SunlightVars.labels.fmanDeleteConfirm)) {
                document.filelist.action.value = 'deleteselected';
                document.filelist.submit();
            }

            return false;
        },

        /**
         * File manager - download selected files
         */
        fmanDownloadSelected: function () {
                document.filelist.action.value = 'downloadselected';
                document.filelist.submit();

            return false;
        },

        /**
         * File manager - add selected files to a gallery
         */
        fmanAddSelectedToGallery: function () {
            document.filelist.action.value = 'addtogallery_showform';
            document.filelist.submit();

            return false;
        },

        /**
         * File manager - add file upload input
         */
        fmanAddFile: function () {
            var newfile = document.createElement('span');
            newfile.id = 'uploaded_file_' + fmanAddedFiles;
            newfile.innerHTML = '<br><input type="file" name="uploaded_file_' + fmanAddedFiles + '[]" multiple>'
                + ' <a href="#" onclick="return Sunlight.admin.fmanRemoveFile(' + fmanAddedFiles + ');">'
                + SunlightVars.labels.cancel
                + '</a>';
            document.getElementById("fmanFiles").appendChild(newfile);
            ++fmanAddedFiles;

            return false;
        },

        /**
         * File manager - remove selected file
         *
         * @param {Number} id
         */
        fmanRemoveFile: function (id) {
            document.getElementById("fmanFiles").removeChild(document.getElementById("uploaded_file_" + id));
        },

        /**
         * SQL executor - insert table name
         *
         * @param {HTMLElement} link
         */
        sqlexInsertTableName: function (link) {
            $('textarea[name=sql]').focus().replaceSelectedText($(link).text());
        },

        /**
         * Initialize sortable elements
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
                    handleSelectorNoTouch: null,
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
                    self.reorderSortableByInputValues(container, this, options);
                    self.updateSortableInputs(container, options);
                });
            }

            var handles = [];

            if (options.handleSelector) {
                handles.push(options.handleSelector);
            }

            if (options.handleSelectorNoTouch && !window.matchMedia('(pointer: coarse)').matches) {
                handles.push(options.handleSelectorNoTouch);
            }

            $(container)
                .sortable({
                    items: options.itemSelector,
                    placeholder: options.placeholder,
                    tolerance: options.tolerance,
                    cancel: options.cancel,
                    forceHelperSize: true,
                    cursor: 'move',
                    handle: handles.length > 0 ? handles.join(', ') : false
                })
                .on('sortstart', function (e, ui) {
                    $(container).addClass(options.sortingClass);
                    $(ui.item).addClass(options.sortingClass);
                    $(ui.placeholder).height($(ui.item).height());
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
                });
        },

        /**
         * Recalculate and update sortable input values
         *
         * @param {HTMLElement} container
         * @param {Object}      options
         */
        updateSortableInputs: function (container, options) {
            var currentOrd = null;

            $(options.inputSelector, container).each(function () {
                if (options.stopperSelector && $(this).parents(options.stopperSelector).length !== 0) {
                    currentOrd = parseInt(this.value, 10) || currentOrd;
                } else if (currentOrd === null) {
                    currentOrd = options.start;
                    this.value = currentOrd.toString();
                } else {
                    this.value = (++currentOrd).toString();
                }
            });
        },

        /**
         * Order sortable elements using input values
         *
         * @param {HTMLElement} container
         * @param {HTMLElement} changedInput
         * @param {Object}      options
         */
        reorderSortableByInputValues: function (container, changedInput, options) {
            var items = $(options.itemSelector, container).toArray();

            var sortedItems = items.slice(0);

            sortedItems.sort(function (a, b) {
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
                        return a === Sunlight.admin.determineWhichItemComesFirst(items, a, b) ? 1 : -1;
                    } else if (inputB === changedInput) {
                        return b === Sunlight.admin.determineWhichItemComesFirst(items, a, b) ? -1 : 1;
                    } else {
                        return 0;
                    }
                }

                return valA > valB ? 1 : -1;
            });

            for (var i = 0; i < sortedItems.length; ++i) {
                $(sortedItems[i]).appendTo(container);
            }
        },

        /**
         * Determine which item is found first in a list
         *
         * @param {Array} list
         * @param {*}     a
         * @param {*}     b
         * @returns {*} a, b or undefined
         */
        determineWhichItemComesFirst: function (list, a, b) {
            for (var i = 0; i < list.length; ++i) {
                if (a === list[i]) {
                    return a;
                }
                if (b === list[i]) {
                    return b;
                }
            }
        },

        /**
         * Update odd / even classes
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
        },

        /**
         * @param {NodeList} checkboxes
         * @param {Boolean}  checked
         */
        toggleCheckboxes: function (checkboxes, checked) {
            for (var i = 0; i < checkboxes.length; ++i) {
                if (!checkboxes[i].disabled) {
                    checkboxes[i].checked = checked;
                }
            }
        }
    };
    
    // initialize
    $(document).ready(initializeOnReady);
    $(window).load(initializeOnLoad);
    
    return self;
})(jQuery);
