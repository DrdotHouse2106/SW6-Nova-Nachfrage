<?php declare(strict_types=1);

namespace Nova\Nachfrage\Service;

/**
 * Signatur des Abmeldelinks (reine Logik).
 *
 * Link: /nova-nachfrage/abmelden/{E-Mail-Hash}/{Verkaufskanal-ID}/{Signatur}. Die Signatur ist ein HMAC
 * (SHA-256, gekürzt auf 32 Hex-Zeichen) über Hash und Kanal mit dem Geheimnis der Installation (APP_SECRET).
 * Es muss nichts gespeichert werden, der Link gilt dauerhaft, und niemand kann ohne den Link eine fremde
 * Adresse abmelden. Im Link steht nur der Hash, nicht die E-Mail-Adresse.
 */
final class OptOutToken
{
    private const PURPOSE = 'nova-nachfrage-optout';

    public static function sign(string $emailHash, string $salesChannelId, string $secret): string
    {
        return substr(hash_hmac('sha256', self::PURPOSE . '|' . strtolower($emailHash) . '|' . strtolower($salesChannelId), $secret), 0, 32);
    }

    public static function verify(string $emailHash, string $salesChannelId, string $signature, string $secret): bool
    {
        if ($secret === '' || !EmailHash::isHash($emailHash) || preg_match('/^[0-9a-f]{32}$/', $salesChannelId) !== 1) {
            return false;
        }

        return hash_equals(self::sign($emailHash, $salesChannelId, $secret), strtolower($signature));
    }

    public static function path(string $emailHash, string $salesChannelId, string $secret): string
    {
        return '/nova-nachfrage/abmelden/' . $emailHash . '/' . strtolower($salesChannelId) . '/' . self::sign($emailHash, $salesChannelId, $secret);
    }
}
