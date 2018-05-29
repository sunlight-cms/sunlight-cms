<?php

use Sunlight\Core;

chdir('../../'); // nasimulovat skript v rootu

require './system/bootstrap.php';
Core::init('./');

echo \Sunlight\Post::render(_e(\Sunlight\Util\Request::post('content')));
