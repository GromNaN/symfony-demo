<?php

declare(strict_types=1);

namespace App\ORM\Id;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Id\AbstractIdGenerator;

/**
 * Uses AUTO_INCREMENT to generate a value when not explicitly assigned.
 * @see \Doctrine\ORM\Id\IdentityGenerator
 */
class AssignableIdentityGenerator extends AbstractIdGenerator
{
    /**
     * {@inheritDoc}
     */
    public function generate(EntityManager $em, $entity)
    {
        $class = $em->getClassMetadata(get_class($entity));
        $idField = $class->getIdentifierFieldNames()[0];

        if (null !== $value = $class->getFieldValue($entity, $idField)) {
            return $value;
        }

        return (int) $em->getConnection()->lastInsertId();
    }

    /**
     * {@inheritdoc}
     */
    public function isPostInsertGenerator()
    {
        return true;
    }
}
