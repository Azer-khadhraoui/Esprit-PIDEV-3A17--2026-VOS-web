<?php

namespace App\Service;

use App\Entity\Entretien;
use App\Entity\EvaluationEntretien;
use App\Entity\User;
use App\Repository\CandidatureRepository;
use App\Repository\UserRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class EntretienNotificationService
{
    private const NOT_DEFINED_FEMININE = 'Non definie';
    private const NOT_DEFINED_MASCULINE = 'Non defini';
    private const ROLE_CANDIDATE = 'candidat';
    private const ROLE_ADMIN = 'admin';
    private const ACTION_CREATION = 'creation';
    private const ACTION_MISE_A_JOUR = 'mise_a_jour';

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UserRepository $userRepository,
        private readonly CandidatureRepository $candidatureRepository,
        private readonly Environment $twig,
        private readonly string $mailerFromAddress,
        private readonly GoogleCalendarService $calendarService,
    ) {
    }

    public function notifyEntretienCreated(Entretien $entretien): int
    {
        return $this->sendEntretienNotification($entretien, self::ACTION_CREATION);
    }

    public function notifyEntretienUpdated(Entretien $entretien): int
    {
        return $this->sendEntretienNotification($entretien, self::ACTION_MISE_A_JOUR);
    }

    public function notifyEvaluationCreated(EvaluationEntretien $evaluation): int
    {
        return $this->sendEvaluationNotification($evaluation, self::ACTION_CREATION);
    }

    public function notifyEvaluationUpdated(EvaluationEntretien $evaluation): int
    {
        return $this->sendEvaluationNotification($evaluation, self::ACTION_MISE_A_JOUR);
    }

    private function sendEntretienNotification(Entretien $entretien, string $action): int
    {
        $recipients = $this->resolveRecipients($entretien);
        if ([] === $recipients) {
            return 0;
        }

        $date = $entretien->getDateEntretien()?->format('d/m/Y') ?? self::NOT_DEFINED_FEMININE;
        $heure = $entretien->getHeureEntretien()?->format('H:i') ?? self::NOT_DEFINED_FEMININE;
        $type = $entretien->getTypeEntretien() ?? self::NOT_DEFINED_MASCULINE;
        $lieu = $entretien->getLieu() ?: self::NOT_DEFINED_MASCULINE;
        $statut = $entretien->getStatutEntretien() ?? self::NOT_DEFINED_MASCULINE;
        $lienReunion = $entretien->getLienReunion() ?: 'Aucun lien';
        $subject = $this->buildEntretienSubject($entretien, $action);

        $calendarLink = $this->calendarService->generateAddToCalendarLink($entretien);

        foreach ($recipients as $recipient) {
            $htmlBody = $this->twig->render('emails/entretien_notification.html.twig', [
                'recipientName' => $recipient['name'],
                'recipientRole' => $recipient['role'],
                'entretienId' => $entretien->getId() ?? 0,
                'type' => $type,
                'date' => $date,
                'heure' => $heure,
                'lieu' => $lieu,
                'statut' => $statut,
                'lienReunion' => $lienReunion,
                'action' => $action,
                'calendarLink' => $recipient['role'] === self::ROLE_CANDIDATE ? $calendarLink : null,
            ]);

            $textBody = implode("\n", [
                sprintf('Bonjour %s,', $recipient['name']),
                '',
                $action === self::ACTION_CREATION
                    ? 'Un nouvel entretien a ete planifie.'
                    : 'Les informations de votre entretien ont ete mises a jour.',
                sprintf('Type: %s', $type),
                sprintf('Date: %s', $date),
                sprintf('Heure: %s', $heure),
                sprintf('Lieu: %s', $lieu),
                sprintf('Statut: %s', $statut),
                sprintf('Lien reunion: %s', $lienReunion),
            ]);

            $this->mailer->send(
                (new Email())
                    ->from($this->mailerFromAddress)
                    ->to($recipient['email'])
                    ->subject($subject)
                    ->text($textBody)
                    ->html($htmlBody)
            );
        }

        return count($recipients);
    }

    private function sendEvaluationNotification(EvaluationEntretien $evaluation, string $action): int
    {
        $entretien = $evaluation->getEntretien();
        if (null === $entretien) {
            return 0;
        }

        $recipients = $this->resolveRecipients($entretien);
        if ([] === $recipients) {
            return 0;
        }

        $decision = $evaluation->getDecision() ?? 'Non definie';
        $note = $evaluation->getNoteEntretien();
        $score = $evaluation->getScoreTest();
        $commentaire = $evaluation->getCommentaire() ?: 'Aucun commentaire.';
        $date = $entretien->getDateEntretien()?->format('d/m/Y') ?? self::NOT_DEFINED_FEMININE;
        $heure = $entretien->getHeureEntretien()?->format('H:i') ?? self::NOT_DEFINED_FEMININE;

        foreach ($recipients as $recipient) {
            $subject = $this->buildEvaluationSubject($entretien->getId() ?? 0, $decision, $recipient['role'], $action);

            $htmlBody = $this->twig->render('emails/evaluation_notification.html.twig', [
                'recipientName' => $recipient['name'],
                'recipientRole' => $recipient['role'],
                'entretienId' => $entretien->getId() ?? 0,
                'date' => $date,
                'heure' => $heure,
                'decision' => $decision,
                'note' => null !== $note ? (string) $note : self::NOT_DEFINED_FEMININE,
                'score' => null !== $score ? (string) $score : self::NOT_DEFINED_MASCULINE,
                'commentaire' => $commentaire,
                'action' => $action,
            ]);

            $textBody = implode("\n", [
                sprintf('Bonjour %s,', $recipient['name']),
                '',
                $action === self::ACTION_CREATION
                    ? sprintf('Une evaluation a ete enregistree pour l entretien #%d.', $entretien->getId() ?? 0)
                    : sprintf('L evaluation de l entretien #%d a ete mise a jour.', $entretien->getId() ?? 0),
                sprintf('Date entretien: %s', $date),
                sprintf('Heure entretien: %s', $heure),
                sprintf('Decision: %s', $decision),
                sprintf('Note entretien: %s', null !== $note ? (string) $note : self::NOT_DEFINED_FEMININE),
                sprintf('Score test: %s', null !== $score ? (string) $score : self::NOT_DEFINED_MASCULINE),
                sprintf('Commentaire: %s', $commentaire),
            ]);

            $this->mailer->send(
                (new Email())
                    ->from($this->mailerFromAddress)
                    ->to($recipient['email'])
                    ->subject($subject)
                    ->text($textBody)
                    ->html($htmlBody)
            );
        }

        return count($recipients);
    }

    /**
     * @return array<int, array{email: string, role: string, name: string}>
     */
    private function resolveRecipients(Entretien $entretien): array
    {
        $recipients = [];

        $utilisateur = $this->findUserById($entretien->getIdUtilisateur());
        if (null !== $utilisateur && $utilisateur->getEmail()) {
            $recipients[] = [
                'email' => (string) $utilisateur->getEmail(),
                'role' => self::ROLE_ADMIN,
                'name' => $this->buildDisplayName($utilisateur),
            ];
        }

        $candidatureId = $entretien->getIdCandidature();
        if (null !== $candidatureId) {
            $candidature = $this->candidatureRepository->find($candidatureId);
            $candidat = null !== $candidature ? $this->findUserById($candidature->getIdUtilisateur()) : null;

            if (null !== $candidat && $candidat->getEmail()) {
                $recipients[] = [
                    'email' => (string) $candidat->getEmail(),
                    'role' => self::ROLE_CANDIDATE,
                    'name' => $this->buildDisplayName($candidat),
                ];
            }
        }

        $unique = [];
        foreach ($recipients as $recipient) {
            $unique[$recipient['email']] = $recipient;
        }

        return array_values($unique);
    }

    private function findUserById(?int $userId): ?User
    {
        if (null === $userId) {
            return null;
        }

        $user = $this->userRepository->find($userId);
        return $user instanceof User ? $user : null;
    }

    private function buildDisplayName(User $user): string
    {
        $name = trim(sprintf('%s %s', (string) $user->getPrenom(), (string) $user->getNom()));
        return '' !== $name ? $name : 'Utilisateur';
    }

    private function buildEntretienSubject(Entretien $entretien, string $action): string
    {
        $id = $entretien->getId() ?? 0;
        $statut = strtolower((string) $entretien->getStatutEntretien());
        $prefix = $action === self::ACTION_MISE_A_JOUR ? '[Mise a jour] ' : '';

        $statutLabel = match (true) {
            str_contains($statut, 'annul') => sprintf('Entretien #%d annule', $id),
            str_contains($statut, 'term')  => sprintf('Entretien #%d termine', $id),
            str_contains($statut, 'planifi') && $action !== self::ACTION_MISE_A_JOUR => sprintf('Entretien #%d planifie', $id),
            default => sprintf('Entretien #%d', $id),
        };

        return $prefix . $statutLabel;
    }

    private function buildEvaluationSubject(int $entretienId, string $decision, string $recipientRole, string $action): string
    {
        $decisionLower = strtolower($decision);
        $prefix = $action === self::ACTION_MISE_A_JOUR ? '[Mise a jour] ' : '';

        if ($recipientRole === self::ROLE_ADMIN) {
            $adminLabel = $action === self::ACTION_CREATION ? 'Evaluation creee' : 'Evaluation';
            return sprintf('%s%s - entretien #%d', $prefix, $adminLabel, $entretienId);
        }

        $decisionLabel = match (true) {
            str_contains($decisionLower, 'accept') => sprintf('Resultat entretien #%d : candidature acceptee', $entretienId),
            str_contains($decisionLower, 'refus')  => sprintf('Resultat entretien #%d : candidature refusee', $entretienId),
            default => sprintf('Resultat evaluation entretien #%d', $entretienId),
        };

        return $prefix . $decisionLabel;
    }
}
