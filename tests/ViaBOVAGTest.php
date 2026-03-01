<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use NiekNijland\ViaBOVAG\Data\MobilityType;
use NiekNijland\ViaBOVAG\Data\MotorcycleBodyType;
use NiekNijland\ViaBOVAG\Data\MotorcycleFuelType;
use NiekNijland\ViaBOVAG\Data\MotorcycleSearchCriteria;
use NiekNijland\ViaBOVAG\Exception\NotFoundException;
use NiekNijland\ViaBOVAG\Exception\ViaBOVAGException;
use NiekNijland\ViaBOVAG\ViaBOVAG;
use PHPUnit\Framework\TestCase;

class ViaBOVAGTest extends TestCase
{
    private function fixture(string $name): string
    {
        return file_get_contents(__DIR__.'/Fixtures/'.$name);
    }

    private function createClient(MockHandler $mock): ViaBOVAG
    {
        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        return new ViaBOVAG(httpClient: $httpClient);
    }

    private function createClientWithCache(MockHandler $mock, ArrayCache $cache): ViaBOVAG
    {
        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        return new ViaBOVAG(httpClient: $httpClient, cache: $cache);
    }

    // --- Build ID Extraction ---

    public function test_extracts_build_id_from_homepage(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $searchJson = $this->fixture('search-results.json');

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClient($mock);
        $result = $client->search(new MotorcycleSearchCriteria);

        $this->assertNotEmpty($result->listings);
    }

