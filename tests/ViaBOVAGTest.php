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
use NiekNijland\ViaBOVAG\Data\BicycleSearchCriteria;
use NiekNijland\ViaBOVAG\Data\Brand;
use NiekNijland\ViaBOVAG\Data\CarSearchCriteria;
use NiekNijland\ViaBOVAG\Data\FacetName;
use NiekNijland\ViaBOVAG\Data\FilterOption;
use NiekNijland\ViaBOVAG\Data\MobilityType;
use NiekNijland\ViaBOVAG\Data\Model;
use NiekNijland\ViaBOVAG\Data\MotorcycleBodyType;
use NiekNijland\ViaBOVAG\Data\MotorcycleFuelType;
use NiekNijland\ViaBOVAG\Data\MotorcycleSearchCriteria;
use NiekNijland\ViaBOVAG\Data\SearchQuery;
use NiekNijland\ViaBOVAG\Data\SortOrder;
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

    private function createClientWithRetries(MockHandler $mock, int $maxRetries): ViaBOVAG
    {
        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        return new ViaBOVAG(httpClient: $httpClient, maxRetries: $maxRetries);
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

    public function test_parses_car_search_results_with_auto_mobility_type(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $searchJson = $this->buildSearchJsonWithMobilityType(mobilityType: 'auto');

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClient($mock);
        $result = $client->search(new CarSearchCriteria);

        $this->assertSame(MobilityType::Car, $result->listings[0]->mobilityType);
    }

    public function test_parses_bicycle_search_results_with_fiets_mobility_type(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $searchJson = $this->buildSearchJsonWithMobilityType(mobilityType: 'fiets');

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClient($mock);
        $result = $client->search(new BicycleSearchCriteria);

        $this->assertSame(MobilityType::Bicycle, $result->listings[0]->mobilityType);
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

    public function test_get_brands_returns_brand_value_objects(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $searchJson = $this->fixture('search-results.json');

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $searchJson),
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
        $homepageHtml = $this->fixture('homepage.html');
        $searchWithInvalidListingMobilityTypeJson = '{"pageProps":{"serverSearchResults":{"results":[{"id":"test-id","mobilityType":"unknown-type"}],"count":1},"serverSearchFacets":{"facets":[{"name":"Brand","label":"Merk","options":[{"name":"tesla","label":"Tesla","count":12}],"optionCategories":[]}]}}}';

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $searchWithInvalidListingMobilityTypeJson),
        ]);

        $client = $this->createClient($mock);
        $brands = $client->getBrands(MobilityType::Car);

        $this->assertCount(1, $brands);
        $this->assertSame('tesla', $brands[0]->slug);
    }

    public function test_get_brands_uses_requested_mobility_type(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $searchJson = $this->fixture('search-results.json');

        $history = [];
        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClientWithHistory($mock, $history);
        $client->getBrands(MobilityType::Car);

        /** @var Request $searchRequest */
        $searchRequest = $history[1]['request'];
        $query = $searchRequest->getUri()->getQuery();

        $this->assertStringContainsString('mobilityType=auto', $query);
    }

    public function test_get_brands_returns_empty_when_brand_facet_is_missing(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $searchWithoutFacetsJson = '{"pageProps":{"serverSearchResults":{"results":[],"count":0}}}';

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $searchWithoutFacetsJson),
        ]);

        $client = $this->createClient($mock);
        $brands = $client->getBrands(MobilityType::Motorcycle);

        $this->assertSame([], $brands);
    }

    public function test_get_brands_supports_top_level_brand_options(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $searchWithTopLevelBrandOptionsJson = '{"pageProps":{"serverSearchResults":{"results":[],"count":0},"serverSearchFacets":{"facets":[{"name":"Brand","label":"Merk","options":[{"name":"tesla","label":"Tesla","count":12}],"optionCategories":[]}]}}}';

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $searchWithTopLevelBrandOptionsJson),
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
        $homepageHtml = $this->fixture('homepage.html');
        $searchWithEngineBrandFacetJson = '{"pageProps":{"serverSearchResults":{"results":[],"count":0},"serverSearchFacets":{"facets":[{"name":"EngineBrand","label":"Motormerk","options":[{"name":"bosch","label":"Bosch","count":18},{"name":"shimano","label":"Shimano","count":7}],"optionCategories":[]}]}}}';

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $searchWithEngineBrandFacetJson),
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
        $homepageHtml = $this->fixture('homepage.html');
        $searchWithFrameTypeFacetJson = '{"pageProps":{"serverSearchResults":{"results":[],"count":0},"serverSearchFacets":{"facets":[{"name":"FrameType","label":"Frametype","options":[],"optionCategories":[]}]}}}';

        $history = [];
        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $searchWithFrameTypeFacetJson),
        ]);

        $client = $this->createClientWithHistory($mock, $history);
        $client->getFacetOptions(
            MobilityType::Motorcycle,
            FacetName::FrameType,
            new Brand(slug: 'yamaha', label: 'Yamaha'),
            new Model(slug: 'mt-07', label: 'MT-07'),
        );

        /** @var Request $searchRequest */
        $searchRequest = $history[1]['request'];
        $query = urldecode($searchRequest->getUri()->getQuery());

        $this->assertStringContainsString('selectedFilters=merk-yamaha', $query);
        $this->assertStringContainsString('selectedFilters=model-mt-07', $query);
    }

    public function test_get_facet_options_supports_option_categories(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $searchWithFrameTypeCategoriesJson = '{"pageProps":{"serverSearchResults":{"results":[],"count":0},"serverSearchFacets":{"facets":[{"name":"FrameType","label":"Frametype","options":[],"optionCategories":[{"label":"Populair","options":[{"name":"dubbel-wieg","label":"Dubbel wieg","count":3}]}]}]}}}';

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $searchWithFrameTypeCategoriesJson),
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
        $homepageHtml = $this->fixture('homepage.html');
        $searchWithoutFacetsJson = '{"pageProps":{"serverSearchResults":{"results":[],"count":0}}}';

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $searchWithoutFacetsJson),
        ]);

        $client = $this->createClient($mock);
        $options = $client->getFacetOptions(MobilityType::Car, FacetName::EnergyLabel);

        $this->assertSame([], $options);
    }

    public function test_get_models_returns_model_value_objects(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $searchWithModelOptionsJson = '{"pageProps":{"serverSearchResults":{"results":[],"count":0},"serverSearchFacets":{"facets":[{"name":"Model","label":"Type uitvoering","options":[{"name":"golf","label":"Golf","count":48},{"name":"polo","label":"Polo","count":22}],"optionCategories":[]}]}}}';

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $searchWithModelOptionsJson),
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
        $homepageHtml = $this->fixture('homepage.html');
        $searchWithModelOptionsJson = '{"pageProps":{"serverSearchResults":{"results":[],"count":0},"serverSearchFacets":{"facets":[{"name":"Model","label":"Type uitvoering","options":[],"optionCategories":[]}]}}}';

        $history = [];
        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $searchWithModelOptionsJson),
        ]);

        $client = $this->createClientWithHistory($mock, $history);
        $client->getModels(
            MobilityType::Car,
            new Brand(slug: 'volkswagen', label: 'Volkswagen'),
        );

        /** @var Request $searchRequest */
        $searchRequest = $history[1]['request'];
        $query = urldecode($searchRequest->getUri()->getQuery());

        $this->assertStringContainsString('selectedFilters=merk-volkswagen', $query);
    }

    public function test_get_models_supports_option_categories(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $searchWithModelCategoriesJson = '{"pageProps":{"serverSearchResults":{"results":[],"count":0},"serverSearchFacets":{"facets":[{"name":"Model","label":"Type uitvoering","options":[],"optionCategories":[{"label":"Populair","options":[{"name":"gsx-r-1000","label":"GSX-R 1000","count":10}]}]}]}}}';

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $searchWithModelCategoriesJson),
        ]);

        $client = $this->createClient($mock);
        $models = $client->getModels(MobilityType::Motorcycle);

        $this->assertCount(1, $models);
        $this->assertSame('gsx-r-1000', $models[0]->slug);
        $this->assertSame('GSX-R 1000', $models[0]->label);
    }

    public function test_get_models_returns_empty_when_model_facet_is_missing(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $searchWithoutFacetsJson = '{"pageProps":{"serverSearchResults":{"results":[],"count":0}}}';

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $searchWithoutFacetsJson),
        ]);

        $client = $this->createClient($mock);
        $models = $client->getModels(MobilityType::Car);

        $this->assertSame([], $models);
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

        $client->getDetailBySlug('test-slug', MobilityType::Motorcycle);
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
        $homepageHtml = $this->fixture('homepage.html');
        $searchJson = $this->fixture('search-results.json');

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClient($mock);
        $result = $client->search(new MotorcycleSearchCriteria);

        // The fixture has priceExcludesVat = false on all listings
        $this->assertFalse($result->listings[0]->priceExcludesVat);
    }

    // --- Search Facets ---

    public function test_parses_search_facets(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $searchJson = $this->fixture('search-results.json');

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClient($mock);
        $result = $client->search(new MotorcycleSearchCriteria);

        $this->assertNotEmpty($result->facets);
        $this->assertNotEmpty($result->facets[0]->name);
        $this->assertNotEmpty($result->facets[0]->label);
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

    // --- Transient Error Retry ---

    public function test_retries_on_429_response(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $searchJson = $this->fixture('search-results.json');

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            // First attempt: 429 Too Many Requests
            new Response(429, [], ''),
            // Retry: succeeds
            new Response(200, [], $searchJson),
        ]);

        // maxRetries: 1, no sleep overhead in tests
        $client = $this->createClientWithRetries($mock, maxRetries: 1);
        $result = $client->search(new MotorcycleSearchCriteria);

        $this->assertCount(24, $result->listings);
    }

    public function test_retries_on_503_response(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $searchJson = $this->fixture('search-results.json');

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            // First attempt: 503 Service Unavailable
            new Response(503, [], ''),
            // Retry: succeeds
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClientWithRetries($mock, maxRetries: 1);
        $result = $client->search(new MotorcycleSearchCriteria);

        $this->assertCount(24, $result->listings);
    }

    public function test_throws_after_exhausting_transient_retries(): void
    {
        $homepageHtml = $this->fixture('homepage.html');

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(429, [], ''),
            new Response(429, [], ''),
        ]);

        // maxRetries: 0 means only the initial attempt + 0 retries
        $client = $this->createClientWithRetries($mock, maxRetries: 0);

        $this->expectException(ViaBOVAGException::class);
        $this->expectExceptionMessage('HTTP 429');

        $client->search(new MotorcycleSearchCriteria);
    }

    public function test_does_not_retry_non_transient_errors(): void
    {
        $homepageHtml = $this->fixture('homepage.html');

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            // 500 is not in the retryable list
            new Response(500, [], 'Internal Server Error'),
        ]);

        $client = $this->createClientWithRetries($mock, maxRetries: 2);

        $this->expectException(ViaBOVAGException::class);
        $this->expectExceptionMessage('HTTP 500');

        $client->search(new MotorcycleSearchCriteria);
    }

    // --- searchAll (pagination iterator) ---

    public function test_search_all_yields_listings_from_single_page(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        // The search fixture has 24 listings and count > 24, so let's build a small one
        $searchJson = $this->buildSearchJson(listingCount: 3, totalCount: 3);

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClient($mock);
        $listings = iterator_to_array($client->searchAll(new MotorcycleSearchCriteria));

        $this->assertCount(3, $listings);
    }

    public function test_search_all_iterates_multiple_pages(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $page1Json = $this->buildSearchJson(listingCount: 24, totalCount: 30);
        $page2Json = $this->buildSearchJson(listingCount: 6, totalCount: 30);

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $page1Json),
            new Response(200, [], $page2Json),
        ]);

        $client = $this->createClient($mock);
        $listings = iterator_to_array($client->searchAll(new MotorcycleSearchCriteria));

        $this->assertCount(30, $listings);
    }

    public function test_search_all_yields_nothing_for_empty_results(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $searchJson = $this->buildSearchJson(listingCount: 0, totalCount: 0);

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClient($mock);
        $listings = iterator_to_array($client->searchAll(new MotorcycleSearchCriteria));

        $this->assertCount(0, $listings);
    }

    public function test_search_all_propagates_error_on_second_page(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $page1Json = $this->buildSearchJson(listingCount: 24, totalCount: 48);

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $page1Json),
            // Second page returns 500
            new Response(500, [], 'Internal Server Error'),
        ]);

        $client = $this->createClientWithRetries($mock, maxRetries: 0);
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
        $homepageHtml = $this->fixture('homepage.html');

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new ConnectException(
                'Connection timed out',
                new Request('GET', 'https://www.viabovag.nl/test'),
            ),
        ]);

        $client = $this->createClient($mock);

        $this->expectException(ViaBOVAGException::class);
        $this->expectExceptionMessage('HTTP request failed');

        // Force the generator to execute by iterating it
        iterator_to_array($client->searchAll(new MotorcycleSearchCriteria));
    }

    // --- URL Construction (via request history) ---

    public function test_search_url_contains_correct_mobility_type_for_motorcycle(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $searchJson = $this->fixture('search-results.json');

        $history = [];
        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClientWithHistory($mock, $history);
        $client->search(new MotorcycleSearchCriteria);

        // history[0] = homepage, history[1] = search request
        $this->assertCount(2, $history);

        /** @var Request $searchRequest */
        $searchRequest = $history[1]['request'];
        $query = $searchRequest->getUri()->getQuery();

        $this->assertStringContainsString('mobilityType=motoren', $query);
    }

    public function test_search_url_contains_correct_mobility_type_for_car(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $searchJson = $this->buildSearchJson(listingCount: 1, totalCount: 1);

        $history = [];
        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClientWithHistory($mock, $history);
        $client->search(new CarSearchCriteria);

        /** @var Request $searchRequest */
        $searchRequest = $history[1]['request'];
        $query = $searchRequest->getUri()->getQuery();

        $this->assertStringContainsString('mobilityType=auto', $query);
    }

    public function test_search_url_contains_build_id(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $searchJson = $this->fixture('search-results.json');

        $history = [];
        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClientWithHistory($mock, $history);
        $client->search(new MotorcycleSearchCriteria);

        /** @var Request $searchRequest */
        $searchRequest = $history[1]['request'];
        $path = $searchRequest->getUri()->getPath();

        // The build ID from homepage.html fixture is PQS_ur-FpJe0R6JUyWiD2
        $this->assertStringContainsString('/_next/data/PQS_ur-FpJe0R6JUyWiD2/', $path);
        $this->assertStringEndsWith('/nl-NL/srp.json', $path);
    }

    public function test_search_url_contains_filter_slugs(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $searchJson = $this->fixture('search-results.json');

        $history = [];
        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClientWithHistory($mock, $history);
        $client->search(new MotorcycleSearchCriteria(
            brand: new Brand(slug: 'honda', label: 'Honda'),
            priceFrom: 3000,
            priceTo: 10000,
        ));

        /** @var Request $searchRequest */
        $searchRequest = $history[1]['request'];
        $query = urldecode($searchRequest->getUri()->getQuery());

        $this->assertStringContainsString('selectedFilters=', $query);
        $this->assertStringContainsString('merk-honda', $query);
        $this->assertStringContainsString('prijs-vanaf-3000', $query);
        $this->assertStringContainsString('prijs-tot-en-met-10000', $query);
    }

    public function test_search_url_omits_selected_filters_when_no_filters(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $searchJson = $this->fixture('search-results.json');

        $history = [];
        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClientWithHistory($mock, $history);
        $client->search(new MotorcycleSearchCriteria);

        /** @var Request $searchRequest */
        $searchRequest = $history[1]['request'];
        $query = $searchRequest->getUri()->getQuery();

        $this->assertStringNotContainsString('selectedFilters', $query);
    }

    public function test_search_url_contains_sort_order_slug(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $searchJson = $this->fixture('search-results.json');

        $history = [];
        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClientWithHistory($mock, $history);
        $client->search(new MotorcycleSearchCriteria(
            sortOrder: SortOrder::PriceAscending,
        ));

        /** @var Request $searchRequest */
        $searchRequest = $history[1]['request'];
        $query = urldecode($searchRequest->getUri()->getQuery());

        $this->assertStringContainsString('selectedFilters=', $query);
        $this->assertStringContainsString('sortering-prijsoplopend', $query);
    }

    public function test_search_url_includes_page_parameter_for_page_2(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $searchJson = $this->fixture('search-results.json');

        $history = [];
        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClientWithHistory($mock, $history);
        $client->search(new MotorcycleSearchCriteria(page: 3));

        /** @var Request $searchRequest */
        $searchRequest = $history[1]['request'];
        $query = $searchRequest->getUri()->getQuery();

        $this->assertStringContainsString('selectedFilters=pagina-3', $query);
    }

    public function test_search_url_omits_page_parameter_for_page_1(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $searchJson = $this->fixture('search-results.json');

        $history = [];
        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClientWithHistory($mock, $history);
        $client->search(new MotorcycleSearchCriteria(page: 1));

        /** @var Request $searchRequest */
        $searchRequest = $history[1]['request'];
        $query = $searchRequest->getUri()->getQuery();

        $this->assertStringNotContainsString('pagina-', $query);
    }

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

    public function test_search_request_includes_nextjs_data_header(): void
    {
        $homepageHtml = $this->fixture('homepage.html');
        $searchJson = $this->fixture('search-results.json');

        $history = [];
        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new Response(200, [], $searchJson),
        ]);

        $client = $this->createClientWithHistory($mock, $history);
        $client->search(new MotorcycleSearchCriteria);

        /** @var Request $searchRequest */
        $searchRequest = $history[1]['request'];

        $this->assertSame('1', $searchRequest->getHeaderLine('x-nextjs-data'));
    }

    // --- HTTP Network Error Handling ---

    public function test_throws_on_connection_exception_during_search(): void
    {
        $homepageHtml = $this->fixture('homepage.html');

        $mock = new MockHandler([
            new Response(200, [], $homepageHtml),
            new ConnectException(
                'Connection timed out',
                new Request('GET', 'https://www.viabovag.nl/_next/data/test/nl-NL/srp.json'),
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

        $client->search(new MotorcycleSearchCriteria);
    }

    public function test_throws_on_non_200_homepage_response(): void
    {
        $mock = new MockHandler([
            new Response(503, [], 'Service Unavailable'),
        ]);

        $client = $this->createClient($mock);

        $this->expectException(ViaBOVAGException::class);
        $this->expectExceptionMessage('Failed to fetch homepage: HTTP 503');

        $client->search(new MotorcycleSearchCriteria);
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

    /**
     * Build a minimal search JSON response with a specified number of listings.
     */
    private function buildSearchJson(int $listingCount, int $totalCount): string
    {
        $listings = [];
        for ($i = 0; $i < $listingCount; $i++) {
            $listings[] = [
                'id' => 'listing-'.$i.'-'.uniqid(),
                'mobilityType' => 'motor',
                'url' => '/motoren/test-'.$i,
                'friendlyUriPart' => 'test-'.$i,
                'title' => 'Test Listing '.$i,
                'price' => 5000 + ($i * 100),
                'isFinanceable' => false,
                'priceExcludesVat' => false,
                'vehicle' => [
                    'type' => 'motor',
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
            'pageProps' => [
                'serverSearchResults' => [
                    'results' => $listings,
                    'count' => $totalCount,
                ],
            ],
        ]);
    }

    private function buildSearchJsonWithMobilityType(string $mobilityType): string
    {
        return json_encode([
            'pageProps' => [
                'serverSearchResults' => [
                    'results' => [
                        [
                            'id' => 'listing-1',
                            'mobilityType' => $mobilityType,
                            'url' => '/test',
                            'friendlyUriPart' => 'test',
                            'title' => 'Test Listing',
                            'price' => 5000,
                            'isFinanceable' => false,
                            'priceExcludesVat' => false,
                            'vehicle' => [
                                'type' => $mobilityType,
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
                        ],
                    ],
                    'count' => 1,
                ],
            ],
        ]);
    }
}
