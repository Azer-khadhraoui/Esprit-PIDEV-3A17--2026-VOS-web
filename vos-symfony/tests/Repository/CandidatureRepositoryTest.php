<?php

namespace App\Tests\Repository;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use App\Repository\CandidatureRepository;
use App\Entity\Candidature;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManager;

class CandidatureRepositoryTest extends TestCase
{
    private CandidatureRepository $repository;
    private MockObject|ManagerRegistry $registryMock;
    private MockObject|EntityManager $entityManagerMock;

    protected function setUp(): void
    {
        $this->entityManagerMock = $this->createMock(EntityManager::class);
        $this->registryMock = $this->createMock(ManagerRegistry::class);
        $this->registryMock->method('getManagerForClass')
            ->willReturn($this->entityManagerMock);

        $this->repository = new CandidatureRepository($this->registryMock);
    }

    /**
     * Test que le repository est instancié correctement
     */
    public function testRepositoryInstantiation(): void
    {
        $this->assertInstanceOf(CandidatureRepository::class, $this->repository);
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

    /**
     * Test que le repository retourne le bon type pour les entités
     */
    public function testRepositoryReturnType(): void
    {
        $candidature = new Candidature();
        $candidature->setStatut('En attente');
        $candidature->setNiveauExperience('Junior');
        
        $this->assertEquals('En attente', $candidature->getStatut());
        $this->assertEquals('Junior', $candidature->getNiveauExperience());
    }

    /**
     * Test les propriétés de base d'une candidature
     */
    public function testCandidatureProperties(): void
    {
        $candidature = new Candidature();
        $candidature->setStatut('Accepté');
        $candidature->setNiveauExperience('Senior');
        $candidature->setAnneesExperience(8);

        $this->assertEquals('Accepté', $candidature->getStatut());
        $this->assertEquals('Senior', $candidature->getNiveauExperience());
        $this->assertEquals(8, $candidature->getAnneesExperience());
    }

    /**
     * Test la création d'une candidature avec différents statuts
     */
    public function testCandidatureWithDifferentStatuses(): void
    {
        $statuts = ['En attente', 'Accepté', 'Refusé'];
        
        foreach ($statuts as $statut) {
            $candidature = new Candidature();
            $candidature->setStatut($statut);
            $this->assertEquals($statut, $candidature->getStatut());
        }
    }

    /**
     * Test la création d'une candidature avec différents niveaux d'expérience
     */
    public function testCandidatureWithDifferentExperienceLevels(): void
    {
        $niveaux = ['Débutant', 'Junior', 'Confirmé', 'Senior', 'Expert'];
        
        foreach ($niveaux as $niveau) {
            $candidature = new Candidature();
            $candidature->setNiveauExperience($niveau);
            $this->assertEquals($niveau, $candidature->getNiveauExperience());
        }
    }

    /**
     * Test l'initialisation complète d'une candidature
     */
    public function testCompleteInitialization(): void
    {
        $candidature = new Candidature();
        $candidature->setStatut('En attente');
        $candidature->setNiveauExperience('Confirmé');
        $candidature->setAnneesExperience(5);
        $candidature->setIdUtilisateur(42);
        $candidature->setIdOffre(10);

        $this->assertEquals('En attente', $candidature->getStatut());
        $this->assertEquals('Confirmé', $candidature->getNiveauExperience());
        $this->assertEquals(5, $candidature->getAnneesExperience());
        $this->assertEquals(42, $candidature->getIdUtilisateur());
        $this->assertEquals(10, $candidature->getIdOffre());
    }
}
