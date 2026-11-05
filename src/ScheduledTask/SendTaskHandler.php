<?php declare(strict_types=1);

namespace Nova\Nachfrage\ScheduledTask;

use Nova\Nachfrage\Service\NachfrageSender;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: SendTask::class)]
final class SendTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $exceptionLogger,
        private readonly NachfrageSender $sender,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($scheduledTaskRepository, $exceptionLogger);
    }

    public function run(): void
    {
        $report = $this->sender->run();
        $counts = array_count_values(array_column($report, 'action'));
        if ($counts !== []) {
            $this->logger->info('Nova Nachfrage: Lauf beendet', $counts);
        }
    }
}
