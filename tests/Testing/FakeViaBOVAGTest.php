<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Tests\Testing;

use NiekNijland\ViaBOVAG\Data\MobilityType;
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

    public function test_returns_default_listing_detail(): void
    {
        $fake = new FakeViaBOVAG;
        $detail = $fake->getDetailBySlug('test-slug');

        $this->assertNotEmpty($detail->id);
        $this->assertNotEmpty($detail->title);
    }

    public function test_returns_configured_listing_detail(): void
    {
        $listingDetail = ListingDetailFactory::make(['title' => 'Custom Title']);

        $fake = new FakeViaBOVAG;
        $fake->withListingDetail($listingDetail);

        $detail = $fake->getDetailBySlug('test-slug');

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
        $fake->getDetailBySlug('slug-1');
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

    public function test_reset_session_tracking(): void
    {
        $fake = new FakeViaBOVAG;

        $fake->assertSessionNotReset();

        $fake->resetSession();

        $fake->assertSessionReset();
    }
}
