<?php

namespace App\Tests\Service;

use App\Entity\Candidature;
use App\Entity\Entretien;
use App\Entity\EvaluationEntretien;
use App\Entity\User;
use App\Repository\CandidatureRepository;
use App\Repository\UserRepository;
use App\Service\EntretienNotificationService;
use App\Service\GoogleCalendarService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class EntretienNotificationServiceTest extends TestCase
{
    public function testNotifyEntretienCreatedSendsEmailToAdminAndCandidate(): void
    {
        $admin = (new User())
            ->setPrenom('Admin')
            ->setNom('RH')
            ->setEmail('admin@example.com');

        $candidate = (new User())
            ->setPrenom('Nadia')
            ->setNom('Benali')
            ->setEmail('candidate@example.com');

        $candidature = (new Candidature())
            ->setIdUtilisateur(20);

        $entretien = (new Entretien())
            ->setIdUtilisateur(10)
            ->setIdCandidature(100)
            ->setDateEntretien(new \DateTime('2026-05-10'))
            ->setHeureEntretien(new \DateTime('14:30'))
            ->setTypeEntretien('TECHNIQUE')
            ->setStatutEntretien('Terminé')
            ->setLieu('Tunis');

        $mailer = $this->createMock(MailerInterface::class);
        $twig = $this->createMock(Environment::class);
        $userRepository = $this->createMock(UserRepository::class);
        $candidatureRepository = $this->createMock(CandidatureRepository::class);
        $calendarService = $this->createMock(GoogleCalendarService::class);

        $userRepository->method('find')->willReturnCallback(static function (int $id) use ($admin, $candidate): ?User {
            return match ($id) {
                10 => $admin,
                20 => $candidate,
                default => null,
            };
        });

        $candidatureRepository->method('find')->with(100)->willReturn($candidature);
        $twig->expects($this->exactly(2))
            ->method('render')
            ->willReturn('<html>notification</html>');
        $calendarService->expects($this->once())
            ->method('generateAddToCalendarLink')
            ->willReturn('https://calendar.example/link');

        $sentEmails = [];
        $mailer->expects($this->exactly(2))
            ->method('send')
            ->willReturnCallback(static function (Email $email) use (&$sentEmails): void {
                $sentEmails[] = $email;
            });

        $service = new EntretienNotificationService(
            $mailer,
            $userRepository,
            $candidatureRepository,
            $twig,
            'noreply@example.com',
            $calendarService,
        );

        $sentCount = $service->notifyEntretienCreated($entretien);

        $this->assertSame(2, $sentCount);
        $this->assertCount(2, $sentEmails);

        $recipientAddresses = array_map(static fn (Email $email): string => $email->getTo()[0]->getAddress(), $sentEmails);
        sort($recipientAddresses);
        $this->assertSame(['admin@example.com', 'candidate@example.com'], $recipientAddresses);

        foreach ($sentEmails as $email) {
            $this->assertSame('Entretien #0 termine', $email->getSubject());
            $this->assertSame('noreply@example.com', $email->getFrom()[0]->getAddress());
        }
    }

    public function testNotifyEvaluationCreatedReturnsZeroWithoutLinkedEntretien(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $twig = $this->createMock(Environment::class);
        $userRepository = $this->createMock(UserRepository::class);
        $candidatureRepository = $this->createMock(CandidatureRepository::class);
        $calendarService = $this->createMock(GoogleCalendarService::class);

        $mailer->expects($this->never())->method('send');
        $twig->expects($this->never())->method('render');
        $userRepository->expects($this->never())->method('find');
        $candidatureRepository->expects($this->never())->method('find');
        $calendarService->expects($this->never())->method('generateAddToCalendarLink');

        $service = new EntretienNotificationService(
            $mailer,
            $userRepository,
            $candidatureRepository,
            $twig,
            'noreply@example.com',
            $calendarService,
        );

        $evaluation = new EvaluationEntretien();

        $this->assertSame(0, $service->notifyEvaluationCreated($evaluation));
    }
}
