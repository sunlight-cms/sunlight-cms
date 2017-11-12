<?php

namespace Sunlight\Database\Doctrine;

use Doctrine\DBAL\Logging\SQLLogger;
use Sunlight\Extend;

class SunlightSqlLogger implements SQLLogger
{
    /** @var string|null */
    protected $currentQuery;

    public function startQuery($sql, array $params = null, array $types = null)
    {
        $this->currentQuery = $sql;

        Extend::call('db.query', array('sql' => $sql));
    }

    public function stopQuery()
    {
        $sql = $this->currentQuery;
        $this->currentQuery = null;

        Extend::call('db.query.post', array(
            'sql' => $sql,
            'result' => 'unknown',
            'exception' => null,
        ));
    }
}