    public function test_throws_exception_when_build_id_not_found(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '<html><body>No build ID here</body></html>'),
        ]);

        $client = $this->createClient($mock);

        $this->expectException(ViaBOVAGException::class);
        $this->expectExceptionMessage('Could not extract build ID');

        $client->search(new MotorcycleSearchCriteria);
    }

    // --- Search Results Parsing ---

    public function test_parses_search_results(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $searchJson = $this->fixture('search-results.json');

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClient($mock);
        $result = $client->search(new MotorcycleSearchCriteria);

        $this->assertCount(24, $result->listings);
        $this->assertGreaterThan(0, $result->totalCount);
        $this->assertSame(1, $result->currentPage);

        $listing = $result->listings[0];
        $this->assertNotEmpty($listing->id);
        $this->assertNotEmpty($listing->title);
        $this->assertGreaterThan(0, $listing->price);
        $this->assertNotEmpty($listing->vehicle->brand);
        $this->assertNotEmpty($listing->company->name);
    }

    public function test_search_result_pagination_helpers(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $searchJson = $this->fixture('search-results.json');

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClient($mock);
        $result = $client->search(new MotorcycleSearchCriteria);

        $this->assertSame(24, $result->pageSize());
        $this->assertGreaterThan(0, $result->totalPages());
        $this->assertTrue($result->hasNextPage());
        $this->assertFalse($result->hasPreviousPage());
    }

    // --- Detail Parsing ---

    public function test_parses_listing_detail(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $detailJson = $this->fixture('listing-detail.json');

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $detailJson),
        ]);

        $client = $this->createClient($mock);
        $detail = $client->getDetailBySlug('harley-davidson-flhxs-streetglide-special-flhx-55pz3zg');

        $this->assertNotEmpty($detail->id);
        $this->assertNotEmpty($detail->title);
        $this->assertGreaterThan(0, $detail->price);
        $this->assertNotEmpty($detail->media);
        $this->assertNotEmpty($detail->specificationGroups);
        $this->assertNotEmpty($detail->company->name);
        $this->assertNotEmpty($detail->vehicle->brand);
    }

    public function test_parses_detail_company_data(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $detailJson = $this->fixture('listing-detail.json');

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $detailJson),
        ]);

        $client = $this->createClient($mock);
        $detail = $client->getDetailBySlug('test-slug');

        $this->assertNotEmpty($detail->company->city);
        $this->assertNotNull($detail->company->latitude);
        $this->assertNotNull($detail->company->longitude);
    }

    public function test_get_detail_from_listing(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $searchJson = $this->fixture('search-results.json');
        $detailJson = $this->fixture('listing-detail.json');

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $searchJson),
            new Response(200, [], $detailJson),
        ]);

        $client = $this->createClient($mock);
        $result = $client->search(new MotorcycleSearchCriteria);
        $detail = $client->getDetail($result->listings[0]);

        $this->assertNotEmpty($detail->id);
    }

    // --- Stale Build ID Retry ---

    public function test_retries_on_stale_build_id(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $searchJson = $this->fixture('search-results.json');

        // Build a homepage with a different build ID for the retry
        $newHomepageHtml = str_replace('PQS_ur-FpJe0R6JUyWiD2', 'NEW_BUILD_ID_12345', $homepageHtml);

        $mock = new MockHandler([
            // First: fetch homepage for build ID
            new Response(200, [], $homepageHtml),
            // Second: search returns 404 (stale build ID)
            new Response(404, [], ''),
            // Third: re-fetch homepage for new build ID
            new Response(200, [], $newHomepageHtml),
            // Fourth: retry search with new build ID
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClient($mock);
        $result = $client->search(new MotorcycleSearchCriteria);

        $this->assertCount(24, $result->listings);
    }

    public function test_throws_exception_on_double_404(): void
    {
        $homepageHtml = $this->fixture('homepage.html');

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(404, [], ''),
            // Same build ID returned — no retry possible
            new Response(200, [], $homepageHtml),
        ]);

        $client = $this->createClient($mock);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('404');

        $client->search(new MotorcycleSearchCriteria);
    }

    // --- Cache ---

    public function test_caches_build_id(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $searchJson = $this->fixture('search-results.json');

        $cache = new ArrayCache;

        $mock = new MockHandler([
            // First request: fetch homepage + search
            new Response(200, [], $homepageHtml),
            new Response(200, [], $searchJson),
            // Second request: only search (build ID from cache)
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClientWithCache($mock, $cache);

        // First call — fetches homepage for build ID
        $client->search(new MotorcycleSearchCriteria);

        $this->assertTrue($cache->has('viabovag:build-id'));

        // Second call — uses cached build ID
        $result = $client->search(new MotorcycleSearchCriteria);

        $this->assertCount(24, $result->listings);
    }

    public function test_reset_session_clears_cache(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $searchJson = $this->fixture('search-results.json');

        $cache = new ArrayCache;

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $searchJson),
            // After reset: fetch homepage again + search
            new Response(200, [], $homepageHtml),
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClientWithCache($mock, $cache);

        $client->search(new MotorcycleSearchCriteria);
        $this->assertTrue($cache->has('viabovag:build-id'));

        $client->resetSession();
        $this->assertFalse($cache->has('viabovag:build-id'));

        // Next call re-fetches homepage
        $result = $client->search(new MotorcycleSearchCriteria);
        $this->assertCount(24, $result->listings);
    }

    // --- SearchCriteria URL Mapping ---

    public function test_search_criteria_filter_slugs_brand_and_model(): void
    {
        $criteria = new MotorcycleSearchCriteria(
            brand: 'suzuki',
            model: 'gsx-r-1300-hayabusa',
        );

        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('merk-suzuki', $slugs);
        $this->assertContains('model-gsx-r-1300-hayabusa', $slugs);
    }

    public function test_search_criteria_filter_slugs_price_range(): void
    {
        $criteria = new MotorcycleSearchCriteria(
            priceFrom: 5000,
            priceTo: 15000,
        );

        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('prijs-vanaf-5000', $slugs);
        $this->assertContains('prijs-tot-en-met-15000', $slugs);
    }

    public function test_search_criteria_filter_slugs_body_types(): void
    {
        $criteria = new MotorcycleSearchCriteria(
            bodyTypes: [MotorcycleBodyType::SuperSport, MotorcycleBodyType::Naked],
        );

        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('supersport', $slugs);
        $this->assertContains('naked', $slugs);
    }

    public function test_search_criteria_filter_slugs_fuel_types(): void
    {
        $criteria = new MotorcycleSearchCriteria(
            fuelTypes: [MotorcycleFuelType::Benzine],
        );

        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('benzine', $slugs);
    }

    public function test_search_criteria_empty_returns_no_slugs(): void
    {
        $criteria = new MotorcycleSearchCriteria;
        $slugs = $criteria->toFilterSlugs();

        $this->assertEmpty($slugs);
    }

    // --- Error Handling ---

    public function test_throws_on_invalid_json_response(): void
    {
        $homepageHtml = $this->fixture('homepage.html');

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], 'not valid json'),
        ]);

        $client = $this->createClient($mock);

        $this->expectException(ViaBOVAGException::class);
        $this->expectExceptionMessage('Failed to decode JSON');

        $client->search(new MotorcycleSearchCriteria);
    }

    public function test_throws_on_missing_search_results(): void
    {
        $homepageHtml = $this->fixture('homepage.html');

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], '{"pageProps":{}}'),
        ]);

        $client = $this->createClient($mock);

        $this->expectException(ViaBOVAGException::class);
        $this->expectExceptionMessage('missing serverSearchResults');

        $client->search(new MotorcycleSearchCriteria);
    }

    public function test_throws_on_missing_vehicle_detail(): void
    {
        $homepageHtml = $this->fixture('homepage.html');

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], '{"pageProps":{}}'),
        ]);

        $client = $this->createClient($mock);

        $this->expectException(ViaBOVAGException::class);
        $this->expectExceptionMessage('missing vehicle data');

        $client->getDetailBySlug('test-slug');
    }

    public function test_throws_on_unexpected_status_code(): void
    {
        $homepageHtml = $this->fixture('homepage.html');

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(500, [], 'Internal Server Error'),
        ]);

        $client = $this->createClient($mock);

        $this->expectException(ViaBOVAGException::class);
        $this->expectExceptionMessage('HTTP 500');

        $client->search(new MotorcycleSearchCriteria);
    }

    public function test_mobility_type_search_and_detail_slugs(): void
    {
        $this->assertSame('motoren', MobilityType::Motor->searchSlug());
        $this->assertSame('motor', MobilityType::Motor->detailSlug());
        $this->assertSame('auto', MobilityType::Car->searchSlug());
        $this->assertSame('auto', MobilityType::Car->detailSlug());
        $this->assertSame('fietsen', MobilityType::Bicycle->searchSlug());
        $this->assertSame('fiets', MobilityType::Bicycle->detailSlug());
        $this->assertSame('campers', MobilityType::Camper->searchSlug());
        $this->assertSame('camper', MobilityType::Camper->detailSlug());
    }
}
