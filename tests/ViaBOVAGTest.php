<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use NiekNijland\ViaBOVAG\Data\AvailableSince;
use NiekNijland\ViaBOVAG\Data\BicycleSearchCriteria;
use NiekNijland\ViaBOVAG\Data\BovagWarranty;
use NiekNijland\ViaBOVAG\Data\Brand;
use NiekNijland\ViaBOVAG\Data\CamperSearchCriteria;
use NiekNijland\ViaBOVAG\Data\CarSearchCriteria;
use NiekNijland\ViaBOVAG\Data\Condition;
use NiekNijland\ViaBOVAG\Data\CylinderCount;
use NiekNijland\ViaBOVAG\Data\DriversLicense;
use NiekNijland\ViaBOVAG\Data\FacetName;
use NiekNijland\ViaBOVAG\Data\FilterOption;
use NiekNijland\ViaBOVAG\Data\GearCount;
use NiekNijland\ViaBOVAG\Data\Listing;
use NiekNijland\ViaBOVAG\Data\MobilityType;
use NiekNijland\ViaBOVAG\Data\Model;
use NiekNijland\ViaBOVAG\Data\MotorcycleBodyType;
use NiekNijland\ViaBOVAG\Data\MotorcycleFuelType;
use NiekNijland\ViaBOVAG\Data\MotorcycleSearchCriteria;
use NiekNijland\ViaBOVAG\Data\SearchQuery;
use NiekNijland\ViaBOVAG\Data\SeatCount;
use NiekNijland\ViaBOVAG\Data\SortOrder;
use NiekNijland\ViaBOVAG\Data\TransmissionType;
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

    /**
     * Create a client with request history tracking.
     *
     * @param  array<int, array<string, mixed>>  $history  Populated by reference
     */
    private function createClientWithHistory(MockHandler $mock, array &$history): ViaBOVAG
    {
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($history));

        $httpClient = new Client(['handler' => $handlerStack]);

        return new ViaBOVAG(httpClient: $httpClient);
    }

    // --- Build ID Extraction (for detail pages) ---

    public function test_extracts_build_id_from_homepage(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $detailJson = $this->fixture('listing-detail.json');

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $detailJson),
        ]);

        $client = $this->createClient($mock);
        $detail = $client->getDetailBySlug('test-slug', MobilityType::Motorcycle);

        $this->assertNotEmpty($detail->id);
    }

    public function test_throws_exception_when_build_id_not_found(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '<html><body>No build ID here</body></html>'),
        ]);

        $client = $this->createClient($mock);

        $this->expectException(ViaBOVAGException::class);
        $this->expectExceptionMessage('Could not extract build ID');

        $client->getDetailBySlug('test-slug', MobilityType::Motorcycle);
    }

    // --- Search Results Parsing (REST API) ---

    public function test_parses_search_results(): void
    {
        $searchJson = $this->fixture('search-results-api.json');

        $mock = new MockHandler([
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

    public function test_parses_car_search_results_with_auto_mobility_type(): void
    {
        $searchJson = $this->buildSearchJson(listingCount: 1, totalCount: 1, mobilityType: 'auto');

        $mock = new MockHandler([
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClient($mock);
        $result = $client->search(new CarSearchCriteria);

        $this->assertSame(MobilityType::Car, $result->listings[0]->mobilityType);
    }

    public function test_parses_bicycle_search_results_with_fiets_mobility_type(): void
    {
        $searchJson = $this->buildSearchJson(listingCount: 1, totalCount: 1, mobilityType: 'fiets');

        $mock = new MockHandler([
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClient($mock);
        $result = $client->search(new BicycleSearchCriteria);

        $this->assertSame(MobilityType::Bicycle, $result->listings[0]->mobilityType);
    }

    public function test_search_result_pagination_helpers(): void
    {
        $searchJson = $this->fixture('search-results-api.json');

        $mock = new MockHandler([
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClient($mock);
        $result = $client->search(new MotorcycleSearchCriteria);

        $this->assertSame(24, $result->pageSize());
        $this->assertGreaterThan(0, $result->totalPages());
        $this->assertTrue($result->hasNextPage());
        $this->assertFalse($result->hasPreviousPage());
    }

    public function test_get_brands_returns_brand_value_objects(): void
    {
        $facetsJson = $this->fixture('search-facets-api.json');

        $mock = new MockHandler([
            new Response(200, [], $facetsJson),
        ]);

        $client = $this->createClient($mock);
        $brands = $client->getBrands(MobilityType::Motorcycle);

        $this->assertNotEmpty($brands);
        $this->assertContainsOnlyInstancesOf(Brand::class, $brands);

        $slugs = array_map(fn (Brand $brand): string => $brand->slug, $brands);

        $this->assertCount(count($slugs), array_unique($slugs));
        $this->assertContains('harley-davidson', $slugs);

        $harleyBrand = array_values(array_filter(
            $brands,
            fn (Brand $brand): bool => $brand->slug === 'harley-davidson',
        ))[0] ?? null;

        $this->assertInstanceOf(Brand::class, $harleyBrand);
        $this->assertSame('Harley-Davidson', $harleyBrand->label);
    }

    public function test_get_brands_does_not_depend_on_listing_parsing(): void
    {
        $facetsJson = '{"count":1,"facets":[{"name":"Brand","label":"Merk","options":[{"name":"tesla","label":"Tesla","count":12}],"optionCategories":[]}]}';

        $mock = new MockHandler([
            new Response(200, [], $facetsJson),
        ]);

        $client = $this->createClient($mock);
        $brands = $client->getBrands(MobilityType::Car);

        $this->assertCount(1, $brands);
        $this->assertSame('tesla', $brands[0]->slug);
    }

    public function test_get_brands_uses_requested_mobility_type(): void
    {
        $facetsJson = $this->fixture('search-facets-api.json');

        $history = [];
        $mock = new MockHandler([
            new Response(200, [], $facetsJson),
        ]);

        $client = $this->createClientWithHistory($mock, $history);
        $client->getBrands(MobilityType::Car);

        /** @var Request $facetsRequest */
        $facetsRequest = $history[0]['request'];
        $body = json_decode((string) $facetsRequest->getBody(), true);

        $this->assertSame('auto', $body['MobilityType']);
    }

    public function test_get_brands_returns_empty_when_brand_facet_is_missing(): void
    {
        $facetsJson = '{"count":0,"facets":[]}';

        $mock = new MockHandler([
            new Response(200, [], $facetsJson),
        ]);

        $client = $this->createClient($mock);
        $brands = $client->getBrands(MobilityType::Motorcycle);

        $this->assertSame([], $brands);
    }

    public function test_get_brands_supports_top_level_brand_options(): void
    {
        $facetsJson = '{"count":0,"facets":[{"name":"Brand","label":"Merk","options":[{"name":"tesla","label":"Tesla","count":12}],"optionCategories":[]}]}';

        $mock = new MockHandler([
            new Response(200, [], $facetsJson),
        ]);

        $client = $this->createClient($mock);
        $brands = $client->getBrands(MobilityType::Car);

        $this->assertCount(1, $brands);
        $this->assertSame('tesla', $brands[0]->slug);
        $this->assertSame('Tesla', $brands[0]->label);
        $this->assertSame(12, $brands[0]->count);
    }

    public function test_get_facet_options_returns_filter_option_value_objects(): void
    {
        $facetsJson = '{"count":0,"facets":[{"name":"EngineBrand","label":"Motormerk","options":[{"name":"bosch","label":"Bosch","count":18},{"name":"shimano","label":"Shimano","count":7}],"optionCategories":[]}]}';

        $mock = new MockHandler([
            new Response(200, [], $facetsJson),
        ]);

        $client = $this->createClient($mock);
        $options = $client->getFacetOptions(MobilityType::Bicycle, FacetName::EngineBrand);

        $this->assertCount(2, $options);
        $this->assertContainsOnlyInstancesOf(FilterOption::class, $options);
        $this->assertSame('bosch', $options[0]->slug);
        $this->assertSame('Bosch', $options[0]->label);
        $this->assertSame(18, $options[0]->count);
    }

    public function test_get_facet_options_includes_brand_and_model_filters_when_provided(): void
    {
        $facetsJson = '{"count":0,"facets":[{"name":"FrameType","label":"Frametype","options":[],"optionCategories":[]}]}';

        $history = [];
        $mock = new MockHandler([
            new Response(200, [], $facetsJson),
        ]);

        $client = $this->createClientWithHistory($mock, $history);
        $client->getFacetOptions(
            MobilityType::Motorcycle,
            FacetName::FrameType,
            new Brand(slug: 'yamaha', label: 'Yamaha'),
            new Model(slug: 'mt-07', label: 'MT-07'),
        );

        /** @var Request $facetsRequest */
        $facetsRequest = $history[0]['request'];
        $body = json_decode((string) $facetsRequest->getBody(), true);

        $this->assertSame(['yamaha'], $body['Brand']);
        $this->assertSame('mt-07', $body['Model']);
    }

    public function test_get_facet_options_supports_option_categories(): void
    {
        $facetsJson = '{"count":0,"facets":[{"name":"FrameType","label":"Frametype","options":[],"optionCategories":[{"label":"Populair","options":[{"name":"dubbel-wieg","label":"Dubbel wieg","count":3}]}]}]}';

        $mock = new MockHandler([
            new Response(200, [], $facetsJson),
        ]);

        $client = $this->createClient($mock);
        $options = $client->getFacetOptions(MobilityType::Motorcycle, FacetName::FrameType);

        $this->assertCount(1, $options);
        $this->assertSame('dubbel-wieg', $options[0]->slug);
        $this->assertSame('Dubbel wieg', $options[0]->label);
        $this->assertSame(3, $options[0]->count);
    }

    public function test_get_facet_options_returns_empty_when_facet_is_missing(): void
    {
        $facetsJson = '{"count":0,"facets":[]}';

        $mock = new MockHandler([
            new Response(200, [], $facetsJson),
        ]);

        $client = $this->createClient($mock);
        $options = $client->getFacetOptions(MobilityType::Car, FacetName::EnergyLabel);

        $this->assertSame([], $options);
    }

    public function test_get_models_returns_model_value_objects(): void
    {
        $facetsJson = '{"count":0,"facets":[{"name":"Model","label":"Type uitvoering","options":[{"name":"golf","label":"Golf","count":48},{"name":"polo","label":"Polo","count":22}],"optionCategories":[]}]}';

        $mock = new MockHandler([
            new Response(200, [], $facetsJson),
        ]);

        $client = $this->createClient($mock);
        $models = $client->getModels(
            MobilityType::Car,
            new Brand(slug: 'volkswagen', label: 'Volkswagen'),
        );

        $this->assertCount(2, $models);
        $this->assertContainsOnlyInstancesOf(Model::class, $models);
        $this->assertSame('golf', $models[0]->slug);
        $this->assertSame('Golf', $models[0]->label);
        $this->assertSame(48, $models[0]->count);
    }

    public function test_get_models_includes_brand_filter_when_brand_is_provided(): void
    {
        $facetsJson = '{"count":0,"facets":[{"name":"Model","label":"Type uitvoering","options":[],"optionCategories":[]}]}';

        $history = [];
        $mock = new MockHandler([
            new Response(200, [], $facetsJson),
        ]);

        $client = $this->createClientWithHistory($mock, $history);
        $client->getModels(
            MobilityType::Car,
            new Brand(slug: 'volkswagen', label: 'Volkswagen'),
        );

        /** @var Request $facetsRequest */
        $facetsRequest = $history[0]['request'];
        $body = json_decode((string) $facetsRequest->getBody(), true);

        $this->assertSame(['volkswagen'], $body['Brand']);
    }

    public function test_get_models_supports_option_categories(): void
    {
        $facetsJson = '{"count":0,"facets":[{"name":"Model","label":"Type uitvoering","options":[],"optionCategories":[{"label":"Populair","options":[{"name":"gsx-r-1000","label":"GSX-R 1000","count":10}]}]}]}';

        $mock = new MockHandler([
            new Response(200, [], $facetsJson),
        ]);

        $client = $this->createClient($mock);
        $models = $client->getModels(MobilityType::Motorcycle);

        $this->assertCount(1, $models);
        $this->assertSame('gsx-r-1000', $models[0]->slug);
        $this->assertSame('GSX-R 1000', $models[0]->label);
    }

    public function test_get_models_returns_empty_when_model_facet_is_missing(): void
    {
        $facetsJson = '{"count":0,"facets":[]}';

        $mock = new MockHandler([
            new Response(200, [], $facetsJson),
        ]);

        $client = $this->createClient($mock);
        $models = $client->getModels(MobilityType::Car);

        $this->assertSame([], $models);
    }

    // --- Detail Parsing (still uses _next/data) ---

    public function test_parses_listing_detail(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $detailJson = $this->fixture('listing-detail.json');

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $detailJson),
        ]);

        $client = $this->createClient($mock);
        $detail = $client->getDetailBySlug('harley-davidson-flhxs-streetglide-special-flhx-55pz3zg', MobilityType::Motorcycle);

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
        $detail = $client->getDetailBySlug('test-slug', MobilityType::Motorcycle);

        $this->assertNotEmpty($detail->company->city);
        $this->assertNotNull($detail->company->latitude);
        $this->assertNotNull($detail->company->longitude);
    }

    public function test_get_detail_from_listing(): void
    {
        $searchJson = $this->fixture('search-results-api.json');
        $homepageHtml = $this->fixture('homepage.html');
        $detailJson = $this->fixture('listing-detail.json');

        $mock = new MockHandler([
            // Search via REST API (no homepage needed)
            new Response(200, [], $searchJson),
            // Detail via _next/data (needs homepage for build ID)
            new Response(200, [], $homepageHtml),
            new Response(200, [], $detailJson),
        ]);

        $client = $this->createClient($mock);
        $result = $client->search(new MotorcycleSearchCriteria);
        $detail = $client->getDetail($result->listings[0]);

        $this->assertNotEmpty($detail->id);
    }

    public function test_get_detail_by_url_parses_slug_and_mobility_type(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $detailJson = $this->fixture('listing-detail.json');

        $history = [];
        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $detailJson),
        ]);

        $client = $this->createClientWithHistory($mock, $history);
        $detail = $client->getDetailByUrl('https://www.viabovag.nl/auto/aanbod/volkswagen-golf-abc123?utm_source=test');

        $this->assertNotEmpty($detail->id);

        /** @var Request $detailRequest */
        $detailRequest = $history[1]['request'];
        $query = urldecode($detailRequest->getUri()->getQuery());

        $this->assertStringContainsString('mobilityType=auto', $query);
        $this->assertStringContainsString('vehicleUrl=volkswagen-golf-abc123', $query);
    }

    public function test_get_detail_by_url_throws_for_invalid_url(): void
    {
        $client = $this->createClient(new MockHandler);

        $this->expectException(ViaBOVAGException::class);
        $this->expectExceptionMessage('Invalid detail URL');

        $client->getDetailByUrl('https://www.viabovag.nl/onbekend/aanbod/test-slug');
    }

    // --- Stale Build ID Retry (detail pages only) ---

    public function test_retries_on_stale_build_id(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $detailJson = $this->fixture('listing-detail.json');

        // Build a homepage with a different build ID for the retry
        $newHomepageHtml = str_replace('PQS_ur-FpJe0R6JUyWiD2', 'NEW_BUILD_ID_12345', $homepageHtml);

        $mock = new MockHandler([
            // First: fetch homepage for build ID
            new Response(200, [], $homepageHtml),
            // Second: detail returns 404 (stale build ID)
            new Response(404, [], ''),
            // Third: re-fetch homepage for new build ID
            new Response(200, [], $newHomepageHtml),
            // Fourth: retry detail with new build ID
            new Response(200, [], $detailJson),
        ]);

        $client = $this->createClient($mock);
        $detail = $client->getDetailBySlug('test-slug', MobilityType::Motorcycle);

        $this->assertNotEmpty($detail->id);
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

        $client->getDetailBySlug('test-slug', MobilityType::Motorcycle);
    }

    // --- Cache (for build ID, used by detail pages) ---

    public function test_caches_build_id(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $detailJson = $this->fixture('listing-detail.json');

        $cache = new ArrayCache;

        $mock = new MockHandler([
            // First detail: fetch homepage + detail
            new Response(200, [], $homepageHtml),
            new Response(200, [], $detailJson),
            // Second detail: only detail (build ID from cache)
            new Response(200, [], $detailJson),
        ]);

        $client = $this->createClientWithCache($mock, $cache);

        // First call — fetches homepage for build ID
        $client->getDetailBySlug('test-slug', MobilityType::Motorcycle);

        $this->assertTrue($cache->has('viabovag:build-id'));

        // Second call — uses cached build ID
        $detail = $client->getDetailBySlug('test-slug-2', MobilityType::Motorcycle);

        $this->assertNotEmpty($detail->id);
    }

    public function test_reset_session_clears_cache(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $detailJson = $this->fixture('listing-detail.json');

        $cache = new ArrayCache;

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $detailJson),
            // After reset: fetch homepage again + detail
            new Response(200, [], $homepageHtml),
            new Response(200, [], $detailJson),
        ]);

        $client = $this->createClientWithCache($mock, $cache);

        $client->getDetailBySlug('test-slug', MobilityType::Motorcycle);
        $this->assertTrue($cache->has('viabovag:build-id'));

        $client->resetSession();
        $this->assertFalse($cache->has('viabovag:build-id'));

        // Next call re-fetches homepage
        $detail = $client->getDetailBySlug('test-slug', MobilityType::Motorcycle);
        $this->assertNotEmpty($detail->id);
    }

    // --- SearchCriteria Filter Slugs ---

    public function test_search_criteria_filter_slugs_brand_and_model(): void
    {
        $criteria = new MotorcycleSearchCriteria(
            brand: new Brand(slug: 'suzuki', label: 'Suzuki'),
            model: new Model(slug: 'gsx-r-1300-hayabusa', label: 'GSX-R 1300 Hayabusa'),
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
            fuelTypes: [MotorcycleFuelType::Petrol],
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

    // --- SearchCriteria Request Body ---

    public function test_search_criteria_request_body_defaults(): void
    {
        $criteria = new MotorcycleSearchCriteria;
        $body = $criteria->toRequestBody();

        $this->assertSame('motor', $body['MobilityType']);
        $this->assertTrue($body['InStock']);
        $this->assertTrue($body['ShowCommercialVehicles']);
        $this->assertTrue($body['HideVatExcludedPrices']);
        $this->assertArrayNotHasKey('PageNumber', $body);
        $this->assertArrayNotHasKey('Brand', $body);
    }

    public function test_search_criteria_request_body_with_filters(): void
    {
        $criteria = new MotorcycleSearchCriteria(
            brand: new Brand(slug: 'honda', label: 'Honda'),
            model: new Model(slug: 'cb-650-r', label: 'CB 650 R'),
            priceFrom: 3000,
            priceTo: 10000,
            bodyTypes: [MotorcycleBodyType::Naked, MotorcycleBodyType::Sport],
            fuelTypes: [MotorcycleFuelType::Petrol],
            sortOrder: SortOrder::PriceAscending,
            page: 2,
        );

        $body = $criteria->toRequestBody();

        $this->assertSame(['honda'], $body['Brand']);
        $this->assertSame('cb-650-r', $body['Model']);
        $this->assertSame(3000, $body['PriceFrom']);
        $this->assertSame(10000, $body['PriceTo']);
        $this->assertSame(['Naked', 'Sport'], $body['BodyType']);
        $this->assertSame(['Benzine'], $body['FuelType']);
        $this->assertSame('prijsOplopend', $body['SortOrder']);
        $this->assertSame(2, $body['PageNumber']);
    }

    public function test_search_criteria_request_body_uses_array_based_api_filters(): void
    {
        $criteria = new MotorcycleSearchCriteria(
            hasNapWeblabel: true,
            isImported: true,
            availableSince: AvailableSince::OneWeek,
            conditions: [Condition::New],
            warranties: [BovagWarranty::TwelveMonths],
            transmissions: [TransmissionType::Manual],
            driversLicenses: [DriversLicense::A],
        );

        $body = $criteria->toRequestBody();

        $this->assertSame(['Handgeschakeld'], $body['Transmission']);
        $this->assertSame(['A'], $body['DriversLicense']);
        $this->assertSame(['Nieuw'], $body['Condition']);
        $this->assertSame(['Bovag12maanden'], $body['Warranty']);
        $this->assertTrue($body['HasNapOrBit']);
        $this->assertArrayNotHasKey('HasNapWeblabel', $body);
        $this->assertSame(['Ja'], $body['Import']);
        $this->assertSame('OneWeek', $body['AvailableSince']);
    }

    public function test_search_criteria_request_body_formats_import_false_as_array(): void
    {
        $criteria = new CarSearchCriteria(isImported: false);
        $body = $criteria->toRequestBody();

        $this->assertSame(['Nee'], $body['Import']);
    }

    public function test_search_criteria_request_body_uses_has_nap_web_label_for_cars(): void
    {
        $criteria = new CarSearchCriteria(hasNapWeblabel: true);
        $body = $criteria->toRequestBody();

        $this->assertTrue($body['HasNapWeblabel']);
        $this->assertArrayNotHasKey('HasNapOrBit', $body);
    }

    public function test_search_criteria_request_body_normalizes_model_keywords_from_slug_style(): void
    {
        $criteria = new MotorcycleSearchCriteria(modelKeywords: 'mt-10-sp');
        $body = $criteria->toRequestBody();

        $this->assertSame('mt 10 sp', $body['ModelKeywords']);
    }

    public function test_search_criteria_request_body_supports_multi_select_filters(): void
    {
        $criteria = new MotorcycleSearchCriteria(
            conditions: [Condition::New, Condition::Used],
            warranties: [BovagWarranty::TwelveMonths, BovagWarranty::Manufacturer],
            transmissions: [TransmissionType::Manual, TransmissionType::Automatic],
            driversLicenses: [DriversLicense::A, DriversLicense::A2],
        );

        $body = $criteria->toRequestBody();

        $this->assertSame(['Nieuw', 'Occasion'], $body['Condition']);
        $this->assertSame(['Bovag12maanden', 'Fabrieksgarantie'], $body['Warranty']);
        $this->assertSame(['Handgeschakeld', 'Automatisch'], $body['Transmission']);
        $this->assertSame(['A', 'A2'], $body['DriversLicense']);
    }

    public function test_search_criteria_request_body_supports_motorcycle_accessories_and_performance_filters(): void
    {
        $criteria = new MotorcycleSearchCriteria(
            accelerationTo: 8,
            topSpeedFrom: 150,
            accessories: [
                new FilterOption(slug: 'cruisecontrol', label: 'Cruise Control'),
                new FilterOption(slug: 'buddyseat', label: 'Buddyseat'),
                new FilterOption(slug: 'cruisecontrol', label: 'Cruise Control'),
            ],
        );

        $body = $criteria->toRequestBody();

        $this->assertSame(['cruisecontrol', 'buddyseat'], $body['Accessory']);
        $this->assertSame(8, $body['AccelerationTo']);
        $this->assertSame(150, $body['TopSpeedFrom']);
    }

    public function test_search_criteria_request_body_supports_multi_select_transmission_for_car(): void
    {
        $criteria = new CarSearchCriteria(
            transmissions: [TransmissionType::Automatic, TransmissionType::Manual],
        );

        $body = $criteria->toRequestBody();

        $this->assertSame(['Automatisch', 'Handgeschakeld'], $body['Transmission']);
    }

    public function test_search_criteria_request_body_uses_specified_battery_range_for_car(): void
    {
        $criteria = new CarSearchCriteria(
            cities: [
                new FilterOption(slug: 'amsterdam', label: 'Amsterdam'),
                new FilterOption(slug: 'utrecht', label: 'Utrecht'),
            ],
            specifiedBatteryRange: new FilterOption(slug: '300-400', label: '300-400 km'),
            energyLabels: [
                new FilterOption(slug: 'A', label: 'A'),
                new FilterOption(slug: 'B', label: 'B'),
            ],
        );

        $body = $criteria->toRequestBody();

        $this->assertSame(['amsterdam', 'utrecht'], $body['City']);
        $this->assertSame(['A', 'B'], $body['EnergyLabel']);
        $this->assertSame('300-400', $body['SpecifiedBatteryRange']);
    }

    public function test_search_criteria_request_body_uses_api_tokens_for_gear_cylinder_and_seat_counts(): void
    {
        $criteria = new CarSearchCriteria(
            gearCounts: [GearCount::Five, GearCount::Eight],
            cylinderCounts: [CylinderCount::Four, CylinderCount::Ten],
            seatCounts: [SeatCount::Five],
        );

        $body = $criteria->toRequestBody();

        $this->assertSame(['OneToFive', 'EightOrMore'], $body['GearCount']);
        $this->assertSame(['Four', 'TenOrMore'], $body['CylinderCount']);
        $this->assertSame(['Five'], $body['SeatCount']);
    }

    public function test_search_criteria_request_body_formats_single_energy_label_as_array(): void
    {
        $criteria = new CarSearchCriteria(
            energyLabels: [new FilterOption(slug: 'A', label: 'A')],
        );

        $body = $criteria->toRequestBody();

        $this->assertSame(['A'], $body['EnergyLabel']);
    }

    public function test_search_criteria_request_body_formats_single_specified_battery_range_as_string(): void
    {
        $criteria = new CarSearchCriteria(
            specifiedBatteryRange: new FilterOption(slug: '300', label: '300 km'),
        );

        $body = $criteria->toRequestBody();

        $this->assertSame('300', $body['SpecifiedBatteryRange']);
    }

    public function test_search_criteria_request_body_uses_specified_battery_range_for_bicycle(): void
    {
        $criteria = new BicycleSearchCriteria(
            frameMaterials: [
                new FilterOption(slug: 'aluminium', label: 'Aluminium'),
                new FilterOption(slug: 'carbon', label: 'Carbon'),
            ],
            brakeTypes: [
                new FilterOption(slug: 'schijfrem', label: 'Schijfrem'),
                new FilterOption(slug: 'velgrem', label: 'Velgrem'),
            ],
            engineBrands: [
                new FilterOption(slug: 'bosch', label: 'Bosch'),
                new FilterOption(slug: 'shimano', label: 'Shimano'),
            ],
            specifiedBatteryRange: new FilterOption(slug: '80-100', label: '80-100 km'),
        );

        $body = $criteria->toRequestBody();

        $this->assertSame(['aluminium', 'carbon'], $body['FrameMaterial']);
        $this->assertSame(['schijfrem', 'velgrem'], $body['BrakeType']);
        $this->assertSame(['bosch', 'shimano'], $body['EngineBrand']);
        $this->assertSame('80-100', $body['SpecifiedBatteryRange']);
    }

    public function test_search_criteria_request_body_supports_multi_select_filter_options_for_camper(): void
    {
        $criteria = new CamperSearchCriteria(
            bedLayouts: [
                new FilterOption(slug: 'dwarsbed', label: 'Dwarsbed'),
                new FilterOption(slug: 'frans-bed', label: 'Frans bed'),
            ],
            seatingLayouts: [
                new FilterOption(slug: 'halfrond', label: 'Halfrond'),
                new FilterOption(slug: 'treinzit', label: 'Treinzit'),
            ],
            sanitaryLayouts: [
                new FilterOption(slug: 'douche', label: 'Douche'),
                new FilterOption(slug: 'toilet', label: 'Toilet'),
            ],
            kitchenLayouts: [
                new FilterOption(slug: 'l-vormig', label: 'L-vormig'),
                new FilterOption(slug: 'hoek', label: 'Hoek'),
            ],
            camperChassisBrands: [
                new FilterOption(slug: 'fiat', label: 'Fiat'),
                new FilterOption(slug: 'mercedes', label: 'Mercedes'),
            ],
        );

        $body = $criteria->toRequestBody();

        $this->assertSame(['dwarsbed', 'frans-bed'], $body['BedLayout']);
        $this->assertSame(['halfrond', 'treinzit'], $body['SeatingLayout']);
        $this->assertSame(['douche', 'toilet'], $body['SanitaryLayout']);
        $this->assertSame(['l-vormig', 'hoek'], $body['KitchenLayout']);
        $this->assertSame(['fiat', 'mercedes'], $body['CamperChassisBrand']);
    }

    public function test_search_criteria_request_body_formats_engine_power_range_as_engine_power_tokens_for_car(): void
    {
        $criteria = new CarSearchCriteria(
            enginePowerFrom: 100,
            enginePowerTo: 150,
        );

        $body = $criteria->toRequestBody();

        $this->assertSame('EnginePower100', $body['EnginePowerFrom']);
        $this->assertSame('EnginePower150', $body['EnginePowerTo']);
    }

    public function test_search_criteria_request_body_formats_engine_power_range_as_engine_power_tokens_for_motorcycle(): void
    {
        $criteria = new MotorcycleSearchCriteria(
            enginePowerFrom: 75,
            enginePowerTo: 125,
        );

        $body = $criteria->toRequestBody();

        $this->assertSame('EnginePower75', $body['EnginePowerFrom']);
        $this->assertSame('EnginePower125', $body['EnginePowerTo']);
    }

    public function test_search_criteria_request_body_rounds_engine_power_to_supported_buckets(): void
    {
        $criteria = new CarSearchCriteria(
            enginePowerFrom: 116,
            enginePowerTo: 116,
        );

        $body = $criteria->toRequestBody();

        $this->assertSame('EnginePower125', $body['EnginePowerFrom']);
        $this->assertSame('EnginePower110', $body['EnginePowerTo']);
    }

    public function test_search_criteria_request_body_car_mobility_type(): void
    {
        $criteria = new CarSearchCriteria;
        $body = $criteria->toRequestBody();

        $this->assertSame('auto', $body['MobilityType']);
    }

    public function test_search_criteria_request_body_bicycle_mobility_type(): void
    {
        $criteria = new BicycleSearchCriteria;
        $body = $criteria->toRequestBody();

        $this->assertSame('fiets', $body['MobilityType']);
    }

    // --- Error Handling ---

    public function test_throws_on_invalid_json_response(): void
    {
        $mock = new MockHandler([
            new Response(200, [], 'not valid json'),
        ]);

        $client = $this->createClient($mock);

        $this->expectException(ViaBOVAGException::class);
        $this->expectExceptionMessage('Failed to decode JSON');

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

        $client->getDetailBySlug('test-slug', MobilityType::Motorcycle);
    }

    public function test_throws_on_unexpected_status_code(): void
    {
        $mock = new MockHandler([
            new Response(500, [], 'Internal Server Error'),
        ]);

        $client = $this->createClient($mock);

        $this->expectException(ViaBOVAGException::class);
        $this->expectExceptionMessage('HTTP 500');

        $client->search(new MotorcycleSearchCriteria);
    }

    public function test_throws_on_invalid_page_number_in_custom_search_query(): void
    {
        $client = $this->createClient(new MockHandler([]));

        $query = new class implements SearchQuery
        {
            public function mobilityType(): MobilityType
            {
                return MobilityType::Car;
            }

            public function toFilterSlugs(): array
            {
                return [];
            }

            /**
             * @return array<string, mixed>
             */
            public function toRequestBody(): array
            {
                return ['MobilityType' => 'auto'];
            }

            public function page(): int
            {
                return 0;
            }

            public function withPage(int $page): static
            {
                return $this;
            }
        };

        $this->expectException(ViaBOVAGException::class);
        $this->expectExceptionMessage('Search page must be greater than or equal to 1.');

        $client->search($query);
    }

    public function test_mobility_type_search_and_detail_slugs(): void
    {
        $this->assertSame('motoren', MobilityType::Motorcycle->searchSlug());
        $this->assertSame('motor', MobilityType::Motorcycle->detailSlug());
        $this->assertSame('auto', MobilityType::Car->searchSlug());
        $this->assertSame('auto', MobilityType::Car->detailSlug());
        $this->assertSame('fietsen', MobilityType::Bicycle->searchSlug());
        $this->assertSame('fiets', MobilityType::Bicycle->detailSlug());
        $this->assertSame('camper', MobilityType::Camper->searchSlug());
        $this->assertSame('camper', MobilityType::Camper->detailSlug());

        $this->assertSame(MobilityType::Car, MobilityType::fromApiValue('auto'));
        $this->assertSame(MobilityType::Bicycle, MobilityType::fromApiValue('fiets'));
        $this->assertNull(MobilityType::fromApiValue('unknown-type'));
    }

    // --- Listing priceExcludesVat ---

    public function test_parses_listing_price_excludes_vat(): void
    {
        $searchJson = $this->fixture('search-results-api.json');

        $mock = new MockHandler([
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClient($mock);
        $result = $client->search(new MotorcycleSearchCriteria);

        // The fixture has priceExcludesVat = false on all listings
        $this->assertFalse($result->listings[0]->priceExcludesVat);
    }

    // --- Detail: New ListingDetail fields ---

    public function test_parses_detail_price_excludes_vat(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $detailJson = $this->fixture('listing-detail.json');

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $detailJson),
        ]);

        $client = $this->createClient($mock);
        $detail = $client->getDetailBySlug('test-slug', MobilityType::Motorcycle);

        $this->assertFalse($detail->priceExcludesVat);
    }

    public function test_parses_detail_url_and_mobility_type(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $detailJson = $this->fixture('listing-detail.json');

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $detailJson),
        ]);

        $client = $this->createClient($mock);
        $detail = $client->getDetailBySlug('test-slug', MobilityType::Motorcycle);

        $this->assertNotNull($detail->url);
        $this->assertNotNull($detail->mobilityType);
    }

    public function test_parses_detail_financial_data(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $detailJson = $this->fixture('listing-detail.json');

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $detailJson),
        ]);

        $client = $this->createClient($mock);
        $detail = $client->getDetailBySlug('test-slug', MobilityType::Motorcycle);

        // roadTax is present in fixture (€ 13,-)
        $this->assertSame(13, $detail->roadTax);
        // bijtellingPercentage is "0 %" in fixture
        $this->assertNotNull($detail->bijtellingPercentage);
    }

    public function test_parses_detail_return_warranty_mileage(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $detailJson = $this->fixture('listing-detail.json');

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $detailJson),
        ]);

        $client = $this->createClient($mock);
        $detail = $client->getDetailBySlug('test-slug', MobilityType::Motorcycle);

        $this->assertSame(500, $detail->returnWarrantyMileage);
    }

    // --- Detail: Extended Company fields ---

    public function test_parses_detail_company_extended_fields(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $detailJson = $this->fixture('listing-detail.json');

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $detailJson),
        ]);

        $client = $this->createClient($mock);
        $detail = $client->getDetailBySlug('test-slug', MobilityType::Motorcycle);

        $this->assertSame(5634, $detail->company->id);
        $this->assertSame('26', $detail->company->houseNumber);
        $this->assertSame('nl', $detail->company->countryCode);
        $this->assertSame('google', $detail->company->reviewProvider);
        $this->assertFalse($detail->company->isOpenNow);
    }

    // --- Detail: Extended Vehicle fields ---

    public function test_parses_detail_vehicle_extended_fields(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $detailJson = $this->fixture('listing-detail.json');

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $detailJson),
        ]);

        $client = $this->createClient($mock);
        $detail = $client->getDetailBySlug('test-slug', MobilityType::Motorcycle);

        // condition is "occasion" in the fixture
        $this->assertSame('occasion', $detail->vehicle->condition);
        // modelYear is "2015" in the fixture
        $this->assertSame(2015, $detail->vehicle->modelYear);
        // isHybridVehicle is false in the fixture
        $this->assertFalse($detail->vehicle->isHybridVehicle);
        // hasNapLabel is false in the fixture
        $this->assertFalse($detail->vehicle->hasNapLabel);
    }

    public function test_parses_detail_vehicle_bovag_warranty_from_certainty_keys(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $detailJson = $this->fixture('listing-detail.json');

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $detailJson),
        ]);

        $client = $this->createClient($mock);
        $detail = $client->getDetailBySlug('test-slug', MobilityType::Motorcycle);

        // The fixture has BovagWarranty12Months in certaintyKeys
        $this->assertSame('TwaalfMaanden', $detail->vehicle->bovagWarranty);
        $this->assertNotEmpty($detail->vehicle->warranties);
        $this->assertContains('bovag12maanden', $detail->vehicle->warranties);
    }

    // --- Detail: SpecificationGroup extended fields ---

    public function test_parses_detail_specification_group_extended_fields(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $detailJson = $this->fixture('listing-detail.json');

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $detailJson),
        ]);

        $client = $this->createClient($mock);
        $detail = $client->getDetailBySlug('test-slug', MobilityType::Motorcycle);

        $firstGroup = $detail->specificationGroups[0];
        $this->assertNotNull($firstGroup->group);
        $this->assertNotNull($firstGroup->iconName);
    }

    // --- Detail: Specification hasValue field ---

    public function test_parses_detail_specification_has_value(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $detailJson = $this->fixture('listing-detail.json');

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $detailJson),
        ]);

        $client = $this->createClient($mock);
        $detail = $client->getDetailBySlug('test-slug', MobilityType::Motorcycle);

        $firstSpec = $detail->specificationGroups[0]->specifications[0];
        $this->assertIsBool($firstSpec->hasValue);
    }

    // --- No Automatic Retry ---

    public function test_search_does_not_retry_on_429_response(): void
    {
        $searchJson = $this->fixture('search-results-api.json');

        $history = [];
        $mock = new MockHandler([
            new Response(429, [], ''),
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClientWithHistory($mock, $history);

        try {
            $client->search(new MotorcycleSearchCriteria);
            $this->fail('Expected ViaBOVAGException was not thrown');
        } catch (ViaBOVAGException $viabovagException) {
            $this->assertStringContainsString('HTTP 429', $viabovagException->getMessage());
        }

        $this->assertCount(1, $history);
    }

    // --- searchAll (pagination iterator) ---

    public function test_search_all_yields_listings_from_single_page(): void
    {
        $searchJson = $this->buildSearchJson(listingCount: 3, totalCount: 3);

        $mock = new MockHandler([
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClient($mock);
        $listings = iterator_to_array($client->searchAll(new MotorcycleSearchCriteria));

        $this->assertCount(3, $listings);
    }

    public function test_search_all_iterates_multiple_pages(): void
    {
        $page1Json = $this->buildSearchJson(listingCount: 24, totalCount: 30);
        $page2Json = $this->buildSearchJson(listingCount: 6, totalCount: 30);

        $mock = new MockHandler([
            new Response(200, [], $page1Json),
            new Response(200, [], $page2Json),
        ]);

        $client = $this->createClient($mock);
        $listings = iterator_to_array($client->searchAll(new MotorcycleSearchCriteria));

        $this->assertCount(30, $listings);
    }

    public function test_search_all_yields_nothing_for_empty_results(): void
    {
        $searchJson = $this->buildSearchJson(listingCount: 0, totalCount: 0);

        $mock = new MockHandler([
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClient($mock);
        $listings = iterator_to_array($client->searchAll(new MotorcycleSearchCriteria));

        $this->assertCount(0, $listings);
    }

    public function test_search_all_propagates_error_on_second_page(): void
    {
        $page1Json = $this->buildSearchJson(listingCount: 24, totalCount: 48);

        $mock = new MockHandler([
            new Response(200, [], $page1Json),
            // Second page returns 500
            new Response(500, [], 'Internal Server Error'),
        ]);

        $client = $this->createClient($mock);
        $generator = $client->searchAll(new MotorcycleSearchCriteria);

        // First page yields 24 listings successfully
        $collected = [];
        try {
            foreach ($generator as $listing) {
                $collected[] = $listing;
            }

            $this->fail('Expected ViaBOVAGException was not thrown');
        } catch (ViaBOVAGException $viabovagException) {
            $this->assertStringContainsString('HTTP 500', $viabovagException->getMessage());
        }

        // Should have collected the first page before the error
        $this->assertCount(24, $collected);
    }

    public function test_search_all_propagates_network_error(): void
    {
        $mock = new MockHandler([
            new ConnectException(
                'Connection timed out',
                new Request('POST', 'https://www.viabovag.nl/api/client/search/results'),
            ),
        ]);

        $client = $this->createClient($mock);

        $this->expectException(ViaBOVAGException::class);
        $this->expectExceptionMessage('HTTP request failed');

        // Force the generator to execute by iterating it
        iterator_to_array($client->searchAll(new MotorcycleSearchCriteria));
    }

    // --- REST API Request Construction ---

    public function test_search_posts_to_rest_api_endpoint(): void
    {
        $searchJson = $this->fixture('search-results-api.json');

        $history = [];
        $mock = new MockHandler([
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClientWithHistory($mock, $history);
        $client->search(new MotorcycleSearchCriteria);

        $this->assertCount(1, $history);

        /** @var Request $searchRequest */
        $searchRequest = $history[0]['request'];

        $this->assertSame('POST', $searchRequest->getMethod());
        $this->assertSame('/api/client/search/results', $searchRequest->getUri()->getPath());
        $this->assertSame('application/json', $searchRequest->getHeaderLine('Content-Type'));
        $this->assertSame('application/json', $searchRequest->getHeaderLine('Accept'));
        $this->assertSame('https://www.viabovag.nl', $searchRequest->getHeaderLine('Origin'));
    }

    public function test_search_request_body_contains_mobility_type(): void
    {
        $searchJson = $this->fixture('search-results-api.json');

        $history = [];
        $mock = new MockHandler([
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClientWithHistory($mock, $history);
        $client->search(new MotorcycleSearchCriteria);

        /** @var Request $searchRequest */
        $searchRequest = $history[0]['request'];
        $body = json_decode((string) $searchRequest->getBody(), true);

        $this->assertSame('motor', $body['MobilityType']);
        $this->assertTrue($body['InStock']);
    }

    public function test_search_request_body_contains_filters(): void
    {
        $searchJson = $this->fixture('search-results-api.json');

        $history = [];
        $mock = new MockHandler([
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClientWithHistory($mock, $history);
        $client->search(new MotorcycleSearchCriteria(
            brand: new Brand(slug: 'honda', label: 'Honda'),
            priceFrom: 3000,
            priceTo: 10000,
        ));

        /** @var Request $searchRequest */
        $searchRequest = $history[0]['request'];
        $body = json_decode((string) $searchRequest->getBody(), true);

        $this->assertSame(['honda'], $body['Brand']);
        $this->assertSame(3000, $body['PriceFrom']);
        $this->assertSame(10000, $body['PriceTo']);
    }

    public function test_search_request_body_omits_optional_filters_when_empty(): void
    {
        $searchJson = $this->fixture('search-results-api.json');

        $history = [];
        $mock = new MockHandler([
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClientWithHistory($mock, $history);
        $client->search(new MotorcycleSearchCriteria);

        /** @var Request $searchRequest */
        $searchRequest = $history[0]['request'];
        $body = json_decode((string) $searchRequest->getBody(), true);

        $this->assertArrayNotHasKey('Brand', $body);
        $this->assertArrayNotHasKey('PriceFrom', $body);
        $this->assertArrayNotHasKey('SortOrder', $body);
        $this->assertArrayNotHasKey('PageNumber', $body);
    }

    public function test_search_request_body_contains_sort_order(): void
    {
        $searchJson = $this->fixture('search-results-api.json');

        $history = [];
        $mock = new MockHandler([
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClientWithHistory($mock, $history);
        $client->search(new MotorcycleSearchCriteria(
            sortOrder: SortOrder::PriceAscending,
        ));

        /** @var Request $searchRequest */
        $searchRequest = $history[0]['request'];
        $body = json_decode((string) $searchRequest->getBody(), true);

        $this->assertSame('prijsOplopend', $body['SortOrder']);
    }

    public function test_search_request_body_includes_page_number_for_page_2(): void
    {
        $searchJson = $this->fixture('search-results-api.json');

        $history = [];
        $mock = new MockHandler([
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClientWithHistory($mock, $history);
        $client->search(new MotorcycleSearchCriteria(page: 3));

        /** @var Request $searchRequest */
        $searchRequest = $history[0]['request'];
        $body = json_decode((string) $searchRequest->getBody(), true);

        $this->assertSame(3, $body['PageNumber']);
    }

    public function test_search_request_body_omits_page_number_for_page_1(): void
    {
        $searchJson = $this->fixture('search-results-api.json');

        $history = [];
        $mock = new MockHandler([
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClientWithHistory($mock, $history);
        $client->search(new MotorcycleSearchCriteria(page: 1));

        /** @var Request $searchRequest */
        $searchRequest = $history[0]['request'];
        $body = json_decode((string) $searchRequest->getBody(), true);

        $this->assertArrayNotHasKey('PageNumber', $body);
    }

    public function test_search_request_for_car_uses_auto_mobility_type(): void
    {
        $searchJson = $this->buildSearchJson(listingCount: 1, totalCount: 1, mobilityType: 'auto');

        $history = [];
        $mock = new MockHandler([
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClientWithHistory($mock, $history);
        $client->search(new CarSearchCriteria);

        /** @var Request $searchRequest */
        $searchRequest = $history[0]['request'];
        $body = json_decode((string) $searchRequest->getBody(), true);

        $this->assertSame('auto', $body['MobilityType']);
    }

    // --- Detail URL Construction (still uses _next/data) ---

    public function test_detail_url_contains_correct_mobility_type_and_slug(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $detailJson = $this->fixture('listing-detail.json');

        $history = [];
        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $detailJson),
        ]);

        $client = $this->createClientWithHistory($mock, $history);
        $client->getDetailBySlug('harley-davidson-test-slug', MobilityType::Motorcycle);

        /** @var Request $detailRequest */
        $detailRequest = $history[1]['request'];
        $query = urldecode($detailRequest->getUri()->getQuery());
        $path = $detailRequest->getUri()->getPath();

        $this->assertSame('GET', $detailRequest->getMethod());
        $this->assertStringEndsWith('/nl-NL/vdp.json', $path);
        $this->assertStringContainsString('mobilityType=motor', $query);
        $this->assertStringContainsString('vehicleUrl=harley-davidson-test-slug', $query);
    }

    public function test_detail_url_uses_car_detail_slug(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $detailJson = $this->fixture('listing-detail.json');

        $history = [];
        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $detailJson),
        ]);

        $client = $this->createClientWithHistory($mock, $history);
        $client->getDetailBySlug('volkswagen-golf-test', MobilityType::Car);

        /** @var Request $detailRequest */
        $detailRequest = $history[1]['request'];
        $query = urldecode($detailRequest->getUri()->getQuery());

        $this->assertStringContainsString('mobilityType=auto', $query);
        $this->assertStringContainsString('vehicleUrl=volkswagen-golf-test', $query);
    }

    public function test_detail_url_contains_build_id(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $detailJson = $this->fixture('listing-detail.json');

        $history = [];
        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $detailJson),
        ]);

        $client = $this->createClientWithHistory($mock, $history);
        $client->getDetailBySlug('test-slug', MobilityType::Motorcycle);

        /** @var Request $detailRequest */
        $detailRequest = $history[1]['request'];
        $path = $detailRequest->getUri()->getPath();

        // The build ID from homepage.html fixture is PQS_ur-FpJe0R6JUyWiD2
        $this->assertStringContainsString('/_next/data/PQS_ur-FpJe0R6JUyWiD2/', $path);
    }

    public function test_detail_request_includes_nextjs_data_header(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $detailJson = $this->fixture('listing-detail.json');

        $history = [];
        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $detailJson),
        ]);

        $client = $this->createClientWithHistory($mock, $history);
        $client->getDetailBySlug('test-slug', MobilityType::Motorcycle);

        /** @var Request $detailRequest */
        $detailRequest = $history[1]['request'];

        $this->assertSame('1', $detailRequest->getHeaderLine('x-nextjs-data'));
    }

    // --- HTTP Network Error Handling ---

    public function test_throws_on_connection_exception_during_search(): void
    {
        $mock = new MockHandler([
            new ConnectException(
                'Connection timed out',
                new Request('POST', 'https://www.viabovag.nl/api/client/search/results'),
            ),
        ]);

        $client = $this->createClient($mock);

        $this->expectException(ViaBOVAGException::class);
        $this->expectExceptionMessage('HTTP request failed');

        $client->search(new MotorcycleSearchCriteria);
    }

    public function test_throws_on_connection_exception_during_homepage_fetch(): void
    {
        $mock = new MockHandler([
            new ConnectException(
                'DNS resolution failed',
                new Request('GET', 'https://www.viabovag.nl'),
            ),
        ]);

        $client = $this->createClient($mock);

        $this->expectException(ViaBOVAGException::class);
        $this->expectExceptionMessage('Failed to fetch homepage for build ID');

        $client->getDetailBySlug('test-slug', MobilityType::Motorcycle);
    }

    public function test_throws_on_non_200_homepage_response(): void
    {
        $mock = new MockHandler([
            new Response(503, [], 'Service Unavailable'),
        ]);

        $client = $this->createClient($mock);

        $this->expectException(ViaBOVAGException::class);
        $this->expectExceptionMessage('Failed to fetch homepage: HTTP 503');

        $client->getDetailBySlug('test-slug', MobilityType::Motorcycle);
    }

    public function test_throws_on_connection_exception_during_detail_fetch(): void
    {
        $homepageHtml = $this->fixture('homepage.html');

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new ConnectException(
                'Connection refused',
                new Request('GET', 'https://www.viabovag.nl/_next/data/test/nl-NL/vdp.json'),
            ),
        ]);

        $client = $this->createClient($mock);

        $this->expectException(ViaBOVAGException::class);
        $this->expectExceptionMessage('HTTP request failed');

        $client->getDetailBySlug('test-slug', MobilityType::Motorcycle);
    }

    // --- Facets REST API Request Construction ---

    public function test_facets_posts_to_rest_api_endpoint(): void
    {
        $facetsJson = $this->fixture('search-facets-api.json');

        $history = [];
        $mock = new MockHandler([
            new Response(200, [], $facetsJson),
        ]);

        $client = $this->createClientWithHistory($mock, $history);
        $client->getBrands(MobilityType::Motorcycle);

        $this->assertCount(1, $history);

        /** @var Request $facetsRequest */
        $facetsRequest = $history[0]['request'];

        $this->assertSame('POST', $facetsRequest->getMethod());
        $this->assertSame('/api/client/search/facets', $facetsRequest->getUri()->getPath());
        $this->assertSame('application/json', $facetsRequest->getHeaderLine('Content-Type'));
    }

    // --- Search REST API Server Filtering ---

    public function test_search_uses_server_results_for_financeable_filter(): void
    {
        $searchJson = $this->buildSearchJsonFromListings([
            [
                'id' => 'financeable-yes',
                'isFinanceable' => true,
            ],
            [
                'id' => 'financeable-no',
                'isFinanceable' => false,
            ],
        ]);

        $mock = new MockHandler([
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClient($mock);
        $result = $client->search(new MotorcycleSearchCriteria(isFinanceable: true));

        $this->assertCount(2, $result->listings);
        $this->assertSame('financeable-yes', $result->listings[0]->id);
        $this->assertSame('financeable-no', $result->listings[1]->id);
    }

    public function test_search_uses_server_results_for_import_odometer_check_filter(): void
    {
        $searchJson = $this->buildSearchJsonFromListings([
            [
                'id' => 'import-check-yes',
                'vehicle' => [
                    'certaintyKeys' => ['HasBovagImportOdometerCheck'],
                ],
            ],
            [
                'id' => 'import-check-no',
                'vehicle' => [
                    'certaintyKeys' => [],
                ],
            ],
        ]);

        $mock = new MockHandler([
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClient($mock);
        $result = $client->search(new MotorcycleSearchCriteria(hasBovagImportOdometerCheck: true));

        $this->assertCount(2, $result->listings);
        $this->assertSame('import-check-yes', $result->listings[0]->id);
        $this->assertSame('import-check-no', $result->listings[1]->id);
    }

    public function test_search_uses_server_order_for_mileage_sort(): void
    {
        $searchJson = $this->buildSearchJsonFromListings([
            [
                'id' => 'mileage-high',
                'vehicle' => ['mileage' => 5000],
            ],
            [
                'id' => 'mileage-medium',
                'vehicle' => ['mileage' => 1500],
            ],
            [
                'id' => 'mileage-low',
                'vehicle' => ['mileage' => 0],
            ],
        ]);

        $mock = new MockHandler([
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClient($mock);
        $result = $client->search(new MotorcycleSearchCriteria(sortOrder: SortOrder::MileageAscending));

        $mileage = array_map(
            fn (Listing $listing): int => $listing->vehicle->mileage,
            $result->listings,
        );

        $this->assertSame([5000, 1500, 0], $mileage);
    }

    public function test_search_uses_server_results_for_city_multi_select_filter(): void
    {
        $searchJson = $this->buildSearchJsonFromListings([
            ['id' => 'city-amsterdam', 'company' => ['city' => 'Amsterdam']],
            ['id' => 'city-utrecht', 'company' => ['city' => 'Utrecht']],
            ['id' => 'city-rotterdam', 'company' => ['city' => 'Rotterdam']],
        ], totalCount: 3);

        $mock = new MockHandler([
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClient($mock);
        $result = $client->search(new CarSearchCriteria(cities: [
            new FilterOption(slug: 'amsterdam', label: 'Amsterdam'),
            new FilterOption(slug: 'utrecht', label: 'Utrecht'),
        ]));

        $this->assertCount(3, $result->listings);
        $this->assertContains('city-amsterdam', array_map(fn (Listing $listing): string => $listing->id, $result->listings));
        $this->assertContains('city-utrecht', array_map(fn (Listing $listing): string => $listing->id, $result->listings));
        $this->assertContains('city-rotterdam', array_map(fn (Listing $listing): string => $listing->id, $result->listings));
    }

    public function test_search_uses_server_results_for_has_nap_weblabel_filter(): void
    {
        $searchJson = $this->buildSearchJsonFromListings([
            ['id' => 'nap-yes', 'vehicle' => ['certaintyKeys' => ['HasNapWeblabel']]],
            ['id' => 'nap-no', 'vehicle' => ['certaintyKeys' => []]],
        ], totalCount: 2);

        $mock = new MockHandler([
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClient($mock);
        $result = $client->search(new CarSearchCriteria(hasNapWeblabel: true));

        $this->assertCount(2, $result->listings);
        $this->assertSame('nap-yes', $result->listings[0]->id);
        $this->assertSame('nap-no', $result->listings[1]->id);
    }

    public function test_search_uses_single_server_request_for_specified_battery_range(): void
    {
        $range300Json = $this->buildSearchJsonFromListings([
            ['id' => 'range-300'],
        ], totalCount: 1);

        $range400Json = $this->buildSearchJsonFromListings([
            ['id' => 'range-400'],
        ], totalCount: 1);

        $history = [];
        $mock = new MockHandler([
            new Response(200, [], $range300Json),
            new Response(200, [], $range400Json),
        ]);

        $client = $this->createClientWithHistory($mock, $history);
        $result = $client->search(new CarSearchCriteria(
            specifiedBatteryRange: new FilterOption(slug: '300', label: '300 km'),
        ));

        $this->assertCount(1, $result->listings);
        $this->assertContains('range-300', array_map(fn (Listing $listing): string => $listing->id, $result->listings));

        $this->assertCount(1, $history);

        /** @var Request $request */
        $request = $history[0]['request'];
        $body = json_decode((string) $request->getBody(), true);

        $this->assertSame('300', $body['SpecifiedBatteryRange']);
    }

    public function test_search_does_not_retry_with_sanitized_colors_when_api_rejects_color_filter(): void
    {
        $searchJson = $this->buildSearchJsonFromListings([
            [
                'id' => 'black-bike',
                'vehicle' => ['color' => 'zwart'],
            ],
        ]);

        $history = [];
        $mock = new MockHandler([
            new Response(500, [], ''),
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClientWithHistory($mock, $history);

        try {
            $client->search(new MotorcycleSearchCriteria(colors: ['Roze', 'Zwart']));
            $this->fail('Expected ViaBOVAGException was not thrown');
        } catch (ViaBOVAGException $viabovagException) {
            $this->assertStringContainsString('HTTP 500', $viabovagException->getMessage());
        }

        $this->assertCount(1, $history);
    }

    public function test_search_uses_server_results_for_not_imported_filter(): void
    {
        $allListingsJson = $this->buildSearchJsonFromListings([
            ['id' => 'import-no'],
            ['id' => 'import-yes'],
        ], totalCount: 2);

        $importedOnlyJson = $this->buildSearchJsonFromListings([
            ['id' => 'import-yes'],
        ], totalCount: 1);

        $mock = new MockHandler([
            new Response(200, [], $allListingsJson),
            new Response(200, [], $importedOnlyJson),
        ]);

        $history = [];
        $client = $this->createClientWithHistory($mock, $history);
        $result = $client->search(new MotorcycleSearchCriteria(isImported: false));

        $this->assertCount(2, $result->listings);
        $this->assertSame('import-no', $result->listings[0]->id);
        $this->assertSame('import-yes', $result->listings[1]->id);
        $this->assertSame(2, $result->totalCount);
        $this->assertCount(1, $history);
    }

    public function test_search_uses_server_results_for_mileage_range_filter(): void
    {
        $withoutMileageFilterJson = $this->buildSearchJsonFromListings([
            ['id' => 'mileage-1500', 'vehicle' => ['mileage' => 1500]],
            ['id' => 'mileage-0', 'vehicle' => ['mileage' => 0]],
            ['id' => 'mileage-9999', 'vehicle' => ['mileage' => 9999]],
        ], totalCount: 3);

        $mock = new MockHandler([
            new Response(200, [], $withoutMileageFilterJson),
        ]);

        $client = $this->createClient($mock);
        $result = $client->search(new MotorcycleSearchCriteria(mileageTo: 2000));

        $this->assertCount(3, $result->listings);
        $this->assertContains('mileage-1500', array_map(fn (Listing $listing): string => $listing->id, $result->listings));
        $this->assertContains('mileage-0', array_map(fn (Listing $listing): string => $listing->id, $result->listings));
        $this->assertContains('mileage-9999', array_map(fn (Listing $listing): string => $listing->id, $result->listings));
    }

    /**
     * Build a minimal search JSON response in REST API format.
     */
    private function buildSearchJson(int $listingCount, int $totalCount, string $mobilityType = 'motor'): string
    {
        $listings = [];
        for ($i = 0; $i < $listingCount; $i++) {
            $listings[] = [
                'id' => 'listing-'.$i.'-'.uniqid(),
                'mobilityType' => $mobilityType,
                'url' => '/'.$mobilityType.'en/test-'.$i,
                'friendlyUriPart' => 'test-'.$i,
                'title' => 'Test Listing '.$i,
                'price' => 5000 + ($i * 100),
                'isFinanceable' => false,
                'priceExcludesVat' => false,
                'vehicle' => [
                    'type' => $mobilityType,
                    'brand' => 'Test',
                    'model' => 'Model '.$i,
                    'mileage' => 10000,
                    'mileageUnit' => 'kilometer',
                    'year' => 2020,
                    'fuelTypes' => ['Benzine'],
                    'warranties' => [],
                    'certaintyKeys' => [],
                    'fullyServiced' => false,
                    'hasBovagChecklist' => false,
                    'hasReturnWarranty' => false,
                    'servicedOnDelivery' => false,
                ],
                'company' => [
                    'name' => 'Test Dealer',
                ],
            ];
        }

        return json_encode([
            'results' => $listings,
            'count' => $totalCount,
        ]);
    }

    /**
     * Build REST API search JSON from per-listing overrides.
     *
     * @param  array<int, array<string, mixed>>  $listingOverrides
     */
    private function buildSearchJsonFromListings(array $listingOverrides, ?int $totalCount = null): string
    {
        $baseListing = [
            'id' => 'listing-default',
            'mobilityType' => 'motor',
            'url' => '/motoren/test',
            'friendlyUriPart' => 'test',
            'title' => 'Test Listing',
            'price' => 5000,
            'isFinanceable' => false,
            'priceExcludesVat' => false,
            'vehicle' => [
                'type' => 'motor',
                'brand' => 'Test',
                'model' => 'Model',
                'mileage' => 10000,
                'mileageUnit' => 'kilometer',
                'year' => 2020,
                'fuelTypes' => ['Benzine'],
                'warranties' => [],
                'certaintyKeys' => [],
                'fullyServiced' => false,
                'hasBovagChecklist' => false,
                'hasReturnWarranty' => false,
                'servicedOnDelivery' => false,
            ],
            'company' => [
                'name' => 'Test Dealer',
            ],
        ];

        $listings = array_map(function (array $overrides) use ($baseListing): array {
            $listing = array_merge($baseListing, $overrides);

            if (isset($overrides['vehicle']) && is_array($overrides['vehicle'])) {
                $listing['vehicle'] = array_merge($baseListing['vehicle'], $overrides['vehicle']);
            }

            if (isset($overrides['company']) && is_array($overrides['company'])) {
                $listing['company'] = array_merge($baseListing['company'], $overrides['company']);
            }

            return $listing;
        }, $listingOverrides);

        return json_encode([
            'results' => $listings,
            'count' => $totalCount ?? count($listings),
        ]);
    }
}
