<?php

return function ($kod = '') {
    return '<div class="pre">' . nl2br(_e(trim($kod)), false) . '</div>';
};
