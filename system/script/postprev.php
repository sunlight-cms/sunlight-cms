<?php

use Sunlight\Core;
use Sunlight\Comment\Comment;
use Sunlight\Util\Request;

chdir(__DIR__ . '/../../'); // nasimulovat skript v rootu

require './system/bootstrap.php';
Core::init('./');

echo Comment::render(_e(Request::post('content')));
