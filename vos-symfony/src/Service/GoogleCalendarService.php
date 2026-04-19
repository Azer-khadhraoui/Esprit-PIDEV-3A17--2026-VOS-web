<?php

namespace App\Service;

use App\Entity\Entretien;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class GoogleCalendarService
{
    private Calendar $calendarService;
    private string $calendarId;

    public function __construct(
        #[Autowire(env: 'GOOGLE_CALENDAR_ID')] string $calendarId,
        #[Autowire(env: 'GOOGLE_SERVICE_ACCOUNT_JSON_PATH')] string $jsonPath,
        #[Autowire('%kernel.project_dir%')] string $projectDir,
    ) {
        $this->calendarId = $calendarId;

        $absolutePath = str_starts_with($jsonPath, '/') || preg_match('/^[A-Za-z]:/', $jsonPath)
            ? $jsonPath
            : $projectDir . '/' . ltrim($jsonPath, '/\\');

        $client = new Client();
        $client->setAuthConfig($absolutePath);
        $client->setScopes([Calendar::CALENDAR]);
        $client->setApplicationName('VOS Interview Manager');

        $this->calendarService = new Calendar($client);
    }

    public function createEvent(Entretien $entretien): string
    {
        $event = $this->buildEvent($entretien);
        $created = $this->calendarService->events->insert($this->calendarId, $event);

        return $created->getId();
    }

    public function updateEvent(string $eventId, Entretien $entretien): void
    {
        $event = $this->buildEvent($entretien);
        $this->calendarService->events->update($this->calendarId, $eventId, $event);
    }

    public function deleteEvent(string $eventId): void
    {
        $this->calendarService->events->delete($this->calendarId, $eventId);
    }

    private function buildEvent(Entretien $entretien): Event
    {
        $type    = $entretien->getTypeEntretien() ?? 'Entretien';
        $statut  = $entretien->getStatutEntretien() ?? '';
        $typeTest = $entretien->getTypeTest() ?? '';
        $lieu    = $entretien->getLieu() ?? '';

        $description = implode("\n", array_filter([
            $type  ? "Type : $type" : null,
            $typeTest ? "Test : $typeTest" : null,
            $statut ? "Statut : $statut" : null,
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
                'timeZone' => 'Africa/Tunis',
            ]));
            $event->setEnd(new EventDateTime([
                'dateTime' => $endDt->format(\DateTime::RFC3339),
                'timeZone' => 'Africa/Tunis',
            ]));
        } elseif ($date !== null) {
            $event->setStart(new EventDateTime(['date' => $date->format('Y-m-d')]));
            $event->setEnd(new EventDateTime(['date' => $date->modify('+1 day')->format('Y-m-d')]));
        }

        return $event;
    }
}
