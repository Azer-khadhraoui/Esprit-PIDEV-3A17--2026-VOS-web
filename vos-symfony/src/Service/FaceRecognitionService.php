<?php

namespace App\Service;

class FaceRecognitionService
{
    public function isValidDescriptor(mixed $descriptor): bool
    {
        if (!is_array($descriptor) || count($descriptor) !== 128) {
            return false;
        }

        foreach ($descriptor as $value) {
            if (!is_numeric($value)) {
                return false;
            }
        }

        return true;
    }

    public function serializeDescriptor(array $descriptor): string
    {
        return json_encode(array_map(static fn ($value) => (float) $value, $descriptor), JSON_THROW_ON_ERROR);
    }

    public function deserializeDescriptor(?string $descriptor): ?array
    {
        if ($descriptor === null || trim($descriptor) === '') {
            return null;
        }

        $decoded = json_decode($descriptor, true);

        return is_array($decoded) ? array_map(static fn ($value) => (float) $value, $decoded) : null;
    }

    public function matches(string $storedDescriptor, array $providedDescriptor, float $threshold = 0.58): bool
    {
        $stored = $this->deserializeDescriptor($storedDescriptor);
        if (!$this->isValidDescriptor($stored) || !$this->isValidDescriptor($providedDescriptor)) {
            return false;
        }

        $distance = $this->euclideanDistance($stored, $providedDescriptor);

        return $distance <= $threshold;
    }

    private function euclideanDistance(array $a, array $b): float
    {
        $sum = 0.0;
        $length = min(count($a), count($b));

        for ($i = 0; $i < $length; $i++) {
            $diff = ((float) $a[$i]) - ((float) $b[$i]);
            $sum += $diff * $diff;
        }

        return sqrt($sum);
    }
}
