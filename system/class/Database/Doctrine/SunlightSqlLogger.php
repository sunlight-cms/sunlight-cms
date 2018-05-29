<?php

namespace Sunlight\Database\Doctrine;

use Doctrine\DBAL\Logging\SQLLogger;
use Sunlight\Extend;

class SunlightSqlLogger implements SQLLogger
{
    /** @var string|null */
    protected $currentQuery;

    function startQuery($sql, array $params = null, array $types = null)
    {
        $this->currentQuery = $sql;

        Extend::call('db.query', array('sql' => $sql));
    }

    function stopQuery()
    {
        $sql = $this->currentQuery;
        $this->currentQuery = null;

        Extend::call('db.query.after', array(
            'sql' => $sql,
            'result' => 'unknown',
            'exception' => null,
        ));
    }
}
