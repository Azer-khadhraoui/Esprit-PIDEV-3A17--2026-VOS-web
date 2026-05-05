<?php

namespace App\Tests\Repository;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use App\Repository\AnalyseCvRepository;
use App\Entity\AnalyseCv;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query;

class AnalyseCvRepositoryTest extends TestCase
{
    private AnalyseCvRepository $repository;
    private MockObject|ManagerRegistry $registryMock;
    private MockObject|EntityManager $entityManagerMock;

    protected function setUp(): void
    {
        $this->entityManagerMock = $this->createMock(EntityManager::class);
        $this->registryMock = $this->createMock(ManagerRegistry::class);
        $this->registryMock->method('getManagerForClass')
            ->willReturn($this->entityManagerMock);

        $this->repository = new AnalyseCvRepository($this->registryMock);
    }

    /**
     * Test que le repository est instancié correctement
     */
    public function testRepositoryInstantiation(): void
    {
        $this->assertInstanceOf(AnalyseCvRepository::class, $this->repository);
    }

    /**
     * Test que la méthode findLatestByCandidature existe
     */
    public function testFindLatestByCandidatureMethodExists(): void
    {
        $this->assertTrue(method_exists($this->repository, 'findLatestByCandidature'));
    }

    /**
     * Test que la méthode findByCandidature existe
     */
    public function testFindByCandidatureMethodExists(): void
    {
        $this->assertTrue(method_exists($this->repository, 'findByCandidature'));
    }

    /**
     * Test que la méthode findByScoreGreaterThan existe
     */
    public function testFindByScoreGreaterThanMethodExists(): void
    {
        $this->assertTrue(method_exists($this->repository, 'findByScoreGreaterThan'));
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
     * Test les propriétés de base d'une analyse
     */
    public function testAnalysisProperties(): void
    {
        $analysis = new AnalyseCv();
        $analysis->setIdCandidature(5);
        $analysis->setScoreCv(92);

        $this->assertEquals(5, $analysis->getIdCandidature());
        $this->assertEquals(92, $analysis->getScoreCv());
    }

    /**
     * Test les analyses nullables
     */
    public function testNullableAnalysis(): void
    {
        $analysis = new AnalyseCv();

        $this->assertNull($analysis->getIdCandidature());
        $this->assertNull($analysis->getCandidature());
        $this->assertNull($analysis->getCompetencesDetectees());
        $this->assertNull($analysis->getPointsForts());
        $this->assertNull($analysis->getPointsFaibles());
        $this->assertNull($analysis->getScoreCv());
        $this->assertNull($analysis->getSuggestions());
    }

    /**
     * Test la création d'une nouvelle analyse
     */
    public function testCreateNewAnalysis(): void
    {
        $analysis = new AnalyseCv();
        $analysis->setIdCandidature(10);
        $analysis->setScoreCv(78);
        $analysis->setCompetencesDetectees(['PHP' => 85, 'Symfony' => 80]);
        $analysis->setPointsForts(['Bon code', 'Expérience']);
        $analysis->setPointsFaibles(['DevOps']);

        $this->assertEquals(10, $analysis->getIdCandidature());
        $this->assertEquals(78, $analysis->getScoreCv());
        $this->assertIsArray($analysis->getCompetencesDetectees());
        $this->assertIsArray($analysis->getPointsForts());
        $this->assertIsArray($analysis->getPointsFaibles());
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
     * Test que findAll est disponible
     */
    public function testFindAllMethodExists(): void
    {
        $this->assertTrue(method_exists($this->repository, 'findAll'));
    }

    /**
     * Test qu'une analyse peut contenir des données JSON
     */
    public function testAnalysisWithComplexJsonData(): void
    {
        $analysis = new AnalyseCv();
        
        $complexData = [
            'competences' => [
                'techniques' => ['PHP', 'Symfony', 'Docker'],
                'transversales' => ['Leadership', 'Communication']
            ],
            'evaluations' => [
                'coding' => 85,
                'design' => 78,
                'testing' => 90
            ]
        ];
        
        $analysis->setCompetencesDetectees($complexData['competences']);
        
        $this->assertIsArray($analysis->getCompetencesDetectees());
        $this->assertArrayHasKey('techniques', $analysis->getCompetencesDetectees());
    }
}
