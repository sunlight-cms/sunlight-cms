<?php

return function (array $args) {
    $this->enableEventGroup('lightbox');

    $args['output'] .= " data-lightbox='" . $args['group'] . "'";
};
