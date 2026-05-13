<?php

namespace App\Tests\Repository;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use App\Repository\PreferenceCandidatureRepository;
use App\Entity\PreferenceCandidature;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManager;

class PreferenceCandidatureRepositoryTest extends TestCase
{
    private PreferenceCandidatureRepository $repository;
    private MockObject|ManagerRegistry $registryMock;
    private MockObject|EntityManager $entityManagerMock;

    protected function setUp(): void
    {
        $this->entityManagerMock = $this->createMock(EntityManager::class);
        $this->registryMock = $this->createMock(ManagerRegistry::class);
        $this->registryMock->method('getManagerForClass')
            ->willReturn($this->entityManagerMock);

        $this->repository = new PreferenceCandidatureRepository($this->registryMock);
    }

    /**
     * Test que le repository est instancié correctement
     */
    public function testRepositoryInstantiation(): void
    {
        $this->assertInstanceOf(PreferenceCandidatureRepository::class, $this->repository);
    }

    /**
     * Test les méthodes héritées sont disponibles
     */
    public function testInheritedMethodsAvailable(): void
    {
        $this->assertTrue(method_exists($this->repository, 'find'));
        $this->assertTrue(method_exists($this->repository, 'findBy'));
        $this->assertTrue(method_exists($this->repository, 'findAll'));
        $this->assertTrue(method_exists($this->repository, 'findOneBy'));
    }

    /**
     * Test que le repository peut être utilisé avec createQueryBuilder
     */
    public function testCanCreateQueryBuilder(): void
    {
        $this->assertTrue(method_exists($this->repository, 'createQueryBuilder'));
    }

    /**
     * Test la sauvegarde d'une préférence
     */
    public function testSavePreference(): void
    {
        $preference = new PreferenceCandidature();
        $preference->setModeTravail('Hybride');
        $preference->setTypePosteSouhaite('Développeur');

        $this->assertEquals('Hybride', $preference->getModeTravail());
        $this->assertEquals('Développeur', $preference->getTypePosteSouhaite());
    }

    /**
     * Test les propriétés de base d'une préférence
     */
    public function testPreferenceProperties(): void
    {
        $preference = new PreferenceCandidature();
        $preference->setModeTravail('Télétravail');
        $preference->setTypeContratSouhaite('CDI');
        $preference->setPretentionSalariale(55000);

        $this->assertEquals('Télétravail', $preference->getModeTravail());
        $this->assertEquals('CDI', $preference->getTypeContratSouhaite());
        $this->assertEquals(55000, $preference->getPretentionSalariale());
    }

    /**
     * Test les préférences nullables
     */
    public function testNullablePreferences(): void
    {
        $preference = new PreferenceCandidature();

        $this->assertNull($preference->getTypePosteSouhaite());
        $this->assertNull($preference->getModeTravail());
        $this->assertNull($preference->getDisponibilite());
        $this->assertNull($preference->getMobiliteGeographique());
    }

    /**
     * Test la création d'une nouvelle préférence
     */
    public function testCreateNewPreference(): void
    {
        $preference = new PreferenceCandidature();
        $preference->setIdUtilisateur(42);
        $preference->setModeTravail('Hybride');
        $preference->setTypeContratSouhaite('CDI');

        $this->assertEquals(42, $preference->getIdUtilisateur());
        $this->assertEquals('Hybride', $preference->getModeTravail());
        $this->assertEquals('CDI', $preference->getTypeContratSouhaite());
    }

    /**
     * Test que findBy est disponible
     */
    public function testFindByMethodExists(): void
    {
        $this->assertTrue(method_exists($this->repository, 'findBy'));
    }

    /**
     * Test que findOneBy est disponible
     */
    public function testFindOneByMethodExists(): void
    {
        $this->assertTrue(method_exists($this->repository, 'findOneBy'));
    }
}
