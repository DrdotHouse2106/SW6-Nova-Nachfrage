<?php declare(strict_types=1);

namespace Nova\Nachfrage;

use Doctrine\DBAL\Connection;
use Nova\Nachfrage\Installer\MailTemplateInstaller;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;

/**
 * Nova Nachfrage – einige Tage nach dem Versand eine Mail mit Bitte um Bewertung der gekauften Artikel
 * und Hinweis auf eigene ähnliche Produkte (Bestandskundenwerbung), mit dauerhaft gespeichertem Widerspruch.
 */
class NovaNachfrage extends Plugin
{
    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);
        $this->mailTemplates()?->install();
    }

    public function update(UpdateContext $updateContext): void
    {
        parent::update($updateContext);
        $this->mailTemplates()?->install();
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $connection = $this->connection();
        if ($connection === null) {
            return;
        }
        (new MailTemplateInstaller($connection))->uninstall();
        // Achtung: Mit der Tabelle gehen auch die gespeicherten Widersprüche verloren (siehe README)
        $connection->executeStatement('DROP TABLE IF EXISTS `nova_nachfrage_log`');
        $connection->executeStatement('DROP TABLE IF EXISTS `nova_nachfrage_optout`');
        $connection->executeStatement('DELETE FROM `system_config` WHERE `configuration_key` LIKE \'NovaNachfrage.%\'');
    }

    private function mailTemplates(): ?MailTemplateInstaller
    {
        $connection = $this->connection();

        return $connection === null ? null : new MailTemplateInstaller($connection);
    }

    private function connection(): ?Connection
    {
        $connection = $this->container?->get(Connection::class);

        return $connection instanceof Connection ? $connection : null;
    }
}
