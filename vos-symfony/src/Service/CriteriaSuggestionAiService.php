<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CriteriaSuggestionAiService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly ?string $groqApiKey,
        private readonly ?string $groqModel,
    ) {
    }

    /**
     * @return array{competences_requises: string, responsibilities: string}|null
     */
    public function suggest(string $title, string $niveauExperience, string $niveauEtude): ?array
    {
        $apiKey = trim((string) $this->groqApiKey);
        if ($apiKey === '') {
            return null;
        }

        $model = trim((string) $this->groqModel);
        if ($model === '') {
            $model = 'llama-3.1-8b-instant';
        }

        $systemPrompt = <<<'PROMPT'
You generate professional job criteria in French.
Return ONLY valid JSON with exactly two keys:
    - competences_requises: bullet points, one skill per line, each line starting with "- ".
- responsibilities: 3 to 5 professional responsibilities in one paragraph.
No markdown, no code fence, no explanations.
PROMPT;

        $userPrompt = sprintf(
            "Titre offre: %s\nNiveau experience: %s\nNiveau etude: %s\nGenerate robust and realistic requirements aligned with this role.",
            $title,
            $niveauExperience,
            $niveauEtude,
        );

        try {
            $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer '.$apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'temperature' => 0.3,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ],
                'timeout' => 20,
            ]);

            $payload = $response->toArray(false);
            $content = (string) ($payload['choices'][0]['message']['content'] ?? '');

            return $this->parseSuggestionPayload($content);
        } catch (\Throwable $exception) {
            $this->logger->warning('AI criteria suggestion failed, using fallback.', [
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array{competences_requises: string, responsibilities: string}|null
     */
    private function parseSuggestionPayload(string $content): ?array
    {
        $normalized = trim($content);

        if ($normalized === '') {
            return null;
        }

        if (str_starts_with($normalized, '```')) {
            $normalized = preg_replace('/^```[a-zA-Z0-9_-]*\s*/', '', $normalized) ?? $normalized;
            $normalized = preg_replace('/\s*```$/', '', $normalized) ?? $normalized;
            $normalized = trim($normalized);
        }

        $decoded = json_decode($normalized, true);
        if (!is_array($decoded)) {
            return null;
        }

        $competences = trim((string) ($decoded['competences_requises'] ?? ''));
        $responsibilities = trim((string) ($decoded['responsibilities'] ?? ''));

        if ($competences === '' || $responsibilities === '') {
            return null;
        }

        return [
            'competences_requises' => $this->normalizeCompetencesAsBulletList($competences),
            'responsibilities' => $responsibilities,
        ];
    }

    private function normalizeCompetencesAsBulletList(string $competences): string
    {
        $rawLines = preg_split('/\r\n|\r|\n|,|;/', $competences) ?: [];
        $items = [];

        foreach ($rawLines as $line) {
            $clean = trim((string) $line);
            $clean = preg_replace('/^[-*\s]+/', '', $clean) ?? $clean;
            $clean = trim($clean);

            if ($clean === '') {
                continue;
            }

            $items[] = '- '.$clean;
        }

        $items = array_values(array_unique($items));

        return implode("\n", $items);
    }
}
