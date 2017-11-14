<?php

namespace Sunlight\Database\Doctrine;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

class ClassMetadataListener
{
    public function loadClassMetadata(LoadClassMetadataEventArgs $args)
    {
        /** @var ClassMetadataInfo $classMetadata */
        $classMetadata = $args->getClassMetadata();

        if (!$classMetadata->isRootEntity() || $classMetadata->isInheritanceTypeSingleTable()) {
            return;
        }

        $classMetadata->setTableName(_dbprefix . $classMetadata->getTableName());

        foreach ($classMetadata->getAssociationMappings() as $fieldName => $mapping) {
            if ($mapping['type'] == ClassMetadataInfo::MANY_TO_MANY) {
                if (!empty($classMetadata->associationMappings[$fieldName]['joinTable'])) {
                    $mappedTableName = $classMetadata->associationMappings[$fieldName]['joinTable']['name'];
                    $classMetadata->associationMappings[$fieldName]['joinTable']['name'] = _dbprefix . $mappedTableName;
                }
            }
        }

    }
}
