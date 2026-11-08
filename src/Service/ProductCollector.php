<?php declare(strict_types=1);

namespace Nova\Nachfrage\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\AbstractProductCrossSellingRoute;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Gekaufte Artikel (mit Link zur Produktseite und zum Bewertungsformular) und ähnliche Produkte für die Mail.
 *
 * Ähnliche Produkte: Cross-Sellings der gekauften Artikel und Bestseller, aber nur mit gemeinsamer spezifischer
 * Kategorie (siehe RecommendationPicker), nach Gewicht sortiert. Nur Artikel, die im
 * Verkaufskanal aktiv und sichtbar (Kategorie-Vorschläge: auch in Listen) und lieferbar sind; keine Artikel
 * und keine Varianten von Artikeln, die in der Bestellung waren. Preise ohne kundenspezifische Regeln.
 */
class ProductCollector
{
    /**
     * @param SalesChannelRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly AbstractSalesChannelContextFactory $contextFactory,
        private readonly SalesChannelRepository $productRepository,
        private readonly AbstractProductCrossSellingRoute $crossSellingRoute,
        private readonly UrlBuilder $urls,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function context(OrderEntity $order): SalesChannelContext
    {
        return $this->contextFactory->create(Uuid::randomHex(), $order->getSalesChannelId(), [
            SalesChannelContextService::LANGUAGE_ID => $order->getLanguageId(),
            SalesChannelContextService::CURRENCY_ID => $order->getCurrencyId(),
        ]);
    }

    /**
     * @return list<array{id: string|null, name: string, productNumber: string, quantity: int, url: string|null, reviewUrl: string|null, imageUrl: string|null}>
     */
    public function purchased(OrderEntity $order, SalesChannelContext $context, bool $reviewsEnabled): array
    {
        $lines = $this->productLines($order);
        $ids = array_values(array_unique(array_filter(array_map(static fn (OrderLineItemEntity $l): ?string => $l->getProductId(), $lines))));
        $products = $this->load($ids, $context, ProductVisibilityDefinition::VISIBILITY_LINK, false);
        $urls = $this->urls->productUrls(array_keys($products), $order->getSalesChannelId(), $order->getLanguageId());

        $out = [];
        $seen = [];
        foreach ($lines as $line) {
            $id = $line->getProductId();
            if ($id !== null && isset($seen[$id])) {
                continue;
            }
            if ($id !== null) {
                $seen[$id] = true;
            }
            $product = $id !== null ? ($products[$id] ?? null) : null;
            $url = $product !== null ? ($urls[$product->getId()] ?? null) : null;
            $payload = $line->getPayload() ?? [];
            $out[] = [
                'id' => $id,
                'name' => $product !== null ? self::name($product) : $line->getLabel(),
                'productNumber' => \is_string($payload['productNumber'] ?? null) ? $payload['productNumber'] : '',
                'quantity' => $line->getQuantity(),
                'url' => $url,
                'reviewUrl' => $url !== null && $reviewsEnabled ? ReviewLink::reviewUrl($url) : null,
                'imageUrl' => self::imageUrl($product?->getCover()?->getMedia()) ?? self::imageUrl($line->getCover()),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{id: string, name: string, productNumber: string, url: string, imageUrl: string|null, price: float|null, referencePrice: array{price: float, unit: string}|null}>
     */
    /**
     * @param list<string> $ignoredCategories
     * @param list<string> $vehicleGroups Eigenschaftsgruppen, deren Ausprägungen wie Kategorien zählen (z. B. „Geeignet für“)
     */
    public function recommendations(OrderEntity $order, SalesChannelContext $context, int $max, array $ignoredCategories = [], array $vehicleGroups = []): array
    {
        if ($max <= 0) {
            return [];
        }
        $lines = $this->productLines($order);
        $purchasedIds = array_values(array_unique(array_filter(array_map(static fn (OrderLineItemEntity $l): ?string => $l->getProductId(), $lines))));
        if ($purchasedIds === []) {
            return [];
        }
        // Gekaufte Artikel laden: Gruppe (Hauptartikel) und gewichtete spezifische Kategorien bestimmen
        $purchased = $this->load($purchasedIds, $context, ProductVisibilityDefinition::VISIBILITY_LINK, false, true);
        $excludedGroups = $purchasedIds;
        $perProduct = [];
        $root = $context->getSalesChannel()->getNavigationCategoryId();
        foreach ($purchased as $product) {
            $excludedGroups[] = $product->getParentId() ?? $product->getId();
            $paths = [];
            foreach ($product->getCategories() ?? [] as $category) {
                $paths[$category->getId()] = (string) $category->getPath();
            }
            $categories = RecommendationPicker::specificCategories($paths, $root, $ignoredCategories);
            // Fahrzeug-Eigenschaften (z. B. „Geeignet für: Citroën CX“) zählen wie spezifische Kategorien
            foreach ($product->getProperties() ?? [] as $option) {
                if (\in_array($option->getGroupId(), $vehicleGroups, true)) {
                    $categories[] = $option->getId();
                }
            }
            if ($categories !== []) {
                $perProduct[] = $categories;
            }
        }
        $weights = RecommendationPicker::weights($perProduct);
        $specific = array_keys($weights);

        $candidates = [];
        foreach ($purchasedIds as $productId) {
            try {
                $elements = $this->crossSellingRoute->load($productId, new Request(), $context, new Criteria())->getResult();
                foreach ($elements as $element) {
                    foreach ($element->getProducts() as $product) {
                        $candidates[] = $product->getId();
                    }
                }
            } catch (\Throwable $e) {
                $this->logger->info('Nova Nachfrage: Cross-Selling nicht ladbar', ['productId' => $productId, 'error' => $e->getMessage()]);
            }
        }

        if ($specific !== []) {
            $criteria = new Criteria();
            $criteria->addFilter(
                new ProductAvailableFilter($context->getSalesChannelId(), ProductVisibilityDefinition::VISIBILITY_ALL),
                new EqualsFilter('available', true),
                new OrFilter([new EqualsAnyFilter('categories.id', $specific), new EqualsAnyFilter('properties.id', $specific)]),
                new NotFilter(NotFilter::CONNECTION_OR, [new EqualsAnyFilter('id', $purchasedIds)]),
            );
            $criteria->addSorting(new FieldSorting('sales', FieldSorting::DESCENDING));
            $criteria->setLimit($max * 10);
            try {
                foreach ($this->productRepository->searchIds($criteria, $context)->getIds() as $id) {
                    $candidates[] = $id;
                }
            } catch (\Throwable $e) {
                $this->logger->info('Nova Nachfrage: Kategorie-Vorschläge nicht ladbar', ['error' => $e->getMessage()]);
            }
        }

        $candidates = array_values(array_unique($candidates));
        $products = $this->load(\array_slice($candidates, 0, 80), $context, ProductVisibilityDefinition::VISIBILITY_LINK, true);
        $categoriesOf = [];
        foreach ($products as $id => $product) {
            $categoriesOf[$id] = array_merge($product->getCategoryIds() ?? [], $product->getPropertyIds() ?? []);
        }
        $candidates = RecommendationPicker::rank($candidates, $categoriesOf, $weights);

        $candidates = array_values(array_unique($candidates));
        if ($candidates === []) {
            return [];
        }
        $groupOf = [];
        foreach ($products as $id => $product) {
            $groupOf[$id] = $product->getParentId() ?? $id;
        }
        $picked = RecommendationPicker::pick($candidates, $groupOf, array_values(array_unique($excludedGroups)), $max);
        $urls = $this->urls->productUrls($picked, $order->getSalesChannelId(), $order->getLanguageId());

        $out = [];
        foreach ($picked as $id) {
            $product = $products[$id];
            $out[] = [
                'id' => $id,
                'name' => self::name($product),
                'productNumber' => $product->getProductNumber(),
                'url' => $urls[$id],
                'imageUrl' => self::imageUrl($product->getCover()?->getMedia()),
                'price' => self::price($product),
                'referencePrice' => self::referencePrice($product),
            ];
        }

        return $out;
    }

    /**
     * @return list<OrderLineItemEntity>
     */
    private function productLines(OrderEntity $order): array
    {
        $lines = [];
        foreach ($order->getLineItems() ?? [] as $line) {
            if ($line->getType() === 'product') {
                $lines[] = $line;
            }
        }
        usort($lines, static fn (OrderLineItemEntity $a, OrderLineItemEntity $b): int => $a->getPosition() <=> $b->getPosition());

        return $lines;
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, SalesChannelProductEntity>
     */
    private function load(array $ids, SalesChannelContext $context, int $visibility, bool $onlyAvailable, bool $withCategories = false): array
    {
        if ($ids === []) {
            return [];
        }
        $criteria = new Criteria($ids);
        $criteria->addAssociation('cover.media');
        if ($withCategories) {
            $criteria->addAssociation('categories');
            $criteria->addAssociation('properties');
        }
        $criteria->addFilter(new ProductAvailableFilter($context->getSalesChannelId(), $visibility));
        if ($onlyAvailable) {
            $criteria->addFilter(new EqualsFilter('available', true));
        }
        try {
            $result = $this->productRepository->search($criteria, $context)->getEntities();
        } catch (\Throwable $e) {
            $this->logger->warning('Nova Nachfrage: Artikel nicht ladbar', ['error' => $e->getMessage()]);

            return [];
        }
        $out = [];
        foreach ($result as $product) {
            if ($product instanceof SalesChannelProductEntity) {
                $out[$product->getId()] = $product;
            }
        }

        return $out;
    }

    private static function name(SalesChannelProductEntity $product): string
    {
        $name = $product->getTranslation('name');

        return \is_string($name) && $name !== '' ? $name : (string) $product->getName();
    }

    private static function price(SalesChannelProductEntity $product): ?float
    {
        try {
            return $product->getCalculatedPrice()->getUnitPrice();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Grundpreis (PAngV), z. B. ['price' => 12.9, 'unit' => '1 l'], sonst null */
    private static function referencePrice(SalesChannelProductEntity $product): ?array
    {
        try {
            $reference = $product->getCalculatedPrice()->getReferencePrice();
        } catch (\Throwable) {
            return null;
        }
        if ($reference === null) {
            return null;
        }

        return ['price' => $reference->getPrice(), 'unit' => rtrim(rtrim(number_format($reference->getReferenceUnit(), 3, ',', ''), '0'), ',') . ' ' . $reference->getUnitName()];
    }

    /** Kleinstes Vorschaubild ab 300 px Breite, sonst das Original */
    private static function imageUrl(?MediaEntity $media): ?string
    {
        if ($media === null) {
            return null;
        }
        $best = null;
        foreach ($media->getThumbnails() ?? [] as $thumbnail) {
            if ($thumbnail->getWidth() >= 300 && ($best === null || $thumbnail->getWidth() < $best->getWidth())) {
                $best = $thumbnail;
            }
        }
        $url = $best !== null ? $best->getUrl() : $media->getUrl();

        return $url !== '' ? $url : null;
    }
}
