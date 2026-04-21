<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GroqReasonEnhancer
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $groqApiKey,
    ) {
    }

    public function isConfigured(): bool
    {
        return is_string($this->groqApiKey) && trim($this->groqApiKey) !== '';
    }

    public function enhance(string $userText, string $requestType): string
    {
        $input = trim($userText);
        if ($input === '') {
            throw new \RuntimeException('Veuillez ecrire un motif avant de lancer l\'amelioration IA.');
        }

        if (!$this->isConfigured()) {
            throw new \RuntimeException('La cle API Groq n\'est pas configuree.');
        }

        $typeLabel = $requestType === 'demission' ? 'demission' : 'demande de conge';
        $systemPrompt = 'Tu es un assistant RH. Tu rediges des motifs professionnels, clairs et polis en francais. '
            . 'Conserve l\'intention de l\'utilisateur, sans inventer de faits. '
            . 'Rends le texte actionnable pour un document administratif (ton formel, phrases completes). '
            . 'Retourne uniquement le texte final sans balises ni JSON.';

        $userPrompt = "Ameliore et developpe ce motif pour une {$typeLabel}.\n"
            . "Texte utilisateur:\n{$input}\n\n"
            . "Contraintes:\n"
            . "- 120 a 250 mots\n"
            . "- Structure claire\n"
            . "- Ton professionnel\n"
            . "- Aucune information inventee";

        try {
            $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . trim((string) $this->groqApiKey),
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'llama-3.3-70b-versatile',
                    'temperature' => 0.4,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ],
                'timeout' => 20,
            ]);

            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                throw new \RuntimeException('Erreur API Groq (HTTP ' . $status . ').');
            }

            $payload = $response->toArray(false);
            $text = trim((string) ($payload['choices'][0]['message']['content'] ?? ''));
            if ($text === '') {
                throw new \RuntimeException('Reponse IA vide.');
            }

            return $text;
        } catch (TransportExceptionInterface $exception) {
            throw new \RuntimeException('Impossible de contacter Groq pour le moment.');
        }
    }
}
