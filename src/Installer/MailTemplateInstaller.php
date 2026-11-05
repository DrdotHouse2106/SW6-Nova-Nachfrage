<?php declare(strict_types=1);

namespace Nova\Nachfrage\Installer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * E-Mail-Vorlagentyp „Nova Nachfrage“ (technical_name nova_nachfrage) mit Standardvorlage de/en.
 *
 * Wird nur angelegt, wenn der Typ fehlt – Änderungen in Einstellungen → E-Mail-Vorlagen bleiben bei
 * Updates erhalten. Beim Deinstallieren ohne „Daten behalten“ wieder entfernt.
 * Variablen: siehe MailTemplates (Kopf) und README.
 */
final class MailTemplateInstaller
{
    public const TYPE = 'nova_nachfrage';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function install(): void
    {
        $exists = $this->connection->fetchOne('SELECT 1 FROM mail_template_type WHERE technical_name = :t', ['t' => self::TYPE]);
        if ($exists !== false) {
            return;
        }

        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $typeId = Uuid::randomBytes();
        $templateId = Uuid::randomBytes();

        $this->connection->insert('mail_template_type', [
            'id' => $typeId,
            'technical_name' => self::TYPE,
            'available_entities' => json_encode(['order' => 'order', 'salesChannel' => 'sales_channel']),
            'created_at' => $now,
        ]);
        $this->connection->insert('mail_template', [
            'id' => $templateId,
            'mail_template_type_id' => $typeId,
            'system_default' => 1,
            'created_at' => $now,
        ]);

        foreach ($this->languages() as $languageId => $german) {
            $this->connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => $typeId,
                'language_id' => $languageId,
                'name' => $german ? 'Nova Nachfrage: Bewertung und ähnliche Produkte' : 'Nova Follow-up: review request and similar products',
                'created_at' => $now,
            ]);
            $this->connection->insert('mail_template_translation', [
                'mail_template_id' => $templateId,
                'language_id' => $languageId,
                'sender_name' => '{{ salesChannel.translated.name }}',
                'subject' => $german ? MailTemplates::SUBJECT_DE : MailTemplates::SUBJECT_EN,
                'description' => $german
                    ? 'Einige Tage nach dem Versand: Bitte um Bewertung der gekauften Artikel und Hinweis auf eigene ähnliche Produkte (Nova Nachfrage)'
                    : 'A few days after shipping: review request for the purchased items and similar products of our own (Nova Follow-up)',
                'content_html' => $german ? MailTemplates::HTML_DE : MailTemplates::HTML_EN,
                'content_plain' => $german ? MailTemplates::PLAIN_DE : MailTemplates::PLAIN_EN,
                'created_at' => $now,
            ]);
        }
    }

    public function uninstall(): void
    {
        $typeId = $this->connection->fetchOne('SELECT id FROM mail_template_type WHERE technical_name = :t', ['t' => self::TYPE]);
        if ($typeId === false) {
            return;
        }
        $this->connection->executeStatement('DELETE FROM mail_template WHERE mail_template_type_id = :id', ['id' => $typeId]);
        $this->connection->executeStatement('DELETE FROM mail_template_type WHERE id = :id', ['id' => $typeId]);
    }

    /**
     * Alle Sprachen mit Locale plus Systemsprache; true = deutsch.
     *
     * @return array<string, bool>
     */
    private function languages(): array
    {
        $out = [];
        $rows = $this->connection->fetchAllAssociative(
            'SELECT language.id, locale.code FROM language INNER JOIN locale ON locale.id = language.locale_id'
        );
        foreach ($rows as $row) {
            $out[(string) $row['id']] = str_starts_with((string) $row['code'], 'de');
        }
        $system = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        if (!isset($out[$system])) {
            $out[$system] = false;
        }

        return $out;
    }
}
