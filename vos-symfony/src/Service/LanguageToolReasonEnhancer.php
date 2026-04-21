<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LanguageToolReasonEnhancer
{
    private const API_URL = 'https://api.languagetool.org/v2/check';

    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    public function enhance(string $text): string
    {
        $input = trim($text);
        if ($input === '') {
            throw new \RuntimeException('Veuillez ecrire un motif avant de lancer la correction.');
        }

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => [
                    'text' => $input,
                    'language' => 'fr',
                    'enabledOnly' => 'false',
                ],
                'timeout' => 20,
            ]);

            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                throw new \RuntimeException('Erreur LanguageTool (HTTP ' . $status . ').');
            }

            $payload = $response->toArray(false);
            $matches = is_array($payload['matches'] ?? null) ? $payload['matches'] : [];

            if ($matches === []) {
                return $input;
            }

            // Apply replacements from right to left to keep offsets valid.
            usort($matches, static fn (array $a, array $b): int => ((int) ($b['offset'] ?? 0)) <=> ((int) ($a['offset'] ?? 0)));

            $corrected = $input;
            foreach ($matches as $match) {
                $offset = (int) ($match['offset'] ?? -1);
                $length = (int) ($match['length'] ?? 0);
                $replacements = is_array($match['replacements'] ?? null) ? $match['replacements'] : [];

                if ($offset < 0 || $length < 0) {
                    continue;
                }

                $replacementValue = (string) ($replacements[0]['value'] ?? '');
                if ($replacementValue === '') {
                    continue;
                }

                $before = mb_substr($corrected, 0, $offset, 'UTF-8');
                $after = mb_substr($corrected, $offset + $length, null, 'UTF-8');
                $corrected = $before . $replacementValue . $after;
            }

            return trim($corrected);
        } catch (TransportExceptionInterface) {
            throw new \RuntimeException('Impossible de contacter LanguageTool pour le moment.');
        }
    }
}
