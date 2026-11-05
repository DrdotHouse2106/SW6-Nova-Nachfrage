<?php declare(strict_types=1);

namespace Nova\Nachfrage\Service;

/**
 * Normalisierung und Hash von E-Mail-Adressen (reine Logik, ohne Shopware).
 *
 * In den Tabellen des Plugins steht nie eine E-Mail-Adresse im Klartext, nur dieser Hash. Bewusst ein
 * einfacher SHA-256 mit festem Präfix und kein HMAC mit dem APP_SECRET: Eine Abmeldung muss dauerhaft
 * gelten, auch wenn das Geheimnis der Installation später geändert wird (Umzug, Neuinstallation).
 * Der Hash ist pseudonym, nicht anonym – wer eine Adresse kennt, kann prüfen, ob sie in der Liste steht.
 */
final class EmailHash
{
    private const PREFIX = 'nova-nachfrage|';

    /**
     * Kleinschreibung und Leerzeichen entfernen. Teiladressen wie name+shop@ bleiben eigene Adressen
     * (sie können zu verschiedenen Postfächern gehören).
     */
    public static function normalize(string $email): string
    {
        $email = trim($email);
        // Steuerzeichen und Leerzeichen (auch geschützte) entfernen, die beim Kopieren entstehen
        $email = (string) preg_replace('/[\s\x{00A0}\x{200B}\x{FEFF}]+/u', '', $email);

        return mb_strtolower($email);
    }

    public static function hash(string $email): string
    {
        return hash('sha256', self::PREFIX . self::normalize($email));
    }

    public static function isHash(string $value): bool
    {
        return preg_match('/^[0-9a-f]{64}$/', $value) === 1;
    }

    public static function isValidEmail(string $email): bool
    {
        $email = self::normalize($email);

        return $email !== '' && mb_strlen($email) <= 254 && filter_var($email, \FILTER_VALIDATE_EMAIL) !== false;
    }

    /** Kurzform für Anzeigen (Administration, Konsole): erste 12 Zeichen */
    public static function short(string $hash): string
    {
        return substr($hash, 0, 12);
    }
}
