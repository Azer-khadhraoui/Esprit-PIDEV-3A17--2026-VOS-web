<?php

namespace App\Service;

use App\Entity\OffreEmploi;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class OffreNotificationService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UserRepository $userRepository,
        private readonly RouterInterface $router,
        private readonly LoggerInterface $logger,
        private readonly string $mailerFromAddress,
        private readonly string $mailerFromName,
    ) {
    }

    /**
     * @return array{total: int, sent: int, failed: int}
     */
    public function notifyClientsForNewOffer(OffreEmploi $offre): array
    {
        $clientEmails = $this->userRepository->findClientEmails();

        $stats = [
            'total' => count($clientEmails),
            'sent' => 0,
            'failed' => 0,
        ];

        if ($clientEmails === []) {
            return $stats;
        }

        $offerUrl = $this->router->generate('client_opportunites', [], UrlGeneratorInterface::ABSOLUTE_URL);

        foreach ($clientEmails as $email) {
            try {
                $message = (new TemplatedEmail())
                    ->from(new Address($this->mailerFromAddress, $this->mailerFromName))
                    ->to($email)
                    ->subject('Nouvelle offre disponible: '.($offre->getTitre() ?? 'Offre VOS'))
                    ->htmlTemplate('emails/new_offre_notification.html.twig')
                    ->context([
                        'offre' => $offre,
                        'offerUrl' => $offerUrl,
                    ]);

                $this->mailer->send($message);
                ++$stats['sent'];
            } catch (\Throwable $exception) {
                ++$stats['failed'];

                $this->logger->error('Echec envoi email nouvelle offre.', [
                    'email' => $email,
                    'offer_id' => $offre->getIdOffre(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $stats;
    }
}
