<?php declare(strict_types=1);

namespace Nova\Nachfrage\Subscriber;

use Nova\Nachfrage\Service\OptOutStore;
use Nova\Nachfrage\Service\Settings;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Kästchen „Keine E-Mail nach dem Versand“ in der Kasse: angehakt → Widerspruch sofort speichern (nur als Hash).
 */
class CheckoutOptOutSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Settings $settings,
        private readonly OptOutStore $optOuts,
        private readonly RequestStack $requests,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [CheckoutOrderPlacedEvent::class => 'onOrderPlaced'];
    }

    public function onOrderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        $scId = $event->getSalesChannelId();
        $request = $this->requests->getCurrentRequest();
        if ($request === null || !$request->request->getBoolean('novaNachfrageOptOut') || !$this->settings->checkoutOptOut($scId)) {
            return;
        }
        $email = $event->getOrder()->getOrderCustomer()?->getEmail();
        if ($email === null || $email === '') {
            return;
        }
        $this->optOuts->addEmail($email, OptOutStore::SOURCE_CHECKOUT, $this->settings->optOutAllChannels($scId) ? null : $scId);
    }
}
