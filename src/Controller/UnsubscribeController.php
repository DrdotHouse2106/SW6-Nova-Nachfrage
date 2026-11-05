<?php declare(strict_types=1);

namespace Nova\Nachfrage\Controller;

use Nova\Nachfrage\Service\OptOutStore;
use Nova\Nachfrage\Service\OptOutToken;
use Nova\Nachfrage\Service\Settings;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Abmeldelink aus der Nachfrage-Mail.
 *
 * GET zeigt nur eine Seite mit Knopf – Mail-Sicherheitsscanner und Link-Vorschauen rufen Links automatisch
 * ab und dürfen dabei niemanden abmelden. Erst POST trägt den Widerspruch ein. Derselbe POST dient als
 * „One-Click“-Abmeldung nach RFC 8058 (Mailprogramme senden List-Unsubscribe=One-Click).
 * Der Link ist mit dem Geheimnis der Installation signiert (OptOutToken) und enthält nur den Hash der Adresse.
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['storefront']])]
class UnsubscribeController extends StorefrontController
{
    private const REQUIREMENTS = ['hash' => '[0-9a-f]{64}', 'salesChannelId' => '[0-9a-f]{32}', 'signature' => '[0-9a-f]{32}'];

    public function __construct(
        private readonly OptOutStore $optOuts,
        private readonly Settings $settings,
        private readonly GenericPageLoaderInterface $pageLoader,
        private readonly string $secret,
    ) {
    }

    #[Route(path: '/nova-nachfrage/abmelden/{hash}/{salesChannelId}/{signature}', name: 'frontend.nova_nachfrage.unsubscribe', requirements: self::REQUIREMENTS, defaults: [PlatformRequest::ATTRIBUTE_NO_STORE => true], methods: ['GET'])]
    public function page(string $hash, string $salesChannelId, string $signature, Request $request, SalesChannelContext $context): Response
    {
        $valid = OptOutToken::verify($hash, $salesChannelId, $signature, $this->secret);

        return $this->renderPage($request, $context, $valid ? 'confirm' : 'invalid', $valid ? $this->generateUrl('frontend.nova_nachfrage.unsubscribe.save', ['hash' => $hash, 'salesChannelId' => $salesChannelId, 'signature' => $signature]) : null, $salesChannelId);
    }

    #[Route(path: '/nova-nachfrage/abmelden/{hash}/{salesChannelId}/{signature}', name: 'frontend.nova_nachfrage.unsubscribe.save', requirements: self::REQUIREMENTS, defaults: [PlatformRequest::ATTRIBUTE_NO_STORE => true], methods: ['POST'])]
    public function save(string $hash, string $salesChannelId, string $signature, Request $request, SalesChannelContext $context): Response
    {
        $oneClick = $request->request->get('List-Unsubscribe') === 'One-Click';
        if (!OptOutToken::verify($hash, $salesChannelId, $signature, $this->secret)) {
            return $oneClick ? new Response('invalid', Response::HTTP_BAD_REQUEST) : $this->renderPage($request, $context, 'invalid', null, $salesChannelId);
        }
        // Gilt die Abmeldung laut Einstellung des Kanals, aus dem die Mail kam, für alle Kanäle?
        $scope = $this->settings->optOutAllChannels($salesChannelId) ? null : $salesChannelId;
        $this->optOuts->addHash($hash, $oneClick ? OptOutStore::SOURCE_ONE_CLICK : OptOutStore::SOURCE_LINK, $scope);

        if ($oneClick) {
            return new Response('ok', Response::HTTP_OK, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        return $this->renderPage($request, $context, 'done', null, $salesChannelId);
    }

    private function renderPage(Request $request, SalesChannelContext $context, string $state, ?string $action, string $salesChannelId): Response
    {
        $response = $this->renderStorefront('@NovaNachfrage/storefront/nova-nachfrage/unsubscribe.html.twig', [
            'page' => $this->pageLoader->load($request, $context),
            'novaNachfrage' => [
                'state' => $state,
                'action' => $action,
                'allChannels' => $this->settings->optOutAllChannels($salesChannelId),
            ],
        ]);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
