<?php declare(strict_types=1);

namespace Nova\Nachfrage\Tests\Unit;

use Nova\Nachfrage\Service\EmailHash;
use PHPUnit\Framework\TestCase;

final class EmailHashTest extends TestCase
{
    public function testNormalizeTrimsAndLowercases(): void
    {
        self::assertSame('max.muster@example.com', EmailHash::normalize("  Max.Muster@Example.COM \n"));
        self::assertSame('a@example.com', EmailHash::normalize("a\u{00A0}@example.com\u{200B}"));
        self::assertSame('ä@exämple.de', EmailHash::normalize('Ä@EXÄMPLE.DE'));
    }

    public function testPlusAddressesStayDistinct(): void
    {
        self::assertNotSame(EmailHash::hash('name+shop@example.com'), EmailHash::hash('name@example.com'));
    }

    public function testHashIsStableAndCaseInsensitive(): void
    {
        $hash = EmailHash::hash('Kunde@Example.com');
        self::assertSame($hash, EmailHash::hash(' kunde@example.com'));
        self::assertTrue(EmailHash::isHash($hash));
        self::assertSame(64, \strlen($hash));
        // fester Präfix: nicht der nackte SHA-256 der Adresse
        self::assertNotSame(hash('sha256', 'kunde@example.com'), $hash);
        self::assertSame(substr($hash, 0, 12), EmailHash::short($hash));
    }

    public function testValidEmail(): void
    {
        self::assertTrue(EmailHash::isValidEmail(' Kunde@Example.com '));
        self::assertFalse(EmailHash::isValidEmail(''));
        self::assertFalse(EmailHash::isValidEmail('kein-at-zeichen'));
        self::assertFalse(EmailHash::isValidEmail(str_repeat('a', 250) . '@example.com'));
    }

    public function testIsHash(): void
    {
        self::assertFalse(EmailHash::isHash('abc'));
        self::assertFalse(EmailHash::isHash(str_repeat('G', 64)));
        self::assertFalse(EmailHash::isHash(str_repeat('A', 64)));
    }
}
