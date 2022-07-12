<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\DiscriminatorMap;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\InheritanceType;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Tests\OrmFunctionalTestCase;
use Doctrine\Tests\TestUtil;

use const PHP_INT_MAX;

/**
 * @group DDC-3634
 */
class DDC3634Test extends OrmFunctionalTestCase
{
    private LastInsertIdMocker $idMocker;

    protected function setUp(): void
    {
        $this->idMocker = new LastInsertIdMocker();
        $config         = new Configuration();
        $config->setMiddlewares([new LastInsertIdMockMiddleware($this->idMocker)]);

        $this->_em         = $this->getEntityManager(TestUtil::getConnection($config));
        $this->_schemaTool = new SchemaTool($this->_em);

        parent::setUp();

        $metadata = $this->_em->getClassMetadata(DDC3634Entity::class);

        if (! $metadata->idGenerator->isPostInsertGenerator()) {
            self::markTestSkipped('Need a post-insert ID generator in order to make this test work correctly');
        }

        $this->createSchemaForModels(
            DDC3634Entity::class,
            DDC3634JTIBaseEntity::class,
            DDC3634JTIChildEntity::class
        );
    }

    public function testSavesVeryLargeIntegerAutoGeneratedValue(): void
    {
        $veryLargeId              = PHP_INT_MAX . PHP_INT_MAX;
        $this->idMocker->mockedId = $veryLargeId;

        $entity = new DDC3634Entity();

        $this->_em->persist($entity);
        $this->_em->flush();

        self::assertSame($veryLargeId, $entity->id);
    }

    public function testSavesIntegerAutoGeneratedValueAsString(): void
    {
        $entity = new DDC3634Entity();

        $this->_em->persist($entity);
        $this->_em->flush();

        self::assertIsString($entity->id);
    }

    public function testSavesIntegerAutoGeneratedValueAsStringWithJoinedInheritance(): void
    {
        $entity = new DDC3634JTIChildEntity();

        $this->_em->persist($entity);
        $this->_em->flush();

        self::assertIsString($entity->id);
    }
}

/** @Entity */
class DDC3634Entity
{
    /**
     * @var int
     * @Id
     * @Column(type="bigint")
     * @GeneratedValue(strategy="AUTO")
     */
    public $id;
}

/**
 * @Entity
 * @InheritanceType("JOINED")
 * @DiscriminatorMap({
 *  DDC3634JTIBaseEntity::class  = DDC3634JTIBaseEntity::class,
 *  DDC3634JTIChildEntity::class = DDC3634JTIChildEntity::class,
 * })
 */
class DDC3634JTIBaseEntity
{
    /**
     * @var int
     * @Id
     * @Column(type="bigint")
     * @GeneratedValue(strategy="AUTO")
     */
    public $id;
}

/** @Entity */
class DDC3634JTIChildEntity extends DDC3634JTIBaseEntity
{
}

class LastInsertIdMocker
{
    public ?string $mockedId = null;
}

final class LastInsertIdMockConnection extends AbstractConnectionMiddleware
{
    public function __construct(DriverConnection $wrappedConnection, private LastInsertIdMocker $idMocker)
    {
        parent::__construct($wrappedConnection);
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId($name = null): int|string
    {
        return $this->idMocker->mockedId ?? parent::lastInsertId($name);
    }
}

final class LastInsertIdMockDriver extends AbstractDriverMiddleware
{
    public function __construct(Driver $wrappedDriver, private LastInsertIdMocker $idMocker)
    {
        parent::__construct($wrappedDriver);
    }

    public function connect(array $params): LastInsertIdMockConnection
    {
        return new LastInsertIdMockConnection(
            parent::connect($params),
            $this->idMocker
        );
    }
}

final class LastInsertIdMockMiddleware implements Middleware
{
    public function __construct(private LastInsertIdMocker $idMocker)
    {
    }

    public function wrap(Driver $driver): LastInsertIdMockDriver
    {
        return new LastInsertIdMockDriver($driver, $this->idMocker);
    }
}
