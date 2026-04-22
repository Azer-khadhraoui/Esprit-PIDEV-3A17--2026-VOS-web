<?php

namespace App\Command;

use App\Repository\ContratEmbaucheRepository;
use App\Service\ContractReminderAiService;
use App\Service\RecrutementNotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:contracts:send-reminders',
    description: 'Send contract reminder emails to the related candidate.',
)]
class SendContractReminderEmailsCommand extends Command
{
    public function __construct(
        private readonly ContratEmbaucheRepository $contratRepository,
        private readonly ContractReminderAiService $contractReminderAiService,
        private readonly RecrutementNotificationService $recrutementNotificationService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'max-days',
                null,
                InputOption::VALUE_OPTIONAL,
                'Send reminders for contracts ending in this many days or fewer.',
                30
            )
            ->addOption(
                'contract-id',
                null,
                InputOption::VALUE_OPTIONAL,
                'Send a reminder only for one contract id.'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Show what would be sent without sending emails.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $maxDays = max(0, (int) $input->getOption('max-days'));
        $contractId = $input->getOption('contract-id');
        $dryRun = (bool) $input->getOption('dry-run');

        $today = new \DateTimeImmutable('today');
        $matchedContracts = [];

        if ($contractId !== null) {
            if (!ctype_digit((string) $contractId)) {
                $io->error('The --contract-id option must be a valid integer.');

                return Command::INVALID;
            }

            $contract = $this->contratRepository->find((int) $contractId);
            if ($contract === null) {
                $io->error(sprintf('Contract #%d was not found.', (int) $contractId));

                return Command::FAILURE;
            }

            $dateFin = $contract->getDateFin();
            if ($dateFin === null) {
                $io->error(sprintf('Contract #%d has no end date.', $contract->getId() ?? 0));

                return Command::FAILURE;
            }

            $matchedContracts[] = [
                $contract,
                (int) $today->diff(\DateTimeImmutable::createFromInterface($dateFin))->format('%r%a'),
            ];
        } else {
            $endDate = $today->modify('+' . $maxDays . ' days');
            $contracts = $this->contratRepository->findEndingBetween($today, $endDate);

            foreach ($contracts as $contract) {
                $dateFin = $contract->getDateFin();
                if ($dateFin === null) {
                    continue;
                }

                $daysRemaining = (int) $today->diff(\DateTimeImmutable::createFromInterface($dateFin))->format('%r%a');
                if ($daysRemaining < 0 || $daysRemaining > $maxDays) {
                    continue;
                }

                $matchedContracts[] = [$contract, $daysRemaining];
            }
        }

        if ($matchedContracts === []) {
            $io->warning(sprintf('No contracts end in %d day(s) or fewer.', $maxDays));

            return Command::SUCCESS;
        }

        $sentCount = 0;
        $skippedCount = 0;

        foreach ($matchedContracts as [$contract, $daysRemaining]) {
            $message = $this->contractReminderAiService->generateReminderMessage($contract, $daysRemaining);

            if ($dryRun) {
                $io->text(sprintf(
                    '[DRY RUN] Contract #%d -> %d day(s) remaining -> %s',
                    $contract->getId() ?? 0,
                    $daysRemaining,
                    $message
                ));
                ++$sentCount;
                continue;
            }

            $sent = $this->recrutementNotificationService->notifyContractReminder($contract, $message, $daysRemaining);
            if ($sent) {
                ++$sentCount;
                $io->success(sprintf(
                    'Reminder email sent for contract #%d (%d day(s) remaining).',
                    $contract->getId() ?? 0,
                    $daysRemaining
                ));
            } else {
                ++$skippedCount;
                $io->warning(sprintf(
                    'Skipped contract #%d because no linked candidate email was found.',
                    $contract->getId() ?? 0
                ));
            }
        }

        $io->note(sprintf(
            'Processed %d contract(s): %d sent, %d skipped. AI configured: %s.',
            count($matchedContracts),
            $sentCount,
            $skippedCount,
            $this->contractReminderAiService->isConfigured() ? 'yes' : 'no'
        ));

        return Command::SUCCESS;
    }
}
