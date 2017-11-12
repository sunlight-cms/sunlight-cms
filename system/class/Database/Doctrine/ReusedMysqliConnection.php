<?php

namespace Sunlight\Database\Doctrine;

use Doctrine\DBAL\Driver\Mysqli\MysqliConnection;

class ReusedMysqliConnection extends MysqliConnection
{
    public function __construct(\mysqli $mysqli)
    {
        // hack, but better than copy-pasting the entire MysqliConnection
        $mysqliProp = new \ReflectionProperty(get_parent_class(), '_conn');
        $mysqliProp->setAccessible(true);
        $mysqliProp->setValue($this, $mysqli);
    }
}
