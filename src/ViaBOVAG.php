<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG;

use Generator;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use JsonException;
use NiekNijland\ViaBOVAG\Data\BicycleSearchCriteria;
use NiekNijland\ViaBOVAG\Data\Brand;
use NiekNijland\ViaBOVAG\Data\CamperSearchCriteria;
use NiekNijland\ViaBOVAG\Data\CarSearchCriteria;
use NiekNijland\ViaBOVAG\Data\FacetName;
use NiekNijland\ViaBOVAG\Data\FilterOption;
use NiekNijland\ViaBOVAG\Data\Filters\SharedSearchFilters;
use NiekNijland\ViaBOVAG\Data\Listing;
use NiekNijland\ViaBOVAG\Data\ListingDetail;
use NiekNijland\ViaBOVAG\Data\MobilityType;
use NiekNijland\ViaBOVAG\Data\Model;
use NiekNijland\ViaBOVAG\Data\MotorcycleSearchCriteria;
use NiekNijland\ViaBOVAG\Data\SearchFacet;
use NiekNijland\ViaBOVAG\Data\SearchFacetOption;
use NiekNijland\ViaBOVAG\Data\SearchQuery;
use NiekNijland\ViaBOVAG\Data\SearchResult;
use NiekNijland\ViaBOVAG\Exception\NotFoundException;
use NiekNijland\ViaBOVAG\Exception\ViaBOVAGException;
use NiekNijland\ViaBOVAG\Parser\JsonParser;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\SimpleCache\CacheInterface;

class ViaBOVAG implements ViaBOVAGInterface
{
    private const string BASE_URL = 'https://www.viabovag.nl';

    private const string SEARCH_ENDPOINT = '/api/client/search/results';

    private const string FACETS_ENDPOINT = '/api/client/search/facets';

    private const string CACHE_KEY = 'viabovag:build-id';

    private const string ALL_BRANDS_CATEGORY_LABEL = 'Alle merken';

    private ?string $buildId = null;

    private readonly JsonParser $parser;

    public function __construct(
        private readonly ClientInterface $httpClient = new Client,
        private readonly ?CacheInterface $cache = null,
        private readonly int $cacheTtl = 3600,
    ) {
        $this->parser = new JsonParser;
    }

    public function search(SearchQuery $query): SearchResult
    {
        $page = $query->page();

        if ($page < 1) {
            throw new ViaBOVAGException('Search page must be greater than or equal to 1.');
        }

        $json = $this->postJson(
            self::BASE_URL.self::SEARCH_ENDPOINT,
            $query->toRequestBody(),
        );

        return $this->parser->parseSearchResults($json, $page);
    }

    public function searchAll(SearchQuery $query): Generator
    {
        $result = $this->search($query);

        foreach ($result->listings as $listing) {
            yield $listing;
        }

        while ($result->hasNextPage()) {
            $query = $query->withPage($result->currentPage + 1);
            $result = $this->search($query);

            foreach ($result->listings as $listing) {
                yield $listing;
            }
        }
    }

    /**
     * @return Brand[]
     */
    public function getBrands(MobilityType $mobilityType): array
    {
        $facets = $this->fetchFacets($this->createEmptyCriteria($mobilityType));

        return $this->extractBrandsFromFacets($facets);
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
        $facets = $this->fetchFacets($this->createEmptyCriteria($mobilityType, $brand, $model));

        return $this->extractFilterOptionsFromFacets($facets, $facetName->value);
    }

    /**
     * @return Model[]
     */
    public function getModels(MobilityType $mobilityType, ?Brand $brand = null): array
    {
        $facets = $this->fetchFacets($this->createEmptyCriteria($mobilityType, $brand));

        return $this->extractModelsFromFacets($facets);
    }

    public function getDetail(Listing $listing): ListingDetail
    {
        return $this->getDetailBySlug($listing->friendlyUriPart, $listing->mobilityType);
    }

    public function getDetailBySlug(string $slug, MobilityType $mobilityType): ListingDetail
    {
        $url = $this->buildDetailUrl($slug, $mobilityType);
        $json = $this->fetchJsonWithBuildIdRefresh($url);

        return $this->parser->parseDetail($json);
    }

    public function getDetailByUrl(string $url): ListingDetail
    {
        [$slug, $mobilityType] = $this->extractSlugAndMobilityTypeFromDetailUrl($url);

        return $this->getDetailBySlug($slug, $mobilityType);
    }

    public function resetSession(): void
    {
        $this->invalidateBuildId();
    }

