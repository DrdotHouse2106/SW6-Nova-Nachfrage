<?php declare(strict_types=1);

namespace Nova\Nachfrage\Tests\Unit;

use Nova\Nachfrage\Service\EmailHash;
use Nova\Nachfrage\Service\OptOutToken;
use PHPUnit\Framework\TestCase;

final class OptOutTokenTest extends TestCase
{
    private const SECRET = 'test-secret';
    private const CHANNEL = '0123456789abcdef0123456789abcdef';

    public function testSignAndVerify(): void
    {
        $hash = EmailHash::hash('kunde@example.com');
        $signature = OptOutToken::sign($hash, self::CHANNEL, self::SECRET);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $signature);
        self::assertTrue(OptOutToken::verify($hash, self::CHANNEL, $signature, self::SECRET));
        self::assertTrue(OptOutToken::verify($hash, self::CHANNEL, strtoupper($signature), self::SECRET));
    }

    public function testRejectsTampering(): void
    {
        $hash = EmailHash::hash('kunde@example.com');
        $other = EmailHash::hash('andere@example.com');
        $signature = OptOutToken::sign($hash, self::CHANNEL, self::SECRET);

        self::assertFalse(OptOutToken::verify($other, self::CHANNEL, $signature, self::SECRET), 'andere Adresse');
        self::assertFalse(OptOutToken::verify($hash, 'fedcba9876543210fedcba9876543210', $signature, self::SECRET), 'anderer Kanal');
        self::assertFalse(OptOutToken::verify($hash, self::CHANNEL, $signature, 'anderes-geheimnis'), 'anderes Geheimnis');
        self::assertFalse(OptOutToken::verify($hash, self::CHANNEL, $signature, ''), 'leeres Geheimnis');
        self::assertFalse(OptOutToken::verify('kein-hash', self::CHANNEL, $signature, self::SECRET));
        self::assertFalse(OptOutToken::verify($hash, 'kein-kanal', $signature, self::SECRET));
        self::assertFalse(OptOutToken::verify($hash, self::CHANNEL, '', self::SECRET));
    }

    public function testPath(): void
    {
        $hash = EmailHash::hash('kunde@example.com');
        $path = OptOutToken::path($hash, strtoupper(self::CHANNEL), self::SECRET);
        self::assertSame('/nova-nachfrage/abmelden/' . $hash . '/' . self::CHANNEL . '/' . OptOutToken::sign($hash, self::CHANNEL, self::SECRET), $path);
        self::assertStringNotContainsString('example.com', $path);
    }
}
