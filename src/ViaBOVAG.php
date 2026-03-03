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
use NiekNijland\ViaBOVAG\Data\Listing;
use NiekNijland\ViaBOVAG\Data\ListingDetail;
use NiekNijland\ViaBOVAG\Data\MobilityType;
use NiekNijland\ViaBOVAG\Data\Model;
use NiekNijland\ViaBOVAG\Data\MotorcycleSearchCriteria;
use NiekNijland\ViaBOVAG\Data\SearchFacet;
use NiekNijland\ViaBOVAG\Data\SearchFacetOption;
use NiekNijland\ViaBOVAG\Data\SearchQuery;
use NiekNijland\ViaBOVAG\Data\SearchResult;
use NiekNijland\ViaBOVAG\Data\SortOrder;
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

    private const int SEARCH_PAGE_SIZE = 24;

    /** @var int[] */
    private const array RETRYABLE_STATUS_CODES = [429, 503];

    private ?string $buildId = null;

    private readonly JsonParser $parser;

    public function __construct(
        private readonly ClientInterface $httpClient = new Client,
        private readonly ?CacheInterface $cache = null,
        private readonly int $cacheTtl = 3600,
        private readonly int $maxRetries = 2,
    ) {
        $this->parser = new JsonParser;
    }

    public function search(SearchQuery $query): SearchResult
    {
        $page = $query->page();

        if ($page < 1) {
            throw new ViaBOVAGException('Search page must be greater than or equal to 1.');
        }

        $requestBody = $query->toRequestBody();

        if ($this->requiresClientSideSearch($requestBody)) {
            return $this->searchWithClientSideFiltering($requestBody, $page);
        }

        $json = $this->postSearchJsonWithColorFallback($requestBody);

        if ($json === null) {
            return new SearchResult(
                listings: [],
                totalCount: 0,
                currentPage: $page,
            );
        }

        $result = $this->parser->parseSearchResults($json, $page);

        return $this->applySearchResultFallbacks($result, $requestBody);
    }

    public function searchAll(SearchQuery $query): Generator
    {
        $requestBody = $query->toRequestBody();

        if ($this->requiresClientSideSearch($requestBody)) {
            yield from $this->searchAllWithClientSideFiltering($requestBody, $query->page());

            return;
        }

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
        $json = $this->fetchJsonWithRetry($url);

        return $this->parser->parseDetail($json);
    }

    public function resetSession(): void
    {
        $this->buildId = null;
        $this->cache?->delete(self::CACHE_KEY);
    }

    private function createEmptyCriteria(
        MobilityType $mobilityType,
        ?Brand $brand = null,
        ?Model $model = null,
    ): SearchQuery {
        return match ($mobilityType) {
            MobilityType::Motorcycle => new MotorcycleSearchCriteria(brand: $brand, model: $model),
            MobilityType::Car => new CarSearchCriteria(brand: $brand, model: $model),
            MobilityType::Bicycle => new BicycleSearchCriteria(brand: $brand, model: $model),
            MobilityType::Camper => new CamperSearchCriteria(brand: $brand, model: $model),
        };
    }

    /**
     * @return SearchFacet[]
     */
    private function fetchFacets(SearchQuery $query): array
    {
        $json = $this->postJsonWithTransientRetry(
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
     * POST JSON to a REST API endpoint with retry logic for transient errors.
     *
     * @param  array<string, mixed>  $body
     */
    private function postJsonWithTransientRetry(string $url, array $body): string
    {
        $lastException = null;

        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            try {
                return $this->postJson($url, $body);
            } catch (ViaBOVAGException $exception) {
                if (! $this->isTransientError($exception)) {
                    throw $exception;
                }

                $lastException = $exception;

                if ($attempt < $this->maxRetries) {
                    usleep(500_000 * 2 ** $attempt);
                }
            }
        }

        throw $lastException ?? new ViaBOVAGException('Request failed after '.($this->maxRetries + 1).' attempts.');
    }

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

    /**
     * Determine whether search should use full client-side filtering.
     *
     * @param  array<string, mixed>  $requestBody
     */
    private function requiresClientSideSearch(array $requestBody): bool
    {
        if (($requestBody['IsFinanceable'] ?? false) === true) {
            return true;
        }

        if (($requestBody['HasBovagImportOdometerCheck'] ?? false) === true) {
            return true;
        }

        if (($requestBody['HasNapWeblabel'] ?? false) === true || ($requestBody['HasNapOrBit'] ?? false) === true) {
            return true;
        }

        if (($requestBody['Import'] ?? null) === ['Nee']) {
            return true;
        }

        if (isset($requestBody['YearFrom']) || isset($requestBody['YearTo'])) {
            return true;
        }

        if (isset($requestBody['MileageFrom']) || isset($requestBody['MileageTo'])) {
            return true;
        }

        $cities = $requestBody['City'] ?? null;

        if (is_array($cities) && count($cities) > 1) {
            return true;
        }

        $specifiedBatteryRange = $requestBody['SpecifiedBatteryRange'] ?? null;

        return is_array($specifiedBatteryRange) && count($specifiedBatteryRange) > 1;
    }

    /**
     * @param  array<string, mixed>  $requestBody
     */
    private function searchWithClientSideFiltering(array $requestBody, int $page): SearchResult
    {
        $listings = $this->buildClientSideFilteredListings($requestBody);

        $offset = ($page - 1) * self::SEARCH_PAGE_SIZE;
        $pageListings = array_slice($listings, $offset, self::SEARCH_PAGE_SIZE);

        return new SearchResult(
            listings: $pageListings,
            totalCount: count($listings),
            currentPage: $page,
        );
    }

    /**
     * @param  array<string, mixed>  $requestBody
     */
    private function searchAllWithClientSideFiltering(array $requestBody, int $startPage = 1): Generator
    {
        $listings = $this->buildClientSideFilteredListings($requestBody);
        $offset = max(0, ($startPage - 1) * self::SEARCH_PAGE_SIZE);

        foreach (array_slice($listings, $offset) as $listing) {
            yield $listing;
        }
    }

    /**
     * @param  array<string, mixed>  $requestBody
     * @return Listing[]
     */
    private function buildClientSideFilteredListings(array $requestBody): array
    {
        $yearFrom = isset($requestBody['YearFrom']) && is_int($requestBody['YearFrom'])
            ? $requestBody['YearFrom']
            : null;
        $yearTo = isset($requestBody['YearTo']) && is_int($requestBody['YearTo'])
            ? $requestBody['YearTo']
            : null;
        $mileageFrom = isset($requestBody['MileageFrom']) && is_int($requestBody['MileageFrom'])
            ? $requestBody['MileageFrom']
            : null;
        $mileageTo = isset($requestBody['MileageTo']) && is_int($requestBody['MileageTo'])
            ? $requestBody['MileageTo']
            : null;

        $requestedCities = [];
        $cityFilter = $requestBody['City'] ?? null;
        if (is_array($cityFilter) && count($cityFilter) > 1) {
            foreach ($cityFilter as $city) {
                if (is_string($city) && $city !== '') {
                    $requestedCities[] = strtolower($city);
                }
            }
        }

        $requestedCities = array_values(array_unique($requestedCities));

        $requestedSpecifiedBatteryRanges = [];
        $specifiedBatteryRangeFilter = $requestBody['SpecifiedBatteryRange'] ?? null;
        if (is_array($specifiedBatteryRangeFilter) && count($specifiedBatteryRangeFilter) > 1) {
            foreach ($specifiedBatteryRangeFilter as $specifiedBatteryRange) {
                if (is_string($specifiedBatteryRange) && $specifiedBatteryRange !== '') {
                    $requestedSpecifiedBatteryRanges[] = $specifiedBatteryRange;
                }
            }
        }

        $requestedSpecifiedBatteryRanges = array_values(array_unique($requestedSpecifiedBatteryRanges));

        $notImportedRequested = (($requestBody['Import'] ?? null) === ['Nee']);

        $serverBody = $requestBody;
        unset(
            $serverBody['YearFrom'],
            $serverBody['YearTo'],
            $serverBody['MileageFrom'],
            $serverBody['MileageTo'],
            $serverBody['PageNumber'],
        );

        if ($notImportedRequested) {
            unset($serverBody['Import']);
        }

        if ($requestedCities !== []) {
            unset($serverBody['City']);
        }

        if ($requestedSpecifiedBatteryRanges !== []) {
            unset($serverBody['SpecifiedBatteryRange']);
        }

        if ($requestedSpecifiedBatteryRanges === []) {
            $listings = $this->fetchAllListingsForBody($serverBody);
        } else {
            $listingsById = [];

            foreach ($requestedSpecifiedBatteryRanges as $specifiedBatteryRange) {
                $specifiedBatteryRangeBody = $serverBody;
                $specifiedBatteryRangeBody['SpecifiedBatteryRange'] = $specifiedBatteryRange;

                foreach ($this->fetchAllListingsForBody($specifiedBatteryRangeBody) as $listing) {
                    $listingsById[$listing->id] = $listing;
                }
            }

            $listings = array_values($listingsById);
        }

        if ($requestedCities !== []) {
            $requestedCityLookup = array_fill_keys($requestedCities, true);

            $listings = array_values(array_filter(
                $listings,
                function (Listing $listing) use ($requestedCityLookup): bool {
                    if ($listing->company->city === null || $listing->company->city === '') {
                        return false;
                    }

                    $city = strtolower($listing->company->city);
                    $citySlug = $this->slugify($listing->company->city);

                    return isset($requestedCityLookup[$city]) || isset($requestedCityLookup[$citySlug]);
                },
            ));
        }

        if ($yearFrom !== null) {
            $listings = array_values(array_filter(
                $listings,
                fn (Listing $listing): bool => $listing->vehicle->year >= $yearFrom,
            ));
        }

        if ($yearTo !== null) {
            $listings = array_values(array_filter(
                $listings,
                fn (Listing $listing): bool => $listing->vehicle->year <= $yearTo,
            ));
        }

        if ($mileageFrom !== null) {
            $listings = array_values(array_filter(
                $listings,
                fn (Listing $listing): bool => $listing->vehicle->mileage >= $mileageFrom,
            ));
        }

        if ($mileageTo !== null) {
            $listings = array_values(array_filter(
                $listings,
                fn (Listing $listing): bool => $listing->vehicle->mileage <= $mileageTo,
            ));
        }

        if ($notImportedRequested) {
            $importedBody = $serverBody;
            $importedBody['Import'] = ['Ja'];
            $importedIds = array_fill_keys(
                array_map(
                    fn (Listing $listing): string => $listing->id,
                    $this->fetchAllListingsForBody($importedBody),
                ),
                true,
            );

            if ($importedIds !== []) {
                $listings = array_values(array_filter(
                    $listings,
                    fn (Listing $listing): bool => ! isset($importedIds[$listing->id]),
                ));
            }
        }

        $fallbackResult = $this->applySearchResultFallbacks(
            new SearchResult(
                listings: $listings,
                totalCount: count($listings),
                currentPage: 1,
            ),
            $requestBody,
        );

        return $fallbackResult->listings;
    }

    /**
     * @param  array<string, mixed>  $requestBody
     * @return Listing[]
     */
    private function fetchAllListingsForBody(array $requestBody): array
    {
        $listings = [];
        $page = 1;

        while (true) {
            $body = $requestBody;

            if ($page > 1) {
                $body['PageNumber'] = $page;
            }

            $json = $this->postSearchJsonWithColorFallback($body);

            if ($json === null) {
                return [];
            }

            $result = $this->parser->parseSearchResults($json, $page);
            $listings = [...$listings, ...$result->listings];

            if (! $result->hasNextPage()) {
                return $listings;
            }

            $page++;
        }
    }

    /**
     * Post search request, retrying once with sanitized colors when needed.
     *
     * Returns null when no requested colors are valid for the current query.
     *
     * @param  array<string, mixed>  $requestBody
     */
    private function postSearchJsonWithColorFallback(array $requestBody): ?string
    {
        try {
            return $this->postJsonWithTransientRetry(
                self::BASE_URL.self::SEARCH_ENDPOINT,
                $requestBody,
            );
        } catch (ViaBOVAGException $viabovagException) {
            if ($viabovagException->getCode() !== 500 || ! isset($requestBody['Color'])) {
                throw $viabovagException;
            }

            $sanitizedBody = $this->sanitizeColorFilter($requestBody);

            if ($sanitizedBody === null) {
                return null;
            }

            if ($sanitizedBody === $requestBody) {
                throw $viabovagException;
            }

            return $this->postJsonWithTransientRetry(
                self::BASE_URL.self::SEARCH_ENDPOINT,
                $sanitizedBody,
            );
        }
    }

    private function slugify(string $value): string
    {
        return (string) preg_replace('/\s+/', '-', strtolower(trim($value)));
    }

    /**
     * Apply client-side fallbacks for filters and sorting that are inconsistently applied server-side.
     *
     * @param  array<string, mixed>  $requestBody
     */
    private function applySearchResultFallbacks(SearchResult $result, array $requestBody): SearchResult
    {
        $listings = $result->listings;

        if (($requestBody['IsFinanceable'] ?? false) === true) {
            $listings = array_values(array_filter(
                $listings,
                fn (Listing $listing): bool => $listing->isFinanceable,
            ));
        }

        if (($requestBody['HasBovagImportOdometerCheck'] ?? false) === true) {
            $listings = array_values(array_filter(
                $listings,
                fn (Listing $listing): bool => in_array('HasBovagImportOdometerCheck', $listing->vehicle->certaintyKeys, true),
            ));
        }

        if (($requestBody['HasNapWeblabel'] ?? false) === true || ($requestBody['HasNapOrBit'] ?? false) === true) {
            $listings = array_values(array_filter(
                $listings,
                fn (Listing $listing): bool => in_array('HasNapWeblabel', $listing->vehicle->certaintyKeys, true)
                    || in_array('HasNapOrBit', $listing->vehicle->certaintyKeys, true),
            ));
        }

        if (($requestBody['SortOrder'] ?? null) === SortOrder::MileageAscending->value) {
            usort(
                $listings,
                fn (Listing $left, Listing $right): int => $left->vehicle->mileage <=> $right->vehicle->mileage,
            );
        }

        if ($listings === $result->listings) {
            return $result;
        }

        return new SearchResult(
            listings: $listings,
            totalCount: $result->totalCount,
            currentPage: $result->currentPage,
            facets: $result->facets,
        );
    }

    /**
     * Sanitize color filters against available facet options.
     *
     * Returns null when no requested colors are valid.
     *
     * @param  array<string, mixed>  $requestBody
     * @return array<string, mixed>|null
     */
    private function sanitizeColorFilter(array $requestBody): ?array
    {
        $requestedColors = $requestBody['Color'] ?? null;

        if (! is_array($requestedColors) || $requestedColors === []) {
            return $requestBody;
        }

        $facetsBody = $requestBody;
        unset($facetsBody['Color']);

        $facetsJson = $this->postJsonWithTransientRetry(
            self::BASE_URL.self::FACETS_ENDPOINT,
            $facetsBody,
        );

        $facets = $this->parser->parseSearchFacets($facetsJson);
        $options = $this->extractFilterOptionsFromFacets($facets, FacetName::Color->value);

        $allowed = [];
        foreach ($options as $option) {
            $allowed[strtolower($option->label)] = $option->label;
            $allowed[strtolower($option->slug)] = $option->label;
        }

        $validColors = [];
        foreach ($requestedColors as $requestedColor) {
            if (! is_string($requestedColor)) {
                continue;
            }

            $key = strtolower($requestedColor);
            if (isset($allowed[$key])) {
                $validColors[] = $allowed[$key];
            }
        }

        $validColors = array_values(array_unique($validColors));

        if ($validColors === []) {
            return null;
        }

        $requestBody['Color'] = $validColors;

        return $requestBody;
    }

    // --- _next/data (detail pages only) ---

    private function buildDetailUrl(string $slug, MobilityType $mobilityType): string
    {
        $buildId = $this->ensureBuildId();

        $params = [
            'mobilityType' => $mobilityType->detailSlug(),
            'vehicleUrl' => $slug,
        ];

        return self::BASE_URL.'/_next/data/'.$buildId.'/nl-NL/vdp.json?'.http_build_query($params);
    }

    /**
     * Fetch JSON with stale build ID retry logic.
     * If we get a 404, invalidate the build ID, re-fetch it, and retry once.
     */
    private function fetchJsonWithRetry(string $url): string
    {
        try {
            return $this->fetchJsonWithTransientRetry($url);
        } catch (NotFoundException $notFoundException) {
            // Stale build ID — invalidate and retry
            $oldBuildId = $this->buildId;

            if ($oldBuildId === null) {
                throw $notFoundException;
            }

            $this->buildId = null;
            $this->cache?->delete(self::CACHE_KEY);

            $newBuildId = $this->ensureBuildId();

            if ($newBuildId === $oldBuildId) {
                throw $notFoundException;
            }

            // Replace old build ID in URL
            $url = str_replace($oldBuildId, $newBuildId, $url);

            return $this->fetchJsonWithTransientRetry($url);
        }
    }

    /**
     * Fetch JSON with retry logic for transient HTTP errors (429, 503).
     * Retries up to $maxRetries times with exponential backoff.
     */
    private function fetchJsonWithTransientRetry(string $url): string
    {
        $lastException = null;

        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            try {
                return $this->fetchJson($url);
            } catch (ViaBOVAGException $exception) {
                // Let 404s bubble up immediately — handled by stale build ID retry
                if ($exception instanceof NotFoundException) {
                    throw $exception;
                }

                // Only retry on transient status codes
                if (! $this->isTransientError($exception)) {
                    throw $exception;
                }

                $lastException = $exception;

                // Exponential backoff: 500ms, 1000ms, ...
                if ($attempt < $this->maxRetries) {
                    usleep(500_000 * 2 ** $attempt);
                }
            }
        }

        throw $lastException ?? new ViaBOVAGException('Request failed after '.($this->maxRetries + 1).' attempts.');
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

    // --- Build ID (shared, used only for detail pages) ---

    /**
     * Check if an exception represents a transient HTTP error that should be retried.
     */
    private function isTransientError(ViaBOVAGException $exception): bool
    {
        return in_array($exception->getCode(), self::RETRYABLE_STATUS_CODES, true);
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
