<?php

namespace App\Tests\Service\candidature;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use App\Service\candidature\PdfService;
use Symfony\Component\HttpFoundation\Response;

class PdfServiceTest extends TestCase
{
    private PdfService $pdfService;

    protected function setUp(): void
    {
        $this->pdfService = new PdfService();
    }

    /**
     * Test que le service est instancié correctement
     */
    public function testServiceInstantiation(): void
    {
        $this->assertInstanceOf(PdfService::class, $this->pdfService);
    }

    /**
     * Test la génération d'une réponse PDF
     */
    public function testGeneratePdfResponse(): void
    {
        $html = '<html><body><h1>Test PDF</h1><p>Contenu du test</p></body></html>';
        $filename = 'test.pdf';

        $response = $this->pdfService->generatePdfResponse($html, $filename);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    /**
     * Test que le type de contenu est PDF
     */
    public function testPdfContentType(): void
    {
        $html = '<html><body><h1>Test</h1></body></html>';
        $filename = 'test.pdf';

        $response = $this->pdfService->generatePdfResponse($html, $filename);

        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    /**
     * Test le header Content-Disposition pour le téléchargement
     */
    public function testContentDispositionHeader(): void
    {
        $html = '<html><body><h1>Test</h1></body></html>';
        $filename = 'document.pdf';

        $response = $this->pdfService->generatePdfResponse($html, $filename);

        $contentDisposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $contentDisposition);
        $this->assertStringContainsString('document.pdf', $contentDisposition);
    }

    /**
     * Test avec différents noms de fichiers
     */
    public function testDifferentFilenames(): void
    {
        $html = '<html><body>Test</body></html>';
        $filenames = [
            'candidature.pdf',
            'cv_john_doe.pdf',
            'test-document-123.pdf',
            'rapport_2025.pdf'
        ];

        foreach ($filenames as $filename) {
            $response = $this->pdfService->generatePdfResponse($html, $filename);
            $contentDisposition = $response->headers->get('Content-Disposition');
            $this->assertStringContainsString($filename, $contentDisposition);
        }
    }

    /**
     * Test avec du HTML simple
     */
    public function testSimpleHtmlContent(): void
    {
        $html = '<html><body><p>Bonjour monde</p></body></html>';
        $filename = 'simple.pdf';

        $response = $this->pdfService->generatePdfResponse($html, $filename);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertTrue($response->getContent() !== '');
    }

    /**
     * Test avec du HTML complexe
     */
    public function testComplexHtmlContent(): void
    {
        $html = <<<HTML
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; }
        .header { background-color: #f0f0f0; padding: 20px; }
        .content { margin: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Rapport de Candidature</h1>
        <p>Date: 2025-02-20</p>
    </div>
    <div class="content">
        <h2>Informations Personnelles</h2>
        <ul>
            <li>Nom: John Doe</li>
            <li>Email: john@example.com</li>
        </ul>
    </div>
</body>
</html>
HTML;
        $filename = 'rapport.pdf';

        $response = $this->pdfService->generatePdfResponse($html, $filename);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    /**
     * Test le statut HTTP
     */
    public function testHttpStatusCode(): void
    {
        $html = '<html><body>Test</body></html>';
        $filename = 'test.pdf';

        $response = $this->pdfService->generatePdfResponse($html, $filename);

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test avec HTML contenant des caractères spéciaux
     */
    public function testSpecialCharacters(): void
    {
        $html = '<html><body><p>Éàü€ñçü Français Español</p></body></html>';
        $filename = 'special-chars.pdf';

        $response = $this->pdfService->generatePdfResponse($html, $filename);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    /**
     * Test avec HTML vide
     */
    public function testEmptyHtml(): void
    {
        $html = '<html><body></body></html>';
        $filename = 'empty.pdf';

        $response = $this->pdfService->generatePdfResponse($html, $filename);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    /**
     * Test que la réponse contient du contenu
     */
    public function testResponseHasContent(): void
    {
        $html = '<html><body><h1>Contenu test</h1></body></html>';
        $filename = 'contenu.pdf';

        $response = $this->pdfService->generatePdfResponse($html, $filename);

        $this->assertNotEmpty($response->getContent());
        $this->assertIsString($response->getContent());
    }

    /**
     * Test avec HTML contenant des tables
     */
    public function testHtmlWithTables(): void
    {
        $html = <<<HTML
<html>
<body>
    <table border="1">
        <tr>
            <th>Compétence</th>
            <th>Niveau</th>
        </tr>
        <tr>
            <td>PHP</td>
            <td>85%</td>
        </tr>
        <tr>
            <td>Symfony</td>
            <td>80%</td>
        </tr>
    </table>
</body>
</html>
HTML;
        $filename = 'table.pdf';

        $response = $this->pdfService->generatePdfResponse($html, $filename);

        $this->assertInstanceOf(Response::class, $response);
    }
}
