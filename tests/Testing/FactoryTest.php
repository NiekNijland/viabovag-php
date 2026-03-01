<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Tests\Testing;

use NiekNijland\ViaBOVAG\Data\Company;
use NiekNijland\ViaBOVAG\Data\Listing;
use NiekNijland\ViaBOVAG\Data\ListingDetail;
use NiekNijland\ViaBOVAG\Data\SearchResult;
use NiekNijland\ViaBOVAG\Data\Vehicle;
use NiekNijland\ViaBOVAG\Testing\CompanyFactory;
use NiekNijland\ViaBOVAG\Testing\ListingDetailFactory;
use NiekNijland\ViaBOVAG\Testing\ListingFactory;
use NiekNijland\ViaBOVAG\Testing\SearchResultFactory;
use NiekNijland\ViaBOVAG\Testing\VehicleFactory;
use PHPUnit\Framework\TestCase;

class FactoryTest extends TestCase
{
    public function test_vehicle_factory_creates_defaults(): void
    {
        $vehicle = VehicleFactory::make();

        $this->assertInstanceOf(Vehicle::class, $vehicle);
        $this->assertSame('Suzuki', $vehicle->brand);
        $this->assertSame(2018, $vehicle->year);
    }

    public function test_vehicle_factory_accepts_overrides(): void
    {
        $vehicle = VehicleFactory::make(['brand' => 'Honda', 'year' => 2023]);

        $this->assertSame('Honda', $vehicle->brand);
        $this->assertSame(2023, $vehicle->year);
    }

    public function test_company_factory_creates_defaults(): void
    {
        $company = CompanyFactory::make();

        $this->assertInstanceOf(Company::class, $company);
        $this->assertSame('Gebben Motoren', $company->name);
    }

    public function test_company_factory_accepts_overrides(): void
    {
        $company = CompanyFactory::make(['name' => 'Test Dealer']);

        $this->assertSame('Test Dealer', $company->name);
    }

    public function test_listing_factory_creates_defaults(): void
    {
        $listing = ListingFactory::make();

        $this->assertInstanceOf(Listing::class, $listing);
        $this->assertNotEmpty($listing->id);
        $this->assertNotEmpty($listing->title);
        $this->assertInstanceOf(Vehicle::class, $listing->vehicle);
        $this->assertInstanceOf(Company::class, $listing->company);
    }

    public function test_listing_factory_accepts_overrides(): void
    {
        $listing = ListingFactory::make(['title' => 'Custom Title', 'price' => 9999]);

        $this->assertSame('Custom Title', $listing->title);
        $this->assertSame(9999, $listing->price);
    }

    public function test_listing_factory_make_many(): void
    {
        $listings = ListingFactory::makeMany(5);

        $this->assertCount(5, $listings);
        $this->assertContainsOnlyInstancesOf(Listing::class, $listings);

        // IDs should be unique
        $ids = array_map(fn (Listing $l): string => $l->id, $listings);
        $this->assertCount(5, array_unique($ids));
    }

    public function test_listing_detail_factory_creates_defaults(): void
    {
        $detail = ListingDetailFactory::make();

        $this->assertInstanceOf(ListingDetail::class, $detail);
        $this->assertNotEmpty($detail->id);
        $this->assertNotEmpty($detail->title);
        $this->assertInstanceOf(Vehicle::class, $detail->vehicle);
        $this->assertInstanceOf(Company::class, $detail->company);
    }

    public function test_listing_detail_factory_accepts_overrides(): void
    {
        $detail = ListingDetailFactory::make(['title' => 'Custom Detail', 'price' => 25000]);

        $this->assertSame('Custom Detail', $detail->title);
        $this->assertSame(25000, $detail->price);
    }

    public function test_search_result_factory_creates_defaults(): void
    {
        $result = SearchResultFactory::make();

        $this->assertInstanceOf(SearchResult::class, $result);
        $this->assertNotEmpty($result->listings);
        $this->assertSame(100, $result->totalCount);
        $this->assertSame(1, $result->currentPage);
    }

    public function test_search_result_factory_accepts_overrides(): void
    {
        $result = SearchResultFactory::make(['totalCount' => 50, 'currentPage' => 3]);

        $this->assertSame(50, $result->totalCount);
        $this->assertSame(3, $result->currentPage);
    }
}
