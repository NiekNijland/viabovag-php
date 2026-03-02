<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Tests\Testing;

use NiekNijland\ViaBOVAG\Data\Brand;
use NiekNijland\ViaBOVAG\Data\FacetName;
use NiekNijland\ViaBOVAG\Data\FilterOption;
use NiekNijland\ViaBOVAG\Data\MobilityType;
use NiekNijland\ViaBOVAG\Data\Model;
use NiekNijland\ViaBOVAG\Data\MotorcycleSearchCriteria;
use NiekNijland\ViaBOVAG\Exception\ViaBOVAGException;
use NiekNijland\ViaBOVAG\Testing\FakeViaBOVAG;
use NiekNijland\ViaBOVAG\Testing\ListingDetailFactory;
use NiekNijland\ViaBOVAG\Testing\ListingFactory;
use NiekNijland\ViaBOVAG\Testing\SearchResultFactory;
use PHPUnit\Framework\TestCase;

class FakeViaBOVAGTest extends TestCase
{
    public function test_returns_default_search_result(): void
    {
        $fake = new FakeViaBOVAG;
        $result = $fake->search(new MotorcycleSearchCriteria);

        $this->assertNotEmpty($result->listings);
        $this->assertGreaterThan(0, $result->totalCount);
    }

    public function test_returns_configured_search_result(): void
    {
        $searchResult = SearchResultFactory::make(['totalCount' => 42]);

        $fake = new FakeViaBOVAG;
        $fake->withSearchResult($searchResult);

        $result = $fake->search(new MotorcycleSearchCriteria);

        $this->assertSame(42, $result->totalCount);
    }

    public function test_returns_configured_brands(): void
    {
        $fake = new FakeViaBOVAG;
        $fake->withBrands(
            new Brand(slug: 'honda', label: 'Honda', count: 42),
            new Brand(slug: 'yamaha', label: 'Yamaha', count: 35),
        );

        $brands = $fake->getBrands(MobilityType::Motorcycle);

        $this->assertCount(2, $brands);
        $this->assertSame('honda', $brands[0]->slug);
        $this->assertSame('Honda', $brands[0]->label);
        $this->assertSame(42, $brands[0]->count);
    }

    public function test_returns_configured_models(): void
    {
        $fake = new FakeViaBOVAG;
        $fake->withModels(
            new Model(slug: 'golf', label: 'Golf', count: 21),
            new Model(slug: 'polo', label: 'Polo', count: 14),
        );

        $models = $fake->getModels(
            MobilityType::Car,
            new Brand(slug: 'volkswagen', label: 'Volkswagen'),
        );

        $this->assertCount(2, $models);
        $this->assertSame('golf', $models[0]->slug);
        $this->assertSame('Golf', $models[0]->label);
        $this->assertSame(21, $models[0]->count);
    }

    public function test_returns_configured_facet_options(): void
    {
        $fake = new FakeViaBOVAG;
        $fake->withFacetOptions(
            new FilterOption(slug: 'bosch', label: 'Bosch', count: 12),
            new FilterOption(slug: 'shimano', label: 'Shimano', count: 8),
        );

        $options = $fake->getFacetOptions(MobilityType::Bicycle, FacetName::EngineBrand);

        $this->assertCount(2, $options);
        $this->assertSame('bosch', $options[0]->slug);
        $this->assertSame('Bosch', $options[0]->label);
        $this->assertSame(12, $options[0]->count);
    }

    public function test_returns_default_listing_detail(): void
    {
        $fake = new FakeViaBOVAG;
        $detail = $fake->getDetailBySlug('test-slug', MobilityType::Motorcycle);

        $this->assertNotEmpty($detail->id);
        $this->assertNotEmpty($detail->title);
    }

    public function test_returns_configured_listing_detail(): void
    {
        $listingDetail = ListingDetailFactory::make(['title' => 'Custom Title']);

        $fake = new FakeViaBOVAG;
        $fake->withListingDetail($listingDetail);

        $detail = $fake->getDetailBySlug('test-slug', MobilityType::Motorcycle);

        $this->assertSame('Custom Title', $detail->title);
    }

    public function test_get_detail_from_listing(): void
    {
        $listing = ListingFactory::make();

        $fake = new FakeViaBOVAG;
        $detail = $fake->getDetail($listing);

        $this->assertNotEmpty($detail->id);
    }

