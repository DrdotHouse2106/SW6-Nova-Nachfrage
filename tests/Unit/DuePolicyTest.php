<?php declare(strict_types=1);

namespace Nova\Nachfrage\Tests\Unit;

use Nova\Nachfrage\Service\DuePolicy;
use PHPUnit\Framework\TestCase;

final class DuePolicyTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-05 12:00:00');
    }

    private function eval(DuePolicy $policy, string $shippedAt, string $orderState = 'open', ?string $transaction = 'paid', int $deliveries = 1, int $shipped = 1, int $products = 1): string
    {
        return $policy->evaluate($orderState, $transaction, $deliveries, $shipped, $products, new \DateTimeImmutable($shippedAt), $this->now);
    }

    public function testDueAfterConfiguredDays(): void
    {
        $policy = new DuePolicy(10, 60);
        self::assertSame(DuePolicy::WAIT, $this->eval($policy, '2026-10-26 12:00:01'));
        self::assertSame(DuePolicy::DUE, $this->eval($policy, '2026-10-26 12:00:00'));
        self::assertSame(DuePolicy::DUE, $this->eval($policy, '2026-10-01 08:00:00'));
        self::assertSame('2026-11-05 12:00', $policy->dueAt(new \DateTimeImmutable('2026-10-26 12:00'))->format('Y-m-d H:i'));
    }

    public function testNeverCatchUpOldOrders(): void
    {
        $policy = new DuePolicy(10, 60);
        self::assertSame(DuePolicy::DUE, $this->eval($policy, '2026-09-06 12:00:00'));
        self::assertSame(DuePolicy::TOO_OLD, $this->eval($policy, '2026-09-06 11:59:59'));
    }

    public function testMaxAgeIsAtLeastDaysPlusTwo(): void
    {
        $policy = new DuePolicy(30, 5);
        self::assertSame(32, $policy->maxAge());
        self::assertSame(DuePolicy::DUE, $this->eval($policy, '2026-10-05 12:00:00'));
        self::assertSame(0, (new DuePolicy(-3, 60))->days());
    }

    public function testRequiresAllDeliveriesShipped(): void
    {
        $policy = new DuePolicy(10, 60);
        self::assertSame(DuePolicy::NOT_SHIPPED, $this->eval($policy, '2026-10-01', deliveries: 2, shipped: 1));
        self::assertSame(DuePolicy::NOT_SHIPPED, $this->eval($policy, '2026-10-01', deliveries: 0, shipped: 0));
        self::assertSame(DuePolicy::NOT_SHIPPED, $policy->evaluate('open', 'paid', 1, 1, 1, null, $this->now));
    }

    public function testSkipsCancelledRefundedAndEmptyOrders(): void
    {
        $policy = new DuePolicy(10, 60);
        self::assertSame(DuePolicy::CANCELLED, $this->eval($policy, '2026-10-01', orderState: 'cancelled'));
        self::assertSame(DuePolicy::REFUNDED, $this->eval($policy, '2026-10-01', transaction: 'refunded'));
        self::assertSame(DuePolicy::REFUNDED, $this->eval($policy, '2026-10-01', transaction: 'cancelled'));
        self::assertSame(DuePolicy::NO_PRODUCTS, $this->eval($policy, '2026-10-01', products: 0));
        // teilweise erstattet oder ohne Zahlung: trotzdem fällig
        self::assertSame(DuePolicy::DUE, $this->eval($policy, '2026-10-01', transaction: 'refunded_partially'));
        self::assertSame(DuePolicy::DUE, $this->eval($policy, '2026-10-01', transaction: null));
        self::assertContains(DuePolicy::CANCELLED, DuePolicy::PERMANENT);
        self::assertNotContains(DuePolicy::WAIT, DuePolicy::PERMANENT);
    }

    public function testRecentlyMailed(): void
    {
        self::assertFalse(DuePolicy::recentlyMailed(null, 30, $this->now));
        self::assertTrue(DuePolicy::recentlyMailed(new \DateTimeImmutable('2026-10-10'), 30, $this->now));
        self::assertFalse(DuePolicy::recentlyMailed(new \DateTimeImmutable('2026-10-05 11:00'), 30, $this->now));
        self::assertFalse(DuePolicy::recentlyMailed(new \DateTimeImmutable('2026-11-04'), 0, $this->now));
    }
}
