<?php

namespace Sunlight\Database\Doctrine;

use Doctrine\ORM\EntityRepository;
use Sunlight\Database\Database;

/**
 * Base entity class
 *
 * Implements a basic Active Record pattern using Database::getEntityManager().
 */
class Entity
{
    /**
     * Make this entity managed and persistent
     */
    function persist()
    {
        static::getEntityManager()->persist($this);
    }

    /**
     * Persist and flush this entity to the database
     */
    function save()
    {
        $this->persist();
        static::getEntityManager()->flush($this);
    }

    /**
     * Delete and flush this entity from the database
     */
    function delete()
    {
        static::getEntityManager()->remove($this);
        static::getEntityManager()->flush($this);
    }

    /**
     * @param mixed $id
     * @return static|null
     */
    static function find($id)
    {
        return static::getEntityManager()->find(get_called_class(), $id);
    }

    /**
     * Get repository for this entity class
     *
     * @return EntityRepository
     */
    static function getRepository()
    {
        return static::getEntityManager()->getRepository(get_called_class());
    }

    /**
     * @return \Doctrine\ORM\EntityManager
     */
    protected static function getEntityManager()
    {
        return Database::getEntityManager();
    }
}
