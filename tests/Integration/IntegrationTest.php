<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Tests\Integration;

use NiekNijland\ViaBOVAG\Data\ListingDetail;
use NiekNijland\ViaBOVAG\Data\MotorcycleSearchCriteria;
use NiekNijland\ViaBOVAG\Data\SearchResult;
use NiekNijland\ViaBOVAG\ViaBOVAG;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests that hit the live viabovag.nl site.
 * Run separately with: vendor/bin/phpunit --testsuite Integration
 */
class IntegrationTest extends TestCase
{
    private static ViaBOVAG $client;

    private static ?SearchResult $searchResult = null;

    private static ?ListingDetail $listingDetail = null;

    public static function setUpBeforeClass(): void
    {
        self::$client = new ViaBOVAG;

        // Fetch data once to minimize HTTP calls
        self::$searchResult = self::$client->search(new MotorcycleSearchCriteria);

        if (self::$searchResult->listings !== []) {
            self::$listingDetail = self::$client->getDetail(self::$searchResult->listings[0]);
        }
    }

    public function test_search_returns_results(): void
    {
        $this->assertNotNull(self::$searchResult);
        $this->assertGreaterThan(0, self::$searchResult->totalCount);
        $this->assertNotEmpty(self::$searchResult->listings);
    }

    public function test_search_listing_has_expected_structure(): void
    {
        $this->assertNotEmpty(self::$searchResult->listings);

        $listing = self::$searchResult->listings[0];

        $this->assertNotEmpty($listing->id);
        $this->assertNotEmpty($listing->title);
        $this->assertGreaterThan(0, $listing->price);
        $this->assertNotEmpty($listing->friendlyUriPart);
        $this->assertNotEmpty($listing->vehicle->brand);
        $this->assertNotEmpty($listing->company->name);
    }

    public function test_detail_has_expected_structure(): void
    {
        if (! self::$listingDetail instanceof ListingDetail) {
            $this->markTestSkipped('No listing available for detail test.');
        }

        $this->assertNotEmpty(self::$listingDetail->id);
        $this->assertNotEmpty(self::$listingDetail->title);
        $this->assertGreaterThan(0, self::$listingDetail->price);
        $this->assertNotEmpty(self::$listingDetail->media);
        $this->assertNotEmpty(self::$listingDetail->specificationGroups);
    }

    public function test_detail_has_company_data(): void
    {
        if (! self::$listingDetail instanceof ListingDetail) {
            $this->markTestSkipped('No listing available for detail test.');
        }

        $this->assertNotEmpty(self::$listingDetail->company->name);
        $this->assertNotNull(self::$listingDetail->company->city);
    }

    public function test_detail_has_vehicle_data(): void
    {
        if (! self::$listingDetail instanceof ListingDetail) {
            $this->markTestSkipped('No listing available for detail test.');
        }

        $this->assertNotEmpty(self::$listingDetail->vehicle->brand);
    }

    public function test_detail_by_slug_works(): void
    {
        if (! self::$searchResult instanceof SearchResult || self::$searchResult->listings === []) {
            $this->markTestSkipped('No listing available for slug test.');
        }

        $listing = self::$searchResult->listings[0];
        $detail = self::$client->getDetailBySlug($listing->friendlyUriPart);

        $this->assertNotEmpty($detail->id);
        $this->assertNotEmpty($detail->title);
    }
}
