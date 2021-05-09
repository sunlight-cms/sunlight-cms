<?php

use Sunlight\Util\Url;

return function () {
    return _e(Url::current()->path);
};
