<?php

return function ($php_code = '') {
    return '<div class="pre php-source">' . highlight_string($php_code, true) . '</div>';
};
