<?php

chdir('../../'); // nasimulovat skript v rootu

require './system/bootstrap.php';
Sunlight\Core::init('./');

echo _parsePost(_e(_post('content')));
