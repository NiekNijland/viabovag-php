<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use NiekNijland\ViaBOVAG\Data\Listing;
use NiekNijland\ViaBOVAG\Data\ListingDetail;
use NiekNijland\ViaBOVAG\Data\MobilityType;
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

    private const string CACHE_KEY = 'viabovag:build-id';

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
        $url = $this->buildSearchUrl($query);
        $json = $this->fetchJsonWithRetry($url);

        return $this->parser->parseSearchResults($json, $query->page());
    }

    public function getDetail(Listing $listing): ListingDetail
    {
        $mobilityType = MobilityType::tryFrom($listing->mobilityType)
            ?? throw new ViaBOVAGException('Unknown mobility type: '.$listing->mobilityType);

        return $this->getDetailBySlug($listing->friendlyUriPart, $mobilityType);
    }

    public function getDetailBySlug(string $slug, MobilityType $mobilityType = MobilityType::Motor): ListingDetail
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

    private function buildSearchUrl(SearchQuery $query): string
    {
        $buildId = $this->ensureBuildId();
        $filterSlugs = $query->toFilterSlugs();
        $selectedFilters = implode(',', $filterSlugs);

        $params = [
            'mobilityType' => $query->mobilityType()->searchSlug(),
        ];

        if ($selectedFilters !== '') {
            $params['selectedFilters'] = $selectedFilters;
        }

        if ($query->page() > 1) {
            $params['page'] = (string) $query->page();
        }

        return self::BASE_URL.'/_next/data/'.$buildId.'/nl-NL/srp.json?'.http_build_query($params);
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

    /**
     * Fetch JSON with stale build ID retry logic.
     * If we get a 404, invalidate the build ID, re-fetch it, and retry once.
     */
    private function fetchJsonWithRetry(string $url): string
    {
        try {
            return $this->fetchJson($url);
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

            return $this->fetchJson($url);
        }
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
            throw new NotFoundException('HTTP 404: Resource not found — build ID may be stale.');
        }

        if ($statusCode !== 200) {
            throw new ViaBOVAGException('HTTP '.$statusCode.': Unexpected response status.');
        }

        return (string) $response->getBody();
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
