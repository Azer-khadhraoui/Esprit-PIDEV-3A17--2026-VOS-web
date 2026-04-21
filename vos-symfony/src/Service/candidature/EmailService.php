<?php

namespace App\Service\candidature;

use App\Entity\Candidature;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class EmailService
{
    private const FROM_EMAIL = 'noreply@vos-recrutement.com';
    private const FROM_NAME = 'VOS - Plateforme de Recrutement';

    public function __construct(
        private MailerInterface $mailer,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Envoyer un email de confirmation de candidature
     */
    public function sendCandidatureCreatedEmail(Candidature $candidature, User $user, string $offreTitre): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address(self::FROM_EMAIL, self::FROM_NAME))
            ->to($user->getEmail())
            ->subject('VOS - Candidature confirmée')
            ->htmlTemplate('emails/candidature/created.html.twig')
            ->context([
                'user' => $user,
                'candidature' => $candidature,
                'offreTitre' => $offreTitre,
            ]);

        $this->mailer->send($email);
    }

    /**
     * Envoyer un email de modification de candidature
     */
    public function sendCandidatureUpdatedEmail(Candidature $candidature, User $user, string $offreTitre): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address(self::FROM_EMAIL, self::FROM_NAME))
            ->to($user->getEmail())
            ->subject('VOS - Candidature mise à jour')
            ->htmlTemplate('emails/candidature/updated.html.twig')
            ->context([
                'user' => $user,
                'candidature' => $candidature,
                'offreTitre' => $offreTitre,
            ]);

        $this->mailer->send($email);
    }

    /**
     * Envoyer un email de suppression de candidature
     */
    public function sendCandidatureDeletedEmail(string $userEmail, string $userName, string $offreTitre): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address(self::FROM_EMAIL, self::FROM_NAME))
            ->to($userEmail)
            ->subject('VOS - Candidature supprimée')
            ->htmlTemplate('emails/candidature/deleted.html.twig')
            ->context([
                'userName' => $userName,
                'offreTitre' => $offreTitre,
            ]);

        $this->mailer->send($email);
    }

    /**
     * Notifier tous les admins d'une nouvelle candidature
     */
    public function notifyAdminsNewCandidature(Candidature $candidature, User $user, string $offreTitre): void
    {
        // Récupérer tous les admins
        $admins = $this->entityManager->getRepository(User::class)
            ->findBy(['role' => 'ADMIN_RH']);

        foreach ($admins as $admin) {
            $email = (new TemplatedEmail())
                ->from(new Address(self::FROM_EMAIL, self::FROM_NAME))
                ->to($admin->getEmail())
                ->subject('VOS - Nouvelle candidature reçue')
                ->htmlTemplate('emails/candidature/admin_new.html.twig')
                ->context([
                    'admin' => $admin,
                    'candidature' => $candidature,
                    'candidat' => $user,
                    'offreTitre' => $offreTitre,
                ]);

            $this->mailer->send($email);
        }
    }

    /**
     * Notifier tous les admins d'une modification de candidature
     */
    public function notifyAdminsUpdatedCandidature(Candidature $candidature, User $user, string $offreTitre): void
    {
        $admins = $this->entityManager->getRepository(User::class)
            ->findBy(['role' => 'ADMIN_RH']);

        foreach ($admins as $admin) {
            $email = (new TemplatedEmail())
                ->from(new Address(self::FROM_EMAIL, self::FROM_NAME))
                ->to($admin->getEmail())
                ->subject('VOS - Candidature mise à jour')
                ->htmlTemplate('emails/candidature/admin_updated.html.twig')
                ->context([
                    'admin' => $admin,
                    'candidature' => $candidature,
                    'candidat' => $user,
                    'offreTitre' => $offreTitre,
                ]);

            $this->mailer->send($email);
        }
    }

    /**
     * Notifier tous les admins d'une suppression de candidature
     */
    public function notifyAdminsDeletedCandidature(string $userName, string $userEmail, string $offreTitre): void
    {
        $admins = $this->entityManager->getRepository(User::class)
            ->findBy(['role' => 'ADMIN_RH']);

        foreach ($admins as $admin) {
            $email = (new TemplatedEmail())
                ->from(new Address(self::FROM_EMAIL, self::FROM_NAME))
                ->to($admin->getEmail())
                ->subject('VOS - Candidature supprimée')
                ->htmlTemplate('emails/candidature/admin_deleted.html.twig')
                ->context([
                    'admin' => $admin,
                    'candidatName' => $userName,
                    'candidatEmail' => $userEmail,
                    'offreTitre' => $offreTitre,
                ]);

            $this->mailer->send($email);
        }
    }
}
