<?php

use Sunlight\Core;
use Sunlight\Comment\Comment;
use Sunlight\Util\Request;

require '../bootstrap.php';
Core::init('../../');

echo Comment::render(_e(Request::post('content')));