    private function createEmptyCriteria(
        MobilityType $mobilityType,
        ?Brand $brand = null,
        ?Model $model = null,
    ): SearchQuery {
        $shared = new SharedSearchFilters(
            brand: $brand,
            model: $model,
        );

        return match ($mobilityType) {
            MobilityType::Motorcycle => MotorcycleSearchCriteria::fromFilters(shared: $shared),
            MobilityType::Car => CarSearchCriteria::fromFilters(shared: $shared),
            MobilityType::Bicycle => BicycleSearchCriteria::fromFilters(shared: $shared),
            MobilityType::Camper => CamperSearchCriteria::fromFilters(shared: $shared),
        };
    }

    /**
     * @return SearchFacet[]
     */
    private function fetchFacets(SearchQuery $query): array
    {
        $json = $this->postJson(
            self::BASE_URL.self::FACETS_ENDPOINT,
            $query->toRequestBody(),
        );

        return $this->parser->parseSearchFacets($json);
    }

    /**
     * @param  SearchFacet[]  $facets
     * @return FilterOption[]
     */
    private function extractFilterOptionsFromFacets(
        array $facets,
        string $facetName,
        ?string $preferredCategoryLabel = null,
    ): array {
        $options = $this->extractFacetOptions(
            facets: $facets,
            facetName: $facetName,
            preferredCategoryLabel: $preferredCategoryLabel,
        );

        $filterOptionsBySlug = [];

        foreach ($options as $option) {
            if ($option->name === '' || array_key_exists($option->name, $filterOptionsBySlug)) {
                continue;
            }

            $filterOptionsBySlug[$option->name] = new FilterOption(
                slug: $option->name,
                label: $option->label !== '' ? $option->label : $option->name,
                count: $option->count,
            );
        }

        return array_values($filterOptionsBySlug);
    }

    /**
     * @param  SearchFacet[]  $facets
     * @return Brand[]
     */
    private function extractBrandsFromFacets(array $facets): array
    {
        $options = $this->extractFilterOptionsFromFacets(
            facets: $facets,
            facetName: FacetName::Brand->value,
            preferredCategoryLabel: self::ALL_BRANDS_CATEGORY_LABEL,
        );

        return array_map(
            fn (FilterOption $option): Brand => new Brand(
                slug: $option->slug,
                label: $option->label,
                count: $option->count,
            ),
            $options,
        );
    }

    /**
     * @param  SearchFacet[]  $facets
     * @return Model[]
     */
    private function extractModelsFromFacets(array $facets): array
    {
        $options = $this->extractFilterOptionsFromFacets($facets, FacetName::Model->value);

        return array_map(
            fn (FilterOption $option): Model => new Model(
                slug: $option->slug,
                label: $option->label,
                count: $option->count,
            ),
            $options,
        );
    }

    /**
     * @param  SearchFacet[]  $facets
     * @return SearchFacetOption[]
     */
    private function extractFacetOptions(array $facets, string $facetName, ?string $preferredCategoryLabel = null): array
    {
        $matchedFacet = array_find($facets, fn ($facet): bool => $facet->name === $facetName);
        if (! $matchedFacet instanceof SearchFacet) {
            return [];
        }

        if ($preferredCategoryLabel !== null) {
            foreach ($matchedFacet->optionCategories as $category) {
                if (strcasecmp($category->label, $preferredCategoryLabel) !== 0) {
                    continue;
                }

                return $category->options;
            }
        }

        if ($matchedFacet->options !== []) {
            return $matchedFacet->options;
        }

        $options = [];

        foreach ($matchedFacet->optionCategories as $category) {
            $options = [...$options, ...$category->options];
        }

        return $options;
    }

    // --- REST API (search + facets) ---

    /**
     * POST JSON to a REST API endpoint and return the response body.
     *
     * @param  array<string, mixed>  $body
     */
    private function postJson(string $url, array $body): string
    {
        try {
            $response = $this->httpClient->sendRequest(
                new Request('POST', $url, [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Origin' => self::BASE_URL,
                ], json_encode($body, JSON_THROW_ON_ERROR)),
            );
        } catch (ClientExceptionInterface $clientException) {
            throw new ViaBOVAGException('HTTP request failed: '.$clientException->getMessage(), 0, $clientException);
        } catch (JsonException $jsonException) {
            throw new ViaBOVAGException('Failed to encode request body: '.$jsonException->getMessage(), 0, $jsonException);
        }

        $statusCode = $response->getStatusCode();

        if ($statusCode !== 200) {
            throw new ViaBOVAGException('HTTP '.$statusCode.': Unexpected response status.', $statusCode);
        }

        return (string) $response->getBody();
    }

