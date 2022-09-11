<?php

return function ($code = '') {
    return '<div class="pre">' . nl2br(_e(trim($code)), false) . '</div>';
};
