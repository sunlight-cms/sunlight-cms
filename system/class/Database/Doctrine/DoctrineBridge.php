<?php

namespace Sunlight\Database\Doctrine;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\EventManager;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\Common\Proxy\AbstractProxyFactory;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Mysqli\Driver;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Mapping\Driver\SimplifiedXmlDriver;
use Doctrine\ORM\Mapping\Driver\SimplifiedYamlDriver;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\Tools\Setup;
use Sunlight\Core;
use Sunlight\Extend;

abstract class DoctrineBridge
{
    /**
     * @param \mysqli $mysqli
     * @return EntityManager
     */
    public static function createEntityManager(\mysqli $mysqli)
    {
        if (!Core::isReady()) {
            throw new \LogicException('Cannot use Doctrine bridge before full system initialization');
        }

        $eventManager = new EventManager();
        $eventManager->addEventListener(Events::loadClassMetadata, new ClassMetadataListener());

        $mysqliConnection = new ReusedMysqliConnection($mysqli);
        $connection = new Connection(array('pdo' => $mysqliConnection), new Driver(), null, $eventManager);
        $connection->getConfiguration()->setSQLLogger(new SunlightSqlLogger());

        $cache = new SunlightCacheAdapter(Core::$cache->getNamespace('doctrine.'));
        $metadataDriver = new MappingDriverChain();

        $config = Setup::createConfiguration(false, _root . 'system/cache/doctrine-proxy', $cache);
        $config->setMetadataDriverImpl($metadataDriver);
        $config->setNamingStrategy(new UnderscoreNamingStrategy());
        $config->setAutoGenerateProxyClasses(AbstractProxyFactory::AUTOGENERATE_FILE_NOT_EXISTS);

        Extend::call('doctrine.init', array(
            'connection' => $connection,
            'config' => $config,
            'metadata_driver' => $metadataDriver,
            'event_manager' => $eventManager,
        ));

        $mapping = array();
        Extend::call('doctrine.map_entities', array('mapping' => &$mapping));

        $drivers = array();

        if (!empty($mapping['annotation'])) {
            $drivers += static::createAnnotationDrivers($mapping['annotation']);
        }
        if (!empty($mapping['yaml'])) {
            $drivers += static::createYamlDrivers($mapping['yaml']);
        }
        if (!empty($mapping['xml'])) {
            $drivers += static::createXmlDrivers($mapping['xml']);
        }

        foreach ($drivers as $namespace => $driver) {
            $metadataDriver->addDriver($driver, $namespace);
        }

        return EntityManager::create($connection, $config, $eventManager);
    }

    /**
     * @param array $entityNamespaceToPaths
     * @return array
     */
    protected static function createAnnotationDrivers(array $entityNamespaceToPaths)
    {
        AnnotationRegistry::registerFile(_root . 'vendor/doctrine/orm/lib/Doctrine/ORM/Mapping/Driver/DoctrineAnnotations.php');

        $driver = new AnnotationDriver(new AnnotationReader(), static::getEntityPaths($entityNamespaceToPaths));

        return static::mapDriverToEntities($driver, $entityNamespaceToPaths);
    }

    /**
     * @param array $entityNamespaceToPaths
     * @return array
     */
    protected static function createYamlDrivers(array $entityNamespaceToPaths)
    {
        $driver = new SimplifiedYamlDriver(static::getEntityPathToNamespace($entityNamespaceToPaths), '.yml');

        return static::mapDriverToEntities($driver, $entityNamespaceToPaths);
    }

    /**
     * @param array $entityNamespaceToPaths
     * @return array
     */
    protected static function createXmlDrivers(array $entityNamespaceToPaths)
    {
        $driver = new SimplifiedXmlDriver(static::getEntityPathToNamespace($entityNamespaceToPaths), '.xml');

        return static::mapDriverToEntities($driver, $entityNamespaceToPaths);
    }

    /**
     * @param array $entityNamespaceToPaths
     * @return string[]
     */
    protected static function getEntityPaths(array $entityNamespaceToPaths)
    {
        $paths = array();

        foreach ($entityNamespaceToPaths as $entityPaths) {
            foreach ((array) $entityPaths as $entityPath) {
                $paths[] = $entityPath;
            }
        }

        return $paths;
    }

    /**
     * @param array $entityNamespaceToPaths
     * @return array
     */
    protected static function getEntityPathToNamespace(array $entityNamespaceToPaths)
    {
        $entityPathToNamespace = array();

        foreach ($entityNamespaceToPaths as $namespace => $entityPaths) {
            foreach ((array) $entityPaths as $entityPath) {
                $entityPathToNamespace[$entityPath] = $namespace;
            }
        }

        return $entityPathToNamespace;
    }

    /**
     * @param MappingDriver $driver
     * @param array         $entityNamespaceToPaths
     * @return array
     */
    protected static function mapDriverToEntities(MappingDriver $driver, array $entityNamespaceToPaths)
    {
        return array_fill_keys(array_keys($entityNamespaceToPaths), $driver);
    }
}
