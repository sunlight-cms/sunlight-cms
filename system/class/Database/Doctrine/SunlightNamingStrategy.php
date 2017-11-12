<?php

namespace Sunlight\Database\Doctrine;

use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;

class SunlightNamingStrategy extends UnderscoreNamingStrategy
{
    public function classToTableName($className)
    {
        return _dbprefix . parent::classToTableName($className);
    }
}
