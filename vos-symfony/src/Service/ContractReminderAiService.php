<?php

namespace App\Service;

use App\Entity\ContratEmbauche;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ContractReminderAiService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly ?string $groqApiKey,
        private readonly ?string $groqModel,
    ) {
    }

    public function isConfigured(): bool
    {
        return trim((string) $this->groqApiKey) !== '';
    }

    public function generateReminderMessage(ContratEmbauche $contract, int $daysRemaining): string
    {
        if (!$this->isConfigured()) {
            return $this->buildFallbackMessage($contract, $daysRemaining);
        }

        $model = trim((string) $this->groqModel);
        if ($model === '') {
            $model = 'llama-3.1-8b-instant';
        }

        $systemPrompt = <<<'PROMPT'
You write concise HR contract reminder messages in French.
Return only the final reminder message.
Keep it professional, clear, and under 80 words.
PROMPT;

        $userPrompt = sprintf(
            "Genere un rappel RH en francais pour un contrat qui arrive bientot a echeance.\n".
            "Type de contrat: %s\n".
            "Date de fin: %s\n".
            "Jours restants: %d\n".
            "Statut: %s\n".
            "Volume horaire: %s\n".
            "Salaire: %s TND\n".
            "Le message doit rappeler la date de fin et suggerer une action de suivi.",
            $contract->getTypeContrat() ?? 'Non defini',
            $contract->getDateFin()?->format('d/m/Y') ?? 'Non definie',
            $daysRemaining,
            $contract->getStatus() ?? 'Non defini',
            $contract->getVolumeHoraire() ?? 'Non defini',
            number_format((float) ($contract->getSalaire() ?? 0), 2, '.', '')
        );

        try {
            $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . trim((string) $this->groqApiKey),
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
            $content = trim((string) ($payload['choices'][0]['message']['content'] ?? ''));

            return $content !== '' ? $content : $this->buildFallbackMessage($contract, $daysRemaining);
        } catch (\Throwable $exception) {
            $this->logger->warning('AI contract reminder generation failed, using fallback.', [
                'error' => $exception->getMessage(),
                'contract_id' => $contract->getId(),
            ]);

            return $this->buildFallbackMessage($contract, $daysRemaining);
        }
    }

    private function buildFallbackMessage(ContratEmbauche $contract, int $daysRemaining): string
    {
        return sprintf(
            'Rappel RH : le contrat %s #%d se termine le %s, soit dans %d jour(s). Merci de preparer le suivi administratif et de confirmer la suite a donner.',
            $contract->getTypeContrat() ?? 'Non defini',
            $contract->getId() ?? 0,
            $contract->getDateFin()?->format('d/m/Y') ?? 'Non definie',
            $daysRemaining
        );
    }
}
