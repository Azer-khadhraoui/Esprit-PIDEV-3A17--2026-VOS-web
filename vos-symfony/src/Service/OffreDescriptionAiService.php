<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OffreDescriptionAiService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly ?string $groqApiKey,
        private readonly ?string $groqModel,
    ) {
    }

    public function suggest(string $title): ?string
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
You write concise, professional French job descriptions.
Return plain text only.
Rules:
- 2 to 3 short sentences
- around 220 to 420 characters
- no markdown
- no bullet points
- mention mission, main impact, and collaboration context
PROMPT;

        $userPrompt = sprintf('Titre offre: %s', $title);

        try {
            $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer '.$apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'temperature' => 0.35,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ],
                'timeout' => 20,
            ]);

            $payload = $response->toArray(false);
            $content = trim((string) ($payload['choices'][0]['message']['content'] ?? ''));

            return $this->sanitizeDescription($content);
        } catch (\Throwable $exception) {
            $this->logger->warning('AI offer description generation failed, using fallback.', [
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function sanitizeDescription(string $content): ?string
    {
        if ($content === '') {
            return null;
        }

        $normalized = preg_replace('/^```[a-zA-Z0-9_-]*\s*/', '', $content) ?? $content;
        $normalized = preg_replace('/\s*```$/', '', $normalized) ?? $normalized;
        $normalized = trim($normalized, " \n\r\t\v\0\"");

        if ($normalized === '') {
            return null;
        }

        if (mb_strlen($normalized) > 700) {
            $normalized = mb_substr($normalized, 0, 700);
        }

        return $normalized;
    }
}
