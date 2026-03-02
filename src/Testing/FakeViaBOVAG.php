<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Testing;

use Generator;
use NiekNijland\ViaBOVAG\Data\Brand;
use NiekNijland\ViaBOVAG\Data\FacetName;
use NiekNijland\ViaBOVAG\Data\FilterOption;
use NiekNijland\ViaBOVAG\Data\Listing;
use NiekNijland\ViaBOVAG\Data\ListingDetail;
use NiekNijland\ViaBOVAG\Data\MobilityType;
use NiekNijland\ViaBOVAG\Data\Model;
use NiekNijland\ViaBOVAG\Data\SearchQuery;
use NiekNijland\ViaBOVAG\Data\SearchResult;
use NiekNijland\ViaBOVAG\ViaBOVAGInterface;
use PHPUnit\Framework\Assert;
use Throwable;

class FakeViaBOVAG implements ViaBOVAGInterface
{
    private ?SearchResult $searchResult = null;

    /** @var SearchResult[] */
    private array $searchResults = [];

    /** @var Brand[] */
    private array $brands = [];

    /** @var Model[] */
    private array $models = [];

    /** @var FilterOption[] */
    private array $facetOptions = [];

    private ?ListingDetail $listingDetail = null;

    private ?Throwable $exception = null;

    /** @var RecordedCall[] */
    private array $calls = [];

    private bool $sessionReset = false;

    public function withSearchResult(SearchResult $result): self
    {
        $this->searchResult = $result;

        return $this;
    }

    /**
     * Configure multiple search results for multi-page searchAll() simulation.
     * Each SearchResult represents one page of results, yielded in order.
     */
    public function withSearchResults(SearchResult ...$results): self
    {
        $this->searchResults = $results;

        return $this;
    }

    public function withListingDetail(ListingDetail $detail): self
    {
        $this->listingDetail = $detail;

        return $this;
    }

    public function withBrands(Brand ...$brands): self
    {
        $this->brands = $brands;

        return $this;
    }

    public function withModels(Model ...$models): self
    {
        $this->models = $models;

        return $this;
    }

    public function withFacetOptions(FilterOption ...$facetOptions): self
    {
        $this->facetOptions = $facetOptions;

        return $this;
    }

    public function shouldThrow(Throwable $exception): self
    {
        $this->exception = $exception;

        return $this;
    }

    public function search(SearchQuery $query): SearchResult
    {
        $this->calls[] = new RecordedCall('search', [$query]);
        $this->throwIfConfigured();

        return $this->searchResult ?? SearchResultFactory::make();
    }

    public function searchAll(SearchQuery $query): Generator
    {
        $this->calls[] = new RecordedCall('searchAll', [$query]);
        $this->throwIfConfigured();

        $results = $this->searchResults !== []
            ? $this->searchResults
            : [$this->searchResult ?? SearchResultFactory::make()];

        foreach ($results as $result) {
            foreach ($result->listings as $listing) {
                yield $listing;
            }
        }
    }

    public function getDetail(Listing $listing): ListingDetail
    {
        $this->calls[] = new RecordedCall('getDetail', [$listing]);
        $this->throwIfConfigured();

        return $this->listingDetail ?? ListingDetailFactory::make();
    }

    /**
     * @return Brand[]
     */
    public function getBrands(MobilityType $mobilityType): array
    {
        $this->calls[] = new RecordedCall('getBrands', [$mobilityType]);
        $this->throwIfConfigured();

        return $this->brands;
    }

    /**
     * @return FilterOption[]
     */
    public function getFacetOptions(
        MobilityType $mobilityType,
        FacetName $facetName,
        ?Brand $brand = null,
        ?Model $model = null,
    ): array {
        $this->calls[] = new RecordedCall('getFacetOptions', [$mobilityType, $facetName, $brand, $model]);
        $this->throwIfConfigured();

        return $this->facetOptions;
    }

    /**
     * @return Model[]
     */
    public function getModels(MobilityType $mobilityType, ?Brand $brand = null): array
    {
        $this->calls[] = new RecordedCall('getModels', [$mobilityType, $brand]);
        $this->throwIfConfigured();

        return $this->models;
    }

    public function getDetailBySlug(string $slug, MobilityType $mobilityType): ListingDetail
    {
        $this->calls[] = new RecordedCall('getDetailBySlug', [$slug, $mobilityType]);
        $this->throwIfConfigured();

        return $this->listingDetail ?? ListingDetailFactory::make();
    }

    public function resetSession(): void
    {
        $this->calls[] = new RecordedCall('resetSession', []);
        $this->sessionReset = true;
    }

    /**
     * @return RecordedCall[]
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    /**
     * @return RecordedCall[]
     */
    public function getCallsTo(string $method): array
    {
        return array_values(array_filter(
            $this->calls,
            fn (RecordedCall $call): bool => $call->method === $method,
        ));
    }

    public function assertCalled(string $method, ?int $times = null): void
    {
        $calls = $this->getCallsTo($method);

        if ($times !== null) {
            Assert::assertCount($times, $calls, sprintf('Expected %s to be called %d times, got ', $method, $times).count($calls));
        } else {
            Assert::assertNotEmpty($calls, sprintf('Expected %s to be called at least once.', $method));
        }
    }

    public function assertNotCalled(string $method): void
    {
        Assert::assertEmpty(
            $this->getCallsTo($method),
            sprintf('Expected %s not to be called.', $method),
        );
    }

    public function assertSessionReset(): void
    {
        Assert::assertTrue($this->sessionReset, 'Expected resetSession() to be called.');
    }

    public function assertSessionNotReset(): void
    {
        Assert::assertFalse($this->sessionReset, 'Expected resetSession() not to be called.');
    }

    /**
     * @throws Throwable
     */
    private function throwIfConfigured(): void
    {
        if ($this->exception instanceof Throwable) {
            $e = $this->exception;
            $this->exception = null;

            throw $e;
        }
    }
}
