<?php

namespace App\Tests\Service\candidature;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use App\Service\candidature\CVAnalysisService;
use App\Entity\Candidature;
use App\Entity\AnalyseCv;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class CVAnalysisServiceTest extends TestCase
{
    private CVAnalysisService $cvAnalysisService;
    private MockObject|HttpClientInterface $httpClientMock;
    private MockObject|EntityManagerInterface $entityManagerMock;
    private MockObject|LoggerInterface $loggerMock;
    private string $groqApiKey = 'test-api-key-12345';

    protected function setUp(): void
    {
        $this->httpClientMock = $this->createMock(HttpClientInterface::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->cvAnalysisService = new CVAnalysisService(
            $this->httpClientMock,
            $this->entityManagerMock,
            $this->groqApiKey,
            $this->loggerMock
        );
    }

    /**
     * Test que le service est instancié correctement
     */
    public function testServiceInstantiation(): void
    {
        $this->assertInstanceOf(CVAnalysisService::class, $this->cvAnalysisService);
    }

    /**
     * Test l'analyse d'un CV avec une réponse valide de l'API
     */
    public function testAnalyzerCVWithValidResponse(): void
    {
        $candidature = new Candidature();
        $cvText = "Je suis un développeur avec 5 ans d'expérience en PHP et Symfony.";

        $apiResponse = [
            'competences_detectees' => ['PHP' => 85, 'Symfony' => 80, 'MySQL' => 75],
            'points_forts' => ['Excellente maîtrise de PHP', 'Expérience confirmée en Symfony'],
            'points_faibles' => ['Peu d\'expérience en DevOps'],
            'score_cv' => 82,
            'suggestions' => ['Améliorez vos compétences DevOps', 'Apprenez Docker']
        ];

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->method('getStatusCode')->willReturn(200);
        $responseMock->method('toArray')->willReturn(['choices' => [['message' => ['content' => json_encode($apiResponse)]]]]);

        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->willReturn($responseMock);

        $result = $this->cvAnalysisService->analyzerCV($cvText, $candidature);

        $this->assertInstanceOf(AnalyseCv::class, $result);
    }

    /**
     * Test l'exception levée quand la clé API est vide
     */
    public function testAnalyzerCVThrowsExceptionWithEmptyApiKey(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Clé API Groq non configurée');

        $service = new CVAnalysisService(
            $this->httpClientMock,
            $this->entityManagerMock,
            '',
            $this->loggerMock
        );

        $candidature = new Candidature();
        $service->analyzerCV('CV text', $candidature);
    }

    /**
     * Test l'exception levée quand le texte du CV est vide
     */
    public function testAnalyzerCVThrowsExceptionWithEmptyCV(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Le texte du CV ne peut pas être vide');

        $candidature = new Candidature();
        $this->cvAnalysisService->analyzerCV('   ', $candidature);
    }

    /**
     * Test le nettoyage du texte du CV
     */
    public function testCVTextCleaning(): void
    {
        $candidature = new Candidature();
        $dirtyCV = "Texte avec\x00caractères\x08invalides\nvalide";

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->method('getStatusCode')->willReturn(200);
        $responseMock->method('toArray')->willReturn([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'competences_detectees' => [],
                        'points_forts' => [],
                        'points_faibles' => [],
                        'score_cv' => 0,
                        'suggestions' => []
                    ])
                ]
            ]]
        ]);

        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->willReturn($responseMock);

        $result = $this->cvAnalysisService->analyzerCV($dirtyCV, $candidature);
        $this->assertInstanceOf(AnalyseCv::class, $result);
    }

    /**
     * Test la limitation de la longueur du CV à 8000 caractères
     */
    public function testCVTextLengthLimitation(): void
    {
        $candidature = new Candidature();
        $longCV = str_repeat('a', 10000); // Plus que 8000

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->method('getStatusCode')->willReturn(200);
        $responseMock->method('toArray')->willReturn([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'competences_detectees' => [],
                        'points_forts' => [],
                        'points_faibles' => [],
                        'score_cv' => 0,
                        'suggestions' => []
                    ])
                ]
            ]]
        ]);

        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->willReturn($responseMock);

        $result = $this->cvAnalysisService->analyzerCV($longCV, $candidature);
        $this->assertInstanceOf(AnalyseCv::class, $result);
    }

    /**
     * Test la journalisation des informations
     */
    public function testLoggingOnAnalysis(): void
    {
        $candidature = new Candidature();
        $cvText = "CV text";

        // Le logger peut être appelé plusieurs fois (request et éventuellement response)
        $this->loggerMock->expects($this->atLeastOnce())
            ->method('info');

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->method('getStatusCode')->willReturn(200);
        $responseMock->method('toArray')->willReturn([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'competences_detectees' => [],
                        'points_forts' => [],
                        'points_faibles' => [],
                        'score_cv' => 0,
                        'suggestions' => []
                    ])
                ]
            ]]
        ]);

        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->willReturn($responseMock);

        $this->cvAnalysisService->analyzerCV($cvText, $candidature);
    }

    /**
     * Test les en-têtes de la requête API
     */
    public function testApiRequestHeaders(): void
    {
        $candidature = new Candidature();
        $cvText = "CV text";

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->method('getStatusCode')->willReturn(200);
        $responseMock->method('toArray')->willReturn([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'competences_detectees' => [],
                        'points_forts' => [],
                        'points_faibles' => [],
                        'score_cv' => 0,
                        'suggestions' => []
                    ])
                ]
            ]]
        ]);

        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://api.groq.com/openai/v1/chat/completions',
                $this->callback(function ($options) {
                    return isset($options['headers']['Authorization']) &&
                           strpos($options['headers']['Authorization'], 'Bearer') === 0 &&
                           $options['headers']['Content-Type'] === 'application/json';
                })
            )
            ->willReturn($responseMock);

        $this->cvAnalysisService->analyzerCV($cvText, $candidature);
    }
}
