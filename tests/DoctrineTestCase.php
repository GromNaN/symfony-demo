<?php

declare(strict_types=1);

namespace App\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Base class for functional tests that need a Doctrine schema.
 * Creates the schema once per concrete test class, then truncates between tests.
 */
abstract class DoctrineTestCase extends KernelTestCase
{
    /** @var array<string, bool> schema-created flag per concrete class */
    private static array $schemaCreated = [];

    protected EntityManagerInterface $em;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $class = static::class;

        if (!empty(self::$schemaCreated[$class])) {
            return;
        }

        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        assert($em instanceof EntityManagerInterface);

        $metadata = static::entityClasses();
        $classMetadata = array_map(fn(string $c) => $em->getClassMetadata($c), $metadata);

        $tool = new SchemaTool($em);

        try {
            $tool->dropSchema($classMetadata);
        } catch (\Exception) {
            // Schema doesn't exist yet – safe to ignore
        }

        $tool->createSchema($classMetadata);
        self::$schemaCreated[$class] = true;
    }

    protected function setUp(): void
    {
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        assert($this->em instanceof EntityManagerInterface);

        // Truncate all managed tables between tests
        foreach (static::entityClasses() as $entityClass) {
            $table = $this->em->getClassMetadata($entityClass)->getTableName();
            try {
                $this->em->getConnection()->executeStatement("TRUNCATE TABLE {$table} CASCADE");
            } catch (\Exception) {
                // Ignore if table doesn't exist
            }
        }

        $this->em->clear();
    }

    /**
     * Return the list of entity FQCNs whose tables this test class needs.
     *
     * @return list<class-string>
     */
    abstract protected static function entityClasses(): array;
}

