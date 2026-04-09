<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class ValidationService
{
    /**
     * Valide et nettoie une chaîne de caractères
     */
    public function validateString(mixed $value, int $minLength = 1, int $maxLength = 255, string $fieldName = 'champ'): string
    {
        if (empty($value)) {
            throw new \InvalidArgumentException("Le $fieldName est requis.");
        }

        $value = trim((string) $value);

        if (strlen($value) < $minLength) {
            throw new \InvalidArgumentException("Le $fieldName doit avoir au moins $minLength caractères.");
        }

        if (strlen($value) > $maxLength) {
            throw new \InvalidArgumentException("Le $fieldName ne doit pas dépasser $maxLength caractères.");
        }

        // Éviter les injections XSS simples
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Valide un email
     */
    public function validateEmail(mixed $value): string
    {
        $email = trim((string) $value);

        if (empty($email)) {
            throw new \InvalidArgumentException('L\'email est requis.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('L\'email n\'est pas valide.');
        }

        if (strlen($email) > 255) {
            throw new \InvalidArgumentException('L\'email est trop long.');
        }

        return $email;
    }

    /**
     * Valide un mot de passe
     */
    public function validatePassword(mixed $value, int $minLength = 6, string $fieldName = 'mot de passe'): string
    {
        $password = (string) $value;

        if (empty($password)) {
            throw new \InvalidArgumentException("Le $fieldName est requis.");
        }

        if (strlen($password) < $minLength) {
            throw new \InvalidArgumentException("Le $fieldName doit avoir au moins $minLength caractères.");
        }

        if (strlen($password) > 255) {
            throw new \InvalidArgumentException("Le $fieldName est trop long.");
        }

        return $password;
    }

    /**
     * Nettoie un nom/prénom
     */
    public function validateName(mixed $value, string $fieldName = 'nom'): string
    {
        return $this->validateString($value, 2, 100, $fieldName);
    }

    /**
     * Valide le type MIME d'un fichier
     */
    public function validateImageFile(\SplFileInfo $file, array $allowedMimes = ['image/jpeg', 'image/png', 'image/gif']): void
    {
        $maxSize = 5 * 1024 * 1024; // 5 MB

        $size = $file->getSize();
        if ($size !== false && $size > $maxSize) {
            throw new \InvalidArgumentException('L\'image dépasse 5 MB.');
        }

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

        if ($file instanceof UploadedFile) {
            $clientMimeType = strtolower((string) $file->getClientMimeType());
            if ($clientMimeType !== '' && in_array($clientMimeType, $allowedMimes, true)) {
                return;
            }

            $originalExt = strtolower((string) pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
            if ($originalExt !== '' && in_array($originalExt, $allowedExtensions, true)) {
                return;
            }
        }

        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mimeType = @finfo_file($finfo, $file->getPathname());
                finfo_close($finfo);
                if (is_string($mimeType) && in_array(strtolower($mimeType), $allowedMimes, true)) {
                    return;
                }
            }
        }

        $fileExt = strtolower((string) pathinfo($file->getPathname(), PATHINFO_EXTENSION));
        if ($fileExt !== '' && in_array($fileExt, $allowedExtensions, true)) {
            return;
        }

        if (!($file instanceof UploadedFile)) {
            return;
        }

        if (!in_array(strtolower((string) pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION)), $allowedExtensions, true)) {
            throw new \InvalidArgumentException('Le format d\'image n\'est pas autorisé (JPEG, PNG, GIF uniquement).');
        }
    }

    public function resolveImageExtension(UploadedFile $file): string
    {
        $ext = strtolower((string) pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true)) {
            return $ext === 'jpeg' ? 'jpg' : $ext;
        }

        $mime = strtolower((string) $file->getClientMimeType());
        $map = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
        ];

        return $map[$mime] ?? 'jpg';
    }

    /**
     * Valide une énumération de rôle
     */
    public function validateRole(mixed $value): string
    {
        $role = trim((string) $value);
        $validRoles = ['CLIENT', 'ADMIN_RH', 'ADMIN_TECHNIQUE'];

        if (!in_array($role, $validRoles, true)) {
            throw new \InvalidArgumentException('Le rôle n\'est pas valide.');
        }

        return $role;
    }
}
