<?php

use Sunlight\Core;
use Sunlight\Post\Post;
use Sunlight\Util\Request;

require __DIR__ . '/../bootstrap.php';
Core::init();

echo Post::render(_e(Request::post('content'), ''));
