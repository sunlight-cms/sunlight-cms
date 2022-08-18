<?php

return function ($kod = '') {
    return '<div class="pre php-source">' .highlight_string($kod, true) . '</div>';
};
