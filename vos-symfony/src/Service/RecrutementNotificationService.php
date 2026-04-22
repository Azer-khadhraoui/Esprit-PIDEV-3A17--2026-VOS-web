<?php

namespace App\Service;

use App\Entity\ContratEmbauche;
use App\Entity\Recrutement;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class RecrutementNotificationService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
        private readonly Environment $twig,
        private readonly string $mailerFromAddress,
    ) {
    }

    public function notifyDecision(Recrutement $recrutement): void
    {
        $decision = trim((string) ($recrutement->getDecisionFinale() ?? ''));
        $candidate = $this->resolveCandidatFromRecrutement($recrutement);
        if ($candidate === null || !$candidate->getEmail()) {
            return;
        }

        $date = $recrutement->getDateDecision()?->format('d/m/Y') ?? 'Non definie';
        $html = $this->twig->render('emails/recrutement_decision.html.twig', [
            'recipientName' => $this->displayName($candidate),
            'recipientRole' => 'candidat',
            'recrutementId' => $recrutement->getId() ?? 0,
            'decision' => $decision !== '' ? $decision : 'En attente',
            'dateDecision' => $date,
            'calendarLink' => $this->generateAddToCalendarLink($recrutement),
        ]);

        $subject = $this->decisionSubject($recrutement->getId() ?? 0, $decision, 'candidat');

        $this->mailer->send(
            (new Email())
                ->from($this->mailerFromAddress)
                ->to((string) $candidate->getEmail())
                ->subject($subject)
                ->html($html)
        );
    }

    public function notifyContratCreated(ContratEmbauche $contrat): void
    {
        $this->sendContratNotification($contrat, 'creation');
    }

    public function notifyContratUpdated(ContratEmbauche $contrat): void
    {
        $this->sendContratNotification($contrat, 'mise_a_jour');
    }

    public function notifyContractReminder(ContratEmbauche $contrat, string $reminderMessage, int $daysRemaining): bool
    {
        $recrutement = $this->findRecrutement($contrat->getIdRecrutement());
        if ($recrutement === null) {
            return false;
        }

        $candidate = $this->resolveCandidatFromRecrutement($recrutement);
        if ($candidate === null || !$candidate->getEmail()) {
            return false;
        }

        $html = $this->twig->render('emails/contract_reminder.html.twig', [
            'recipientName' => $this->displayName($candidate),
            'contratId' => $contrat->getId() ?? 0,
            'typeContrat' => $contrat->getTypeContrat() ?? 'Non defini',
            'dateFin' => $contrat->getDateFin()?->format('d/m/Y') ?? 'Non definie',
            'daysRemaining' => $daysRemaining,
            'status' => $contrat->getStatus() ?? 'Non defini',
            'volumeHoraire' => $contrat->getVolumeHoraire() ?? 'Non defini',
            'salaire' => $contrat->getSalaire(),
            'reminderMessage' => $reminderMessage,
        ]);

        $subject = sprintf(
            'Rappel contrat #%d - echeance dans %d jour(s)',
            $contrat->getId() ?? 0,
            $daysRemaining
        );

        $this->mailer->send(
            (new Email())
                ->from($this->mailerFromAddress)
                ->to((string) $candidate->getEmail())
                ->subject($subject)
                ->html($html)
        );

        return true;
    }

    private function sendContratNotification(ContratEmbauche $contrat, string $action): void
    {
        $recrutement = $this->findRecrutement($contrat->getIdRecrutement());
        if ($recrutement === null) {
            return;
        }

        $recipients = $this->resolveRecipients($recrutement);
        if ($recipients === []) {
            return;
        }

        $prefix = $action === 'mise_a_jour' ? '[Mise a jour] ' : '';
        $subject = sprintf('%sContrat #%d - %s', $prefix, $contrat->getId() ?? 0, $contrat->getTypeContrat() ?? 'Contrat');

        foreach ($recipients as $recipient) {
            $html = $this->twig->render('emails/contrat_created.html.twig', [
                'recipientName' => $recipient['name'],
                'recipientRole' => $recipient['role'],
                'contratId' => $contrat->getId() ?? 0,
                'typeContrat' => $contrat->getTypeContrat() ?? 'Non defini',
                'dateDebut' => $contrat->getDateDebut()?->format('d/m/Y') ?? 'Non definie',
                'dateFin' => $contrat->getDateFin()?->format('d/m/Y') ?? 'Non definie',
                'salaire' => $contrat->getSalaire(),
                'status' => $contrat->getStatus() ?? 'Non defini',
                'volumeHoraire' => $contrat->getVolumeHoraire() ?? 'Non defini',
                'avantages' => $contrat->getAvantages() ?: 'Aucun',
                'action' => $action,
            ]);

            $this->mailer->send(
                (new Email())
                    ->from($this->mailerFromAddress)
                    ->to($recipient['email'])
                    ->subject($subject)
                    ->html($html)
            );
        }
    }

    /**
     * @return array<int, array{email: string, role: string, name: string}>
     */
    private function resolveRecipients(Recrutement $recrutement): array
    {
        $recipients = [];

        $adminUser = $this->findUser($recrutement->getIdUtilisateur());
        if ($adminUser !== null && $adminUser->getEmail()) {
            $recipients[] = [
                'email' => (string) $adminUser->getEmail(),
                'role' => 'admin',
                'name' => $this->displayName($adminUser),
            ];
        }

        $candidat = $this->resolveCandidatFromRecrutement($recrutement);
        if ($candidat !== null && $candidat->getEmail()) {
            $email = (string) $candidat->getEmail();
            $alreadyAdded = false;
            foreach ($recipients as $recipient) {
                if ($recipient['email'] === $email) {
                    $alreadyAdded = true;
                    break;
                }
            }

            if (!$alreadyAdded) {
                $recipients[] = [
                    'email' => $email,
                    'role' => 'candidat',
                    'name' => $this->displayName($candidat),
                ];
            }
        }

        return $recipients;
    }

    private function isFinalDecision(string $decision): bool
    {
        $normalizedDecision = strtolower($decision);

        return str_contains($normalizedDecision, 'accept') || str_contains($normalizedDecision, 'refus');
    }

    private function generateAddToCalendarLink(Recrutement $recrutement): ?string
    {
        $date = $recrutement->getDateDecision();
        if ($date === null) {
            return null;
        }

        $startDate = \DateTimeImmutable::createFromInterface($date);
        $endDate = $startDate->modify('+1 day');
        $decision = trim((string) ($recrutement->getDecisionFinale() ?? ''));
        $decision = $decision !== '' ? $decision : 'En attente';

        return 'https://calendar.google.com/calendar/render?' . http_build_query([
            'action' => 'TEMPLATE',
            'text' => sprintf('Decision recrutement - %s', $decision),
            'dates' => $startDate->format('Ymd') . '/' . $endDate->format('Ymd'),
            'details' => sprintf(
                "Decision de recrutement #%d\nDecision: %s\nDate: %s",
                $recrutement->getId() ?? 0,
                $decision,
                $startDate->format('d/m/Y')
            ),
        ]);
    }

    private function resolveCandidatFromRecrutement(Recrutement $recrutement): ?User
    {
        $entretienId = $recrutement->getIdEntretien();
        if ($entretienId === null) {
            return null;
        }

        $row = $this->em->getConnection()->executeQuery(
            'SELECT ca.id_utilisateur
             FROM entretien e
             INNER JOIN candidature ca ON ca.id_candidature = e.id_candidature
             WHERE e.id_entretien = :id
             LIMIT 1',
            ['id' => $entretienId]
        )->fetchAssociative();

        if ($row === false || !isset($row['id_utilisateur'])) {
            return null;
        }

        return $this->findUser((int) $row['id_utilisateur']);
    }

    private function findRecrutement(?int $id): ?Recrutement
    {
        if ($id === null) {
            return null;
        }

        $result = $this->em->getRepository(Recrutement::class)->find($id);

        return $result instanceof Recrutement ? $result : null;
    }

    private function findUser(?int $id): ?User
    {
        if ($id === null) {
            return null;
        }

        $result = $this->userRepository->find($id);

        return $result instanceof User ? $result : null;
    }

    private function displayName(User $user): string
    {
        $name = trim(sprintf('%s %s', (string) $user->getPrenom(), (string) $user->getNom()));

        return $name !== '' ? $name : 'Utilisateur';
    }

    private function decisionSubject(int $id, string $decision, string $role): string
    {
        $dec = strtolower($decision);

        if ($role === 'admin') {
            return sprintf('Decision recrutement #%d enregistree - %s', $id, $decision);
        }

        return match (true) {
            str_contains($dec, 'accept') => sprintf('Bonne nouvelle - Votre candidature #%d a ete acceptee', $id),
            str_contains($dec, 'refus') => sprintf('Resultat recrutement #%d - Candidature non retenue', $id),
            default => sprintf('Mise a jour recrutement #%d - %s', $id, $decision),
        };
    }
}
