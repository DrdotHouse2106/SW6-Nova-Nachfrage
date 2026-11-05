<?php declare(strict_types=1);

namespace Nova\Nachfrage\Command;

use Nova\Nachfrage\Service\EmailHash;
use Nova\Nachfrage\Service\OptOutStore;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Widersprüche verwalten: auflisten (nur gekürzter Hash), Adresse hinzufügen (z. B. Widerspruch per Telefon
 * oder Brief), prüfen, ob eine Adresse eingetragen ist, oder einen Eintrag entfernen.
 */
#[AsCommand(name: 'nova-nachfrage:optouts', description: 'Widersprüche (Abmeldungen) auflisten, hinzufügen, prüfen oder entfernen')]
class OptOutCommand extends Command
{
    public function __construct(private readonly OptOutStore $optOuts)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::OPTIONAL, 'list | add | check | remove', 'list')
            ->addArgument('email', InputArgument::OPTIONAL, 'E-Mail-Adresse (bei add, check, remove)')
            ->addOption('sales-channel', null, InputOption::VALUE_REQUIRED, 'add: nur für diesen Verkaufskanal (ID); Standard: alle Kanäle')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'list: Anzahl', '50');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = (string) $input->getArgument('action');
        $email = (string) ($input->getArgument('email') ?? '');

        if ($action === 'list') {
            $limit = $input->getOption('limit');
            $rows = $this->optOuts->list(is_numeric($limit) ? (int) $limit : 50);
            $table = new Table($output);
            $table->setHeaders(['Hash (gekürzt)', 'Seit (UTC)', 'Quelle', 'Verkaufskanal']);
            foreach ($rows as $row) {
                $table->addRow([$row['hash'], $row['createdAt'], $row['source'], $row['salesChannel'] ?? 'alle']);
            }
            $table->render();
            $output->writeln('Gesamt: ' . $this->optOuts->count());

            return self::SUCCESS;
        }

        if (!\in_array($action, ['add', 'check', 'remove'], true)) {
            $output->writeln('<error>Aktion muss list, add, check oder remove sein.</error>');

            return self::INVALID;
        }
        if (!EmailHash::isValidEmail($email)) {
            $output->writeln('<error>Bitte eine gültige E-Mail-Adresse angeben.</error>');

            return self::INVALID;
        }
        $hash = EmailHash::hash($email);

        if ($action === 'add') {
            $salesChannel = $input->getOption('sales-channel');
            $scope = \is_string($salesChannel) && $salesChannel !== '' ? strtolower($salesChannel) : null;
            if ($scope !== null && !Uuid::isValid($scope)) {
                $output->writeln('<error>Ungültige Verkaufskanal-ID.</error>');

                return self::INVALID;
            }
            $new = $this->optOuts->addHash($hash, OptOutStore::SOURCE_CLI, $scope);
            $output->writeln($new ? '<info>Widerspruch eingetragen (' . EmailHash::short($hash) . ').</info>' : 'War bereits eingetragen.');

            return self::SUCCESS;
        }
        if ($action === 'check') {
            $output->writeln($this->optOuts->exists($hash) ? 'Eingetragen (' . EmailHash::short($hash) . ').' : 'Nicht eingetragen.');

            return self::SUCCESS;
        }

        $removed = $this->optOuts->removeHash($hash);
        $output->writeln($removed > 0 ? '<info>Eintrag entfernt. Diese Adresse kann wieder Nachfrage-Mails bekommen.</info>' : 'Nicht eingetragen.');

        return self::SUCCESS;
    }
}