    // --- _next/data (detail pages only) ---

    /**
     * @return array{0: string, 1: MobilityType}
     */
    private function extractSlugAndMobilityTypeFromDetailUrl(string $url): array
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            throw new ViaBOVAGException('Invalid detail URL: missing path.');
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/'))));

        if (count($segments) < 3 || strtolower($segments[1]) !== 'aanbod') {
            throw new ViaBOVAGException('Invalid detail URL: expected /{mobilityType}/aanbod/{slug}.');
        }

        $mobilityType = $this->mobilityTypeFromUrlSegment($segments[0]);

        if (! $mobilityType instanceof MobilityType) {
            throw new ViaBOVAGException('Invalid detail URL: unknown mobility type segment "'.$segments[0].'".');
        }

        $slug = rawurldecode($segments[count($segments) - 1]);

        return [$slug, $mobilityType];
    }

    private function mobilityTypeFromUrlSegment(string $segment): ?MobilityType
    {
        $normalizedSegment = strtolower($segment);

        return match ($normalizedSegment) {
            'motoren' => MobilityType::Motorcycle,
            'fietsen' => MobilityType::Bicycle,
            default => MobilityType::fromApiValue($normalizedSegment),
        };
    }

    private function buildDetailUrl(string $slug, MobilityType $mobilityType): string
    {
        $buildId = $this->ensureBuildId();

        $params = [
            'mobilityType' => $mobilityType->detailSlug(),
            'vehicleUrl' => $slug,
        ];

        return self::BASE_URL.'/_next/data/'.$buildId.'/nl-NL/vdp.json?'.http_build_query($params);
    }

    private function fetchJson(string $url): string
    {
        try {
            $response = $this->httpClient->sendRequest(
                new Request('GET', $url, [
                    'x-nextjs-data' => '1',
                ]),
            );
        } catch (ClientExceptionInterface $clientException) {
            throw new ViaBOVAGException('HTTP request failed: '.$clientException->getMessage(), 0, $clientException);
        }

        $statusCode = $response->getStatusCode();

        if ($statusCode === 404) {
            throw new NotFoundException('HTTP 404: Resource not found — build ID may be stale.', $statusCode);
        }

        if ($statusCode !== 200) {
            throw new ViaBOVAGException('HTTP '.$statusCode.': Unexpected response status.', $statusCode);
        }

        return (string) $response->getBody();
    }

    private function fetchJsonWithBuildIdRefresh(string $url): string
    {
        try {
            return $this->fetchJson($url);
        } catch (NotFoundException $notFoundException) {
            $previousBuildId = $this->buildId;

            if ($previousBuildId === null) {
                throw $notFoundException;
            }

            $this->invalidateBuildId();
            $nextBuildId = $this->ensureBuildId();

            if ($nextBuildId === $previousBuildId) {
                throw $notFoundException;
            }

            return $this->fetchJson(str_replace($previousBuildId, $nextBuildId, $url));
        }
    }

    // --- Build ID (shared, used only for detail pages) ---

    private function invalidateBuildId(): void
    {
        $this->buildId = null;
        $this->cache?->delete(self::CACHE_KEY);
    }

    private function ensureBuildId(): string
    {
        if ($this->buildId !== null) {
            return $this->buildId;
        }

        // Try cache
        if ($this->cache instanceof CacheInterface) {
            $cached = $this->cache->get(self::CACHE_KEY);
            if (is_string($cached) && $cached !== '') {
                $this->buildId = $cached;

                return $cached;
            }
        }

        // Fetch from homepage
        $this->buildId = $this->fetchBuildId();

        // Store in cache
        $this->cache?->set(self::CACHE_KEY, $this->buildId, $this->cacheTtl);

        return $this->buildId;
    }

    private function fetchBuildId(): string
    {
        try {
            $response = $this->httpClient->sendRequest(
                new Request('GET', self::BASE_URL),
            );
        } catch (ClientExceptionInterface $clientException) {
            throw new ViaBOVAGException('Failed to fetch homepage for build ID: '.$clientException->getMessage(), 0, $clientException);
        }

        if ($response->getStatusCode() !== 200) {
            throw new ViaBOVAGException('Failed to fetch homepage: HTTP '.$response->getStatusCode());
        }

        $html = (string) $response->getBody();

        return $this->parser->extractBuildId($html);
    }
}
