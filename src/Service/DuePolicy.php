<?php declare(strict_types=1);

namespace Nova\Nachfrage\Service;

/**
 * Wann bekommt eine Bestellung die Nachfrage-Mail? (reine Logik, ohne Shopware)
 *
 * - Alle Lieferungen der Bestellung (stornierte Lieferungen zählen nicht) stehen auf „versendet“.
 *   „Teilweise versendet“ reicht bewusst nicht: Der Kunde hat noch nicht alles, eine Bewertungsanfrage
 *   und Empfehlungen wären verfrüht. Sobald die Lieferung auf „versendet“ wechselt, zählt dieser Zeitpunkt.
 * - Fällig X Tage nach dem Versand (Zeitpunkt des Wechsels auf „versendet“ aus der Statushistorie).
 * - Nie nachgeholt: Versand länger als „Höchstalter“ Tage her (Schutz beim ersten Einschalten und nach
 *   längeren Pausen der geplanten Aufgaben).
 * - Keine Mail bei stornierten Bestellungen, erstatteten/stornierten Zahlungen und Bestellungen ohne Artikel.
 *   Zurückgesendete Lieferungen fallen schon durch die erste Regel heraus (Status nicht mehr „versendet“).
 */
final class DuePolicy
{
    public const DUE = 'due';
    public const WAIT = 'wait';
    public const TOO_OLD = 'too_old';
    public const NOT_SHIPPED = 'not_shipped';
    public const CANCELLED = 'cancelled';
    public const REFUNDED = 'refunded';
    public const NO_PRODUCTS = 'no_products';

    /** Gründe, die sich nicht mehr ändern: werden im Versandprotokoll festgehalten, damit die Bestellung nicht jede Stunde neu geprüft wird */
    public const PERMANENT = [self::CANCELLED, self::REFUNDED, self::NO_PRODUCTS];

    /** Zahlungsstatus, bei denen keine Mail geht (letzte Zahlung der Bestellung) */
    private const BAD_TRANSACTION_STATES = ['cancelled', 'refunded', 'failed', 'chargeback'];

    public function __construct(
        public readonly int $daysAfterShipping = 10,
        public readonly int $maxAgeDays = 60,
    ) {
    }

    /** Tage nach Versand, mindestens 0 */
    public function days(): int
    {
        return max(0, $this->daysAfterShipping);
    }

    /** Höchstalter in Tagen, mindestens „Tage nach Versand“ + 2, damit das Zeitfenster nie leer ist */
    public function maxAge(): int
    {
        return max($this->maxAgeDays, $this->days() + 2);
    }

    public function dueAt(\DateTimeImmutable $shippedAt): \DateTimeImmutable
    {
        return $shippedAt->modify('+' . $this->days() . ' days');
    }

    /**
     * @param int $deliveries Lieferungen der Bestellung ohne stornierte
     * @param int $shippedDeliveries davon mit Status „versendet“
     * @param int $productLines Positionen vom Typ „product“
     */
    public function evaluate(
        string $orderState,
        ?string $transactionState,
        int $deliveries,
        int $shippedDeliveries,
        int $productLines,
        ?\DateTimeImmutable $shippedAt,
        \DateTimeImmutable $now,
    ): string {
        if ($orderState === 'cancelled') {
            return self::CANCELLED;
        }
        if ($transactionState !== null && \in_array($transactionState, self::BAD_TRANSACTION_STATES, true)) {
            return self::REFUNDED;
        }
        if ($productLines <= 0) {
            return self::NO_PRODUCTS;
        }
        if ($deliveries <= 0 || $shippedDeliveries < $deliveries || $shippedAt === null) {
            return self::NOT_SHIPPED;
        }
        if ($shippedAt < $now->modify('-' . $this->maxAge() . ' days')) {
            return self::TOO_OLD;
        }
        if ($this->dueAt($shippedAt) > $now) {
            return self::WAIT;
        }

        return self::DUE;
    }

    /**
     * Hat derselbe Empfänger kürzlich schon eine Mail bekommen?
     */
    public static function recentlyMailed(?\DateTimeImmutable $lastSentAt, int $minDays, \DateTimeImmutable $now): bool
    {
        if ($lastSentAt === null || $minDays <= 0) {
            return false;
        }

        return $lastSentAt > $now->modify('-' . $minDays . ' days');
    }
}
