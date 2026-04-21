<?php

namespace App\Service;

use App\Entity\Entretien;
use App\Exception\GoogleCalendarException;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class GoogleCalendarService
{
    private const TIMEZONE = 'Africa/Tunis';

    private ?Calendar $calendarService = null;
    private string $calendarId;

    public function __construct(
        #[Autowire(env: 'GOOGLE_CALENDAR_ID')] string $calendarId,
        #[Autowire(env: 'GOOGLE_SERVICE_ACCOUNT_JSON_PATH')] string $jsonPath,
        #[Autowire('%kernel.project_dir%')] string $projectDir,
    ) {
        $this->calendarId = $calendarId;

        if ('' === $calendarId) {
            return;
        }

        $absolutePath = str_starts_with($jsonPath, '/') || preg_match('/^[A-Za-z]:/', $jsonPath)
            ? $jsonPath
            : $projectDir . '/' . ltrim($jsonPath, '/\\');

        $client = new Client();
        $client->setAuthConfig($absolutePath);
        $client->setScopes([Calendar::CALENDAR]);
        $client->setApplicationName('VOS Interview Manager');

        $this->calendarService = new Calendar($client);
    }

    public function isConfigured(): bool
    {
        return '' !== $this->calendarId && $this->calendarService !== null;
    }

    public function createEvent(Entretien $entretien): string
    {
        if (!$this->isConfigured()) {
            throw new GoogleCalendarException('Google Calendar non configuré (GOOGLE_CALENDAR_ID manquant).');
        }

        $created = $this->calendarService->events->insert(
            $this->calendarId,
            $this->buildEvent($entretien),
        );

        return $created->getId();
    }

    public function updateEvent(string $eventId, Entretien $entretien): void
    {
        if (!$this->isConfigured()) {
            throw new GoogleCalendarException('Google Calendar non configuré (GOOGLE_CALENDAR_ID manquant).');
        }

        $this->calendarService->events->update(
            $this->calendarId,
            $eventId,
            $this->buildEvent($entretien),
        );
    }

    public function deleteEvent(string $eventId): void
    {
        if (!$this->isConfigured()) {
            return;
        }

        $this->calendarService->events->delete($this->calendarId, $eventId);
    }

    public function generateAddToCalendarLink(Entretien $entretien): ?string
    {
        $date = $entretien->getDateEntretien();
        if ($date === null) {
            return null;
        }

        $type        = $entretien->getTypeEntretien() ?? 'Entretien';
        $typeTest    = $entretien->getTypeTest();
        $statut      = $entretien->getStatutEntretien();
        $lien        = $entretien->getLienReunion();

        $description = implode('\n', array_filter([
            "Type : $type",
            $typeTest ? "Test : $typeTest" : null,
            $statut   ? "Statut : $statut" : null,
            $lien     ? "Lien : $lien"     : null,
        ]));

        $time = $entretien->getHeureEntretien();
        if ($time !== null) {
            $start = new \DateTime(
                $date->format('Y-m-d') . ' ' . $time->format('H:i:s'),
                new \DateTimeZone(self::TIMEZONE),
            );
            $start->setTimezone(new \DateTimeZone('UTC'));
            $end   = (clone $start)->modify('+1 hour');
            $dates = $start->format('Ymd\THis\Z') . '/' . $end->format('Ymd\THis\Z');
        } else {
            $dates = $date->format('Ymd') . '/' . (clone $date)->modify('+1 day')->format('Ymd');
        }

        return 'https://calendar.google.com/calendar/render?' . http_build_query([
            'action'   => 'TEMPLATE',
            'text'     => sprintf('Entretien %s - VOS', $type),
            'dates'    => $dates,
            'details'  => $description,
            'location' => $entretien->getLieu() ?? '',
        ]);
    }

    private function buildEvent(Entretien $entretien): Event
    {
        $type     = $entretien->getTypeEntretien() ?? 'Entretien';
        $statut   = $entretien->getStatutEntretien() ?? '';
        $typeTest = $entretien->getTypeTest() ?? '';
        $lieu     = $entretien->getLieu() ?? '';

        $description = implode("\n", array_filter([
            $type     ? "Type : $type"       : null,
            $typeTest ? "Test : $typeTest"    : null,
            $statut   ? "Statut : $statut"   : null,
            $entretien->getLienReunion() ? "Lien : " . $entretien->getLienReunion() : null,
        ]));

        $event = new Event([
            'summary'     => sprintf('Entretien %s - VOS', $type),
            'location'    => $lieu,
            'description' => $description,
        ]);

        $date = $entretien->getDateEntretien();
        $time = $entretien->getHeureEntretien();

        if ($date !== null && $time !== null) {
            $startDt = new \DateTime($date->format('Y-m-d') . ' ' . $time->format('H:i:s'));
            $endDt   = (clone $startDt)->modify('+1 hour');

            $event->setStart(new EventDateTime([
                'dateTime' => $startDt->format(\DateTime::RFC3339),
                'timeZone' => self::TIMEZONE,
            ]));
            $event->setEnd(new EventDateTime([
                'dateTime' => $endDt->format(\DateTime::RFC3339),
                'timeZone' => self::TIMEZONE,
            ]));
        } elseif ($date !== null) {
            $event->setStart(new EventDateTime(['date' => $date->format('Y-m-d')]));
            $event->setEnd(new EventDateTime(['date' => $date->modify('+1 day')->format('Y-m-d')]));
        }

        return $event;
    }
}
