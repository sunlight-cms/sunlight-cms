<?php

use Sunlight\Core;

chdir('../../'); // nasimulovat skript v rootu

require './system/bootstrap.php';
Core::init('./');

echo _parsePost(_e(_post('content')));
