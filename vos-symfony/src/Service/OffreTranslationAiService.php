<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OffreTranslationAiService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly ?string $groqApiKey,
        private readonly ?string $groqModel,
    ) {
    }

    public function translate(string $text, string $targetLanguage): string
    {
        $source = trim($text);
        if ($source === '') {
            return '';
        }

        $targetLanguage = strtoupper(trim($targetLanguage));
        if (!in_array($targetLanguage, ['FR', 'EN'], true)) {
            return $source;
        }

        $apiKey = trim((string) $this->groqApiKey);
        if ($apiKey === '') {
            return $source;
        }

        $model = trim((string) $this->groqModel);
        if ($model === '') {
            $model = 'llama-3.1-8b-instant';
        }

        $languageLabel = $targetLanguage === 'EN' ? 'English' : 'French';

        $systemPrompt = sprintf(
            'You are a professional translator. Translate only to %s. Keep meaning and tone. Return plain text only, no markdown.',
            $languageLabel,
        );

        $userPrompt = sprintf('Text to translate:\n%s', $source);

        try {
            $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer '.$apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'temperature' => 0.2,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ],
                'timeout' => 20,
            ]);

            $payload = $response->toArray(false);
            $translated = trim((string) ($payload['choices'][0]['message']['content'] ?? ''));
            $translated = preg_replace('/^```[a-zA-Z0-9_-]*\s*/', '', $translated) ?? $translated;
            $translated = preg_replace('/\s*```$/', '', $translated) ?? $translated;

            return trim($translated) !== '' ? trim($translated) : $source;
        } catch (\Throwable $exception) {
            $this->logger->warning('Offer translation failed. Returning source text.', [
                'error' => $exception->getMessage(),
                'target_language' => $targetLanguage,
            ]);

            return $source;
        }
    }
}
