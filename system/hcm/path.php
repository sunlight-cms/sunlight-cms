<?php

use Sunlight\Core;

return function () {
    return _e(Core::getCurrentUrl()->getPath());
};
