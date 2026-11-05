<?php declare(strict_types=1);

namespace Nova\Nachfrage\Command;

use Nova\Nachfrage\Service\NachfrageSender;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Fällige Nachfrage-Mails sofort versenden (wie die stündliche Aufgabe) oder mit --dry-run nur anzeigen,
 * wer was bekäme.
 */
#[AsCommand(name: 'nova-nachfrage:send', description: 'Fällige Nachfrage-Mails versenden (mit --dry-run nur anzeigen)')]
class SendCommand extends Command
{
    public function __construct(private readonly NachfrageSender $sender)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Nichts senden und nichts speichern, nur anzeigen')
            ->addOption('pending', null, InputOption::VALUE_NONE, 'Auch noch nicht fällige Bestellungen mit Fälligkeit anzeigen')
            ->addOption('sales-channel', null, InputOption::VALUE_REQUIRED, 'Nur dieser Verkaufskanal (ID)')
            ->addOption('order', null, InputOption::VALUE_REQUIRED, 'Nur diese Bestellnummer')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Höchstens so viele Mails', (string) NachfrageSender::DEFAULT_LIMIT);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');
        $salesChannel = $input->getOption('sales-channel');
        $order = $input->getOption('order');
        $limit = $input->getOption('limit');
        $report = $this->sender->run(
            $dryRun,
            \is_string($salesChannel) && $salesChannel !== '' ? $salesChannel : null,
            \is_string($order) && $order !== '' ? $order : null,
            is_numeric($limit) ? max(0, (int) $limit) : NachfrageSender::DEFAULT_LIMIT,
            (bool) $input->getOption('pending'),
        );

        if ($report === []) {
            $output->writeln('Keine fälligen Bestellungen (Plugin in einem Verkaufskanal aktiv? Lieferstatus „versendet“?).');

            return self::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Bestellung', 'Empfänger', 'Aktion', 'Grund', 'Fällig ab (UTC)', 'Artikel', 'Ähnliche Produkte']);
        foreach ($report as $row) {
            $table->addRow([
                $row['orderNumber'],
                $row['email'],
                $row['action'],
                $row['reason'] ?? '',
                $row['dueAt'] ?? '',
                implode(', ', $row['products']),
                implode(', ', $row['recommendations']),
            ]);
        }
        $table->render();
        $counts = array_count_values(array_column($report, 'action'));
        $output->writeln(implode(', ', array_map(static fn (string $k, int $v): string => $k . ': ' . $v, array_keys($counts), $counts)));
        if ($dryRun) {
            $output->writeln('<comment>Probelauf – es wurde nichts gesendet oder gespeichert.</comment>');
        }

        return self::SUCCESS;
    }
}
