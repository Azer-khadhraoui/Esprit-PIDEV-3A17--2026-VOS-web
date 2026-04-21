<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OffreChatFilterAiService
{
	public function __construct(
		private readonly HttpClientInterface $httpClient,
		private readonly LoggerInterface $logger,
		private readonly ?string $groqApiKey,
		private readonly ?string $groqModel,
	) {
	}

	/**
	 * @param array{typesContrat:list<string>, workPreferences:list<string>, statuts:list<string>} $availableOptions
	 *
	 * @return array{
	 *   filters:array{q:?string, type_contrat:?string, work_preference:?string, lieu:?string, statut:?string},
	 *   reason:string
	 * }
	 */
	public function suggestFilters(string $message, array $availableOptions): array
	{
		$trimmedMessage = trim($message);
		if ($trimmedMessage === '') {
			return [
				'filters' => [
					'q' => null,
					'type_contrat' => null,
					'work_preference' => null,
					'lieu' => null,
					'statut' => 'OUVERTE',
				],
				'reason' => 'J ai besoin de plus de details sur le poste que vous cherchez.',
			];
		}

		$fallback = $this->buildFallbackSuggestion($trimmedMessage, $availableOptions);
		$apiKey = trim((string) $this->groqApiKey);
		if ($apiKey === '') {
			return $fallback;
		}

		$model = trim((string) $this->groqModel);
		if ($model === '') {
			$model = 'llama-3.1-8b-instant';
		}

		$systemPrompt = <<<'PROMPT'
You are a recruitment assistant that converts a user request into filters.
Return ONLY a valid JSON object with this shape:
{
  "q": string|null,
  "type_contrat": string|null,
  "work_preference": string|null,
  "lieu": string|null,
  "statut": string|null,
  "reason": string
}
Rules:
- Keep values concise.
- If uncertain, set null.
- Prefer statut "OUVERTE" when user asks for available offers.
- Detect contract intent words (freelance, cdi, cdd, stage, alternance) and map them to type_contrat when possible.
- Detect work mode words (remote, teletravail, hybride, presentiel) and map them to work_preference when possible.
- If contract is requested but you cannot map exact type_contrat, set q to that contract word.
- reason must be in French and max 180 chars.
PROMPT;

		$userPrompt = sprintf(
			"Message utilisateur: %s\nOptions type_contrat: %s\nOptions work_preference: %s\nOptions statut: %s",
			$trimmedMessage,
			implode(', ', $availableOptions['typesContrat'] ?? []),
			implode(', ', $availableOptions['workPreferences'] ?? []),
			implode(', ', $availableOptions['statuts'] ?? []),
		);

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
			$content = trim((string) ($payload['choices'][0]['message']['content'] ?? ''));
			$content = preg_replace('/^```[a-zA-Z0-9_-]*\s*/', '', $content) ?? $content;
			$content = preg_replace('/\s*```$/', '', $content) ?? $content;

			$decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
			if (!is_array($decoded)) {
				return $fallback;
			}

			$suggestion = [
				'filters' => [
					'q' => $this->normalizeText($decoded['q'] ?? null),
					'type_contrat' => $this->normalizeText($decoded['type_contrat'] ?? null),
					'work_preference' => $this->normalizeText($decoded['work_preference'] ?? null),
					'lieu' => $this->normalizeText($decoded['lieu'] ?? null),
					'statut' => $this->normalizeText($decoded['statut'] ?? null),
				],
				'reason' => $this->normalizeText($decoded['reason'] ?? null) ?? $fallback['reason'],
			];

			return $this->sanitizeFilters($suggestion, $availableOptions, $fallback);
		} catch (\Throwable $exception) {
			$this->logger->warning('Offre chat filter suggestion failed. Using fallback.', [
				'error' => $exception->getMessage(),
			]);

			return $fallback;
		}
	}

	/**
	 * @param array{typesContrat:list<string>, workPreferences:list<string>, statuts:list<string>} $availableOptions
	 *
	 * @return array{
	 *   filters:array{q:?string, type_contrat:?string, work_preference:?string, lieu:?string, statut:?string},
	 *   reason:string
	 * }
	 */
	private function buildFallbackSuggestion(string $message, array $availableOptions): array
	{
		$lower = $this->normalizeForMatch($message);
		$contractKeyword = $this->extractContractKeyword($message);
		$filters = [
			'q' => $this->extractSearchQuery($message),
			'type_contrat' => null,
			'work_preference' => null,
			'lieu' => $this->extractLocation($message),
			'statut' => 'OUVERTE',
		];

		foreach ($availableOptions['typesContrat'] ?? [] as $option) {
			if (str_contains($lower, mb_strtolower($option))) {
				$filters['type_contrat'] = $option;
				break;
			}
		}

		foreach ($availableOptions['workPreferences'] ?? [] as $option) {
			if (str_contains($lower, mb_strtolower($option))) {
				$filters['work_preference'] = $option;
				break;
			}
		}

		if ($filters['work_preference'] === null) {
			if (str_contains($lower, 'remote') || str_contains($lower, 'teletravail') || str_contains($lower, 'distance')) {
				$filters['work_preference'] = $this->findByNeedle($availableOptions['workPreferences'] ?? [], ['remote', 'distance', 'tele']);
			} elseif (str_contains($lower, 'hybride') || str_contains($lower, 'hybrid')) {
				$filters['work_preference'] = $this->findByNeedle($availableOptions['workPreferences'] ?? [], ['hybride', 'hybrid']);
			} elseif (str_contains($lower, 'presentiel') || str_contains($lower, 'sur site')) {
				$filters['work_preference'] = $this->findByNeedle($availableOptions['workPreferences'] ?? [], ['presentiel', 'site']);
			}
		}

		if ($filters['type_contrat'] === null) {
			if ($contractKeyword === 'stage' || str_contains($lower, 'stage')) {
				$filters['type_contrat'] = $this->findByNeedle($availableOptions['typesContrat'] ?? [], ['stage']);
			} elseif ($contractKeyword === 'alternance' || str_contains($lower, 'alternance')) {
				$filters['type_contrat'] = $this->findByNeedle($availableOptions['typesContrat'] ?? [], ['alternance']);
			} elseif ($contractKeyword === 'cdd' || str_contains($lower, 'cdd')) {
				$filters['type_contrat'] = $this->findByNeedle($availableOptions['typesContrat'] ?? [], ['cdd']);
			} elseif ($contractKeyword === 'cdi' || str_contains($lower, 'cdi')) {
				$filters['type_contrat'] = $this->findByNeedle($availableOptions['typesContrat'] ?? [], ['cdi']);
			} elseif ($contractKeyword === 'freelance' || str_contains($lower, 'freelance') || str_contains($lower, 'independant')) {
				$filters['type_contrat'] = $this->findByNeedle($availableOptions['typesContrat'] ?? [], ['freelance', 'independant', 'consultant']);
			}
		}

		if ($filters['type_contrat'] === null && $contractKeyword !== null) {
			$filters['q'] = $contractKeyword;
		}

		return [
			'filters' => $filters,
			'reason' => 'J ai interprete votre demande (contrat, lieu, mode de travail, mots metier) pour cibler des offres plus pertinentes.',
		];
	}

	/**
	 * @param array{
	 *   filters:array{q:?string, type_contrat:?string, work_preference:?string, lieu:?string, statut:?string},
	 *   reason:string
	 * } $suggestion
	 * @param array{typesContrat:list<string>, workPreferences:list<string>, statuts:list<string>} $availableOptions
	 * @param array{
	 *   filters:array{q:?string, type_contrat:?string, work_preference:?string, lieu:?string, statut:?string},
	 *   reason:string
	 * } $fallback
	 *
	 * @return array{
	 *   filters:array{q:?string, type_contrat:?string, work_preference:?string, lieu:?string, statut:?string},
	 *   reason:string
	 * }
	 */
	private function sanitizeFilters(array $suggestion, array $availableOptions, array $fallback): array
	{
		$sanitized = $suggestion;

		if ($sanitized['filters']['type_contrat'] !== null && !in_array($sanitized['filters']['type_contrat'], $availableOptions['typesContrat'] ?? [], true)) {
			$sanitized['filters']['type_contrat'] = $this->coerceOptionFromAvailable($sanitized['filters']['type_contrat'], $availableOptions['typesContrat'] ?? []) ?? $fallback['filters']['type_contrat'];
		}

		if ($sanitized['filters']['work_preference'] !== null && !in_array($sanitized['filters']['work_preference'], $availableOptions['workPreferences'] ?? [], true)) {
			$sanitized['filters']['work_preference'] = $this->coerceOptionFromAvailable($sanitized['filters']['work_preference'], $availableOptions['workPreferences'] ?? []) ?? $fallback['filters']['work_preference'];
		}

		if ($sanitized['filters']['statut'] !== null && !in_array($sanitized['filters']['statut'], $availableOptions['statuts'] ?? [], true)) {
			$sanitized['filters']['statut'] = $this->coerceOptionFromAvailable($sanitized['filters']['statut'], $availableOptions['statuts'] ?? []) ?? $fallback['filters']['statut'];
		}

		if ($sanitized['filters']['q'] === null && $sanitized['filters']['type_contrat'] === null && $sanitized['filters']['work_preference'] === null && $sanitized['filters']['lieu'] === null) {
			$sanitized['filters']['q'] = $fallback['filters']['q'];
		}

		if (trim($sanitized['reason']) === '') {
			$sanitized['reason'] = $fallback['reason'];
		}

		return $sanitized;
	}

	private function normalizeText(mixed $value): ?string
	{
		if (!is_string($value)) {
			return null;
		}

		$value = trim($value);

		return $value === '' ? null : $value;
	}

	/**
	 * @param list<string> $options
	 * @param list<string> $needles
	 */
	private function findByNeedle(array $options, array $needles): ?string
	{
		foreach ($options as $option) {
			$lower = $this->normalizeForMatch($option);
			foreach ($needles as $needle) {
				if (str_contains($lower, $this->normalizeForMatch($needle))) {
					return $option;
				}
			}
		}

		return null;
	}

	/**
	 * @param list<string> $available
	 */
	private function coerceOptionFromAvailable(string $value, array $available): ?string
	{
		$needle = $this->normalizeForMatch($value);
		foreach ($available as $option) {
			if ($this->normalizeForMatch($option) === $needle) {
				return $option;
			}
		}

		foreach ($available as $option) {
			$normalizedOption = $this->normalizeForMatch($option);
			if (str_contains($normalizedOption, $needle) || str_contains($needle, $normalizedOption)) {
				return $option;
			}
		}

		return null;
	}

	private function extractSearchQuery(string $message): ?string
	{
		$keywords = [
			'freelance', 'cdi', 'cdd', 'stage', 'alternance', 'data', 'analyst', 'python', 'php', 'symfony', 'react', 'angular', 'vue', 'java', 'devops', 'cloud', 'qa', 'mobile', 'frontend', 'backend', 'fullstack',
		];

		$lower = $this->normalizeForMatch($message);
		foreach ($keywords as $keyword) {
			if (str_contains($lower, $this->normalizeForMatch($keyword))) {
				return $keyword;
			}
		}

		return null;
	}

	private function extractContractKeyword(string $message): ?string
	{
		$lower = $this->normalizeForMatch($message);

		if (str_contains($lower, 'freelance') || str_contains($lower, 'independant') || str_contains($lower, 'consultant')) {
			return 'freelance';
		}

		if (str_contains($lower, 'alternance')) {
			return 'alternance';
		}

		if (str_contains($lower, 'stage')) {
			return 'stage';
		}

		if (str_contains($lower, 'cdd')) {
			return 'cdd';
		}

		if (str_contains($lower, 'cdi')) {
			return 'cdi';
		}

		return null;
	}

	private function extractLocation(string $message): ?string
	{
		if (preg_match('/(?:a|à|en|in|au|aux)\s+([\p{L}\-\s]{2,30})/iu', $message, $matches) !== 1) {
			return null;
		}

		$value = trim((string) ($matches[1] ?? ''));
		$value = preg_replace('/\b(avec|pour|qui|et|de|du|des)\b.*$/iu', '', $value) ?? $value;
		$value = trim($value);

		return $value === '' ? null : $value;
	}

	private function normalizeForMatch(string $value): string
	{
		$value = mb_strtolower(trim($value));
		$value = str_replace(['é', 'è', 'ê', 'ë'], 'e', $value);
		$value = str_replace(['à', 'â'], 'a', $value);
		$value = str_replace(['î', 'ï'], 'i', $value);
		$value = str_replace(['ô', 'ö'], 'o', $value);
		$value = str_replace(['ù', 'û', 'ü'], 'u', $value);
		$value = str_replace('ç', 'c', $value);

		return $value;
	}
}