    public function test_throws_configured_exception(): void
    {
        $fake = new FakeViaBOVAG;
        $fake->shouldThrow(new ViaBOVAGException('Test error'));

        $this->expectException(ViaBOVAGException::class);
        $this->expectExceptionMessage('Test error');

        $fake->search(new MotorcycleSearchCriteria);
    }

    public function test_exception_is_cleared_after_throw(): void
    {
        $fake = new FakeViaBOVAG;
        $fake->shouldThrow(new ViaBOVAGException('Test error'));

        try {
            $fake->search(new MotorcycleSearchCriteria);
        } catch (ViaBOVAGException) {
            // Expected
        }

        // Second call should succeed
        $result = $fake->search(new MotorcycleSearchCriteria);
        $this->assertNotEmpty($result->listings);
    }

    public function test_records_calls(): void
    {
        $fake = new FakeViaBOVAG;

        $fake->search(new MotorcycleSearchCriteria);
        $fake->getDetailBySlug('slug-1', MobilityType::Motorcycle);
        $fake->getDetailBySlug('slug-2', MobilityType::Car);

        $this->assertCount(3, $fake->getCalls());
        $this->assertCount(1, $fake->getCallsTo('search'));
        $this->assertCount(2, $fake->getCallsTo('getDetailBySlug'));
    }

    public function test_assert_called(): void
    {
        $fake = new FakeViaBOVAG;

        $fake->search(new MotorcycleSearchCriteria);
        $fake->search(new MotorcycleSearchCriteria);

        $fake->assertCalled('search');
        $fake->assertCalled('search', 2);
        $fake->assertNotCalled('getDetail');
    }

    public function test_get_brands_records_call(): void
    {
        $fake = new FakeViaBOVAG;

        $fake->getBrands(MobilityType::Car);

        $fake->assertCalled('getBrands', 1);
    }

    public function test_get_models_records_call(): void
    {
        $fake = new FakeViaBOVAG;

        $fake->getModels(MobilityType::Car, new Brand(slug: 'toyota', label: 'Toyota'));

        $fake->assertCalled('getModels', 1);
    }

    public function test_get_facet_options_records_call(): void
    {
        $fake = new FakeViaBOVAG;

        $fake->getFacetOptions(
            MobilityType::Motorcycle,
            FacetName::FrameType,
            new Brand(slug: 'yamaha', label: 'Yamaha'),
            new Model(slug: 'mt-07', label: 'MT-07'),
        );

        $fake->assertCalled('getFacetOptions', 1);
    }

    public function test_reset_session_tracking(): void
    {
        $fake = new FakeViaBOVAG;

        $fake->assertSessionNotReset();

        $fake->resetSession();

        $fake->assertSessionReset();
    }

    public function test_search_all_yields_listings(): void
    {
        $fake = new FakeViaBOVAG;
        $listings = iterator_to_array($fake->searchAll(new MotorcycleSearchCriteria));

        $this->assertNotEmpty($listings);
    }

    public function test_search_all_records_call(): void
    {
        $fake = new FakeViaBOVAG;
        iterator_to_array($fake->searchAll(new MotorcycleSearchCriteria));

        $fake->assertCalled('searchAll');
        $fake->assertCalled('searchAll', 1);
    }

    public function test_search_all_with_multiple_pages(): void
    {
        $page1 = SearchResultFactory::make([
            'listings' => ListingFactory::makeMany(3),
            'totalCount' => 5,
        ]);
        $page2 = SearchResultFactory::make([
            'listings' => ListingFactory::makeMany(2),
            'totalCount' => 5,
        ]);

        $fake = new FakeViaBOVAG;
        $fake->withSearchResults($page1, $page2);

        $listings = iterator_to_array($fake->searchAll(new MotorcycleSearchCriteria));

        $this->assertCount(5, $listings);
    }

    public function test_search_all_falls_back_to_single_search_result(): void
    {
        $result = SearchResultFactory::make([
            'listings' => ListingFactory::makeMany(2),
            'totalCount' => 2,
        ]);

        $fake = new FakeViaBOVAG;
        $fake->withSearchResult($result);

        $listings = iterator_to_array($fake->searchAll(new MotorcycleSearchCriteria));

        $this->assertCount(2, $listings);
    }
}
