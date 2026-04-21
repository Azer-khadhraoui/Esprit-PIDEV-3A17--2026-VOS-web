<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EmailVerificationService
{
    private const API_URL = 'https://emailreputation.abstractapi.com/v1/';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $abstractApiKey,
    ) {
    }

    public function isDeliverable(string $email): bool
    {
        // If no API key is configured, keep signup functional.
        if (trim($this->abstractApiKey) === '') {
            return true;
        }

        try {
            $response = $this->httpClient->request('GET', self::API_URL, [
                'query' => [
                    'api_key' => $this->abstractApiKey,
                    'email' => $email,
                ],
                'timeout' => 10,
            ]);

            $data = $response->toArray(false);
        } catch (ExceptionInterface) {
            // Fail-open in dev/demo: API outages should not block account creation.
            return true;
        }

        if (isset($data['error'])) {
            $errorCode = (string) ($data['error']['code'] ?? '');
            if ($errorCode === 'unauthorized') {
                // Invalid/expired key should not block signup flow.
                return true;
            }

            // Any provider-side validation error: keep signup available.
            return true;
        }

        // Email Reputation API schema (new)
        $deliverabilityNode = is_array($data['email_deliverability'] ?? null) ? $data['email_deliverability'] : [];
        $isValidFormat = $this->toBool($deliverabilityNode['is_format_valid'] ?? null);
        $isSmtpValid = $this->toBool($deliverabilityNode['is_smtp_valid'] ?? null);
        $deliverability = strtoupper((string) ($deliverabilityNode['status'] ?? ''));

        // Backward compatibility with old Email Validation API schema.
        if ($deliverability === '' && !$isValidFormat && !$isSmtpValid) {
            $isValidFormat = $this->toBool($data['is_valid_format']['value'] ?? false);
            $isSmtpValid = $this->toBool($data['is_smtp_valid']['value'] ?? false);
            $deliverability = strtoupper((string) ($data['deliverability'] ?? ''));
        }

        return $isValidFormat && $isSmtpValid && $deliverability === 'DELIVERABLE';
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return ((int) $value) === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            return in_array($normalized, ['true', '1', 'yes', 'y'], true);
        }

        return false;
    }
}
