<?php

namespace App\Service;

use App\Repository\UserRepository;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class PasswordResetService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly MailerInterface $mailer,
        private readonly string $mailerFrom,
        private readonly string $appName,
    ) {
    }

    public function requestReset(string $email, ?string $requestedIp = null): void
    {
        $user = $this->userRepository->findByEmail($email);
        if (!$user) {
            return;
        }

        $resetCode = (string) random_int(100000, 999999);
        $user
            ->setResetTokenHash(hash('sha256', $resetCode))
            ->setResetExpiresAt(new \DateTimeImmutable('+15 minutes'));

        $this->userRepository->getEntityManager()->flush();

        $fromAddress = filter_var($this->mailerFrom, FILTER_VALIDATE_EMAIL) ? $this->mailerFrom : 'no-reply@vos.local';
        $appName = trim($this->appName) !== '' ? $this->appName : 'VOS';

        $message = (new Email())
            ->from(new Address($fromAddress, $appName))
            ->to(new Address((string) $user->getEmail(), trim((string) ($user->getPrenom() . ' ' . $user->getNom()))))
            ->subject('Code de réinitialisation de votre mot de passe')
            ->html(sprintf(
                '<p>Bonjour %s,</p><p>Vous avez demandé la réinitialisation de votre mot de passe.</p><p><strong>Code de réinitialisation : %s</strong></p><p>Ce code expire dans 15 minutes.</p><p>Si vous n\'êtes pas à l\'origine de cette demande, ignorez cet email.</p>',
                htmlspecialchars((string) ($user->getPrenom() ?? 'utilisateur'), ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($resetCode, ENT_QUOTES, 'UTF-8')
            ))
            ->text("Bonjour,\n\nVous avez demandé la réinitialisation de votre mot de passe.\n\nCode de réinitialisation: {$resetCode}\n\nCe code expire dans 15 minutes.\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez cet email.");

        try {
            $this->mailer->send($message);
        } catch (TransportExceptionInterface $exception) {
            throw new \RuntimeException('Impossible d\'envoyer l\'email de réinitialisation pour le moment.');
        }
    }

    public function resetPasswordWithCode(string $email, string $code, string $newPassword): bool
    {
        $user = $this->userRepository->findByEmail($email);
        if (!$user || !$user->getResetTokenHash() || !$user->getResetExpiresAt()) {
            return false;
        }

        if ($user->getResetExpiresAt() <= new \DateTimeImmutable()) {
            return false;
        }

        $isCodeValid = hash_equals((string) $user->getResetTokenHash(), hash('sha256', $code));
        if (!$isCodeValid) {
            return false;
        }

        if (!$user) {
            return false;
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setMotDePasse($hashedPassword);
        $user
            ->setResetTokenHash(null)
            ->setResetExpiresAt(null);

        $this->userRepository->getEntityManager()->flush();

        return true;
    }
}
