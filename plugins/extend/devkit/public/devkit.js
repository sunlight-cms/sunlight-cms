jQuery(document).ready(function ($) {

    var toolbarHeight = 38;
    var currentContent = null;

    /**
     * Show content
     *
     * @param content
     */
    function showContent(content)
    {
        if (currentContent !== null && content.get(0) !== currentContent.get(0)) {
            currentContent.hide();
        }
        content.show();
        currentContent = content;
        updateContentHeight();

        $(document.body).addClass('devkit-content-open');
    }

    /**
     * Hide content
     *
     * @param content
     */
    function hideContent(content)
    {
        content.hide();
        currentContent = null;

        $(document.body).removeClass('devkit-content-open');
    }

    /**
     * Update current content's height
     */
    function updateContentHeight()
    {
        if (currentContent !== null) {
            currentContent.height($(window).height() - toolbarHeight);
        }
    }

    /**
     * Update body margins
     */
    function updateBodyMargins()
    {
        var body = $(document.body);

        if (isOpen()) {
            body.css('marginBottom', (toolbarHeight + parseInt($(body).css('marginBottom'))) + 'px');
        } else {
            body.css('marginBottom', '');
        }
    }

    /**
     * Set cookie
     */
    function setCookie(name, value)
    {
        document.cookie = name + '=' + encodeURIComponent(value) + ';path=' + SunlightVars.basePath + ';SameSite=Lax';
    }

    /**
     * Close the toolbar
     */
    function close()
    {
        setCookie('sl_devkit_toolbar', 'closed');
        if (currentContent !== null) {
            hideContent(currentContent);
        }
        $('#devkit-toolbar')
            .addClass('devkit-toolbar-closed')
            .removeClass('devkit-toolbar-open');

        updateBodyMargins();
    }

    /**
     * Open the toolbar
     */
    function open()
    {
        setCookie('sl_devkit_toolbar', 'open');
        $('#devkit-toolbar')
            .addClass('devkit-toolbar-open')
            .removeClass('devkit-toolbar-closed');

        updateBodyMargins();
    }

    /**
     * See if the toolbar is open
     */
    function isOpen()
    {
        return $('#devkit-toolbar').hasClass('devkit-toolbar-open');
    }

    // toggleable
    $('#devkit-toolbar > div.devkit-toggleable').click(function () {
        var content = $(this).next('div.devkit-content');
        if (content.is(':visible')) {
            hideContent(content);
        } else {
            showContent(content);
        }

        return false;
    });

    // selectable
    $('#devkit-toolbar .devkit-selectable').focus(function () {
        var selectable = $(this);
        setTimeout(function () { selectable.select(); }, 100);
    });

    // update content height on window resize
    $(window).resize(updateContentHeight);

    // hide/show content
    $('#devkit-toolbar .devkit-hideshow').click(function () {
        var content;

        if (this.hasAttribute('data-target')) {
            content = $(this.getAttribute('data-target'));
        } else {
            content = $(this).next('.devkit-hideshow-target');
        }

        if (content.length > 0) {
            content.toggle(0);
        }

        return false;
    });

    // close and open button
    $('#devkit-toolbar > div.devkit-close').click(close);
    $('#devkit-toolbar > div.devkit-open').click(open);

    // close on doubleclick on the bar
    $('#devkit-toolbar').on('dblclick', function (e) {
        if ($(e.target).is('#devkit-toolbar')) {
            close();
        }
    });

    // initial margin update
    updateBodyMargins();

});
