# ViaBOVAG PHP

[![Latest Version on Packagist](https://img.shields.io/packagist/v/nieknijland/viabovag.svg?style=flat-square)](https://packagist.org/packages/nieknijland/viabovag)
[![Tests](https://img.shields.io/github/actions/workflow/status/nieknijland/viabovag/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/nieknijland/viabovag/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/nieknijland/viabovag.svg?style=flat-square)](https://packagist.org/packages/nieknijland/viabovag)

A PHP package to search and retrieve vehicle listings from [viabovag.nl](https://www.viabovag.nl), the BOVAG-certified vehicle marketplace in the Netherlands. Supports cars, motorcycles, bicycles, and campers.

## Requirements

- PHP 8.4+

## Installation

```bash
composer require nieknijland/viabovag
```

## Quick Start

```php
use NiekNijland\ViaBOVAG\ViaBOVAG;
use NiekNijland\ViaBOVAG\Data\CarSearchCriteria;

$client = new ViaBOVAG();

// Search for cars
$results = $client->search(new CarSearchCriteria(
    brand: 'volkswagen',
    priceFrom: 5000,
    priceTo: 20000,
));

foreach ($results->listings as $listing) {
    echo "{$listing->title} - EUR {$listing->price}\n";
}

// Get full details for a listing
$detail = $client->getDetail($results->listings[0]);
```

## Usage

### Creating the Client

The client accepts an optional PSR-18 HTTP client and PSR-16 cache:

```php
use NiekNijland\ViaBOVAG\ViaBOVAG;

// Default: uses Guzzle, no cache
$client = new ViaBOVAG();

// With custom HTTP client and cache
$client = new ViaBOVAG(
    httpClient: $yourPsr18Client,
    cache: $yourPsr16Cache,
    cacheTtl: 3600, // Build ID cache TTL in seconds (default: 1 hour)
);
```

The cache is used to persist the viabovag.nl Next.js build ID between requests. Without a cache, the build ID is fetched from the homepage on every new client instance.

### Searching Listings

Use a search criteria class matching the vehicle type you want to search for. All filter parameters are optional -- pass only what you need:

#### Cars

```php
use NiekNijland\ViaBOVAG\Data\CarSearchCriteria;
use NiekNijland\ViaBOVAG\Data\CarBodyType;
use NiekNijland\ViaBOVAG\Data\CarFuelType;
use NiekNijland\ViaBOVAG\Data\TransmissionType;
use NiekNijland\ViaBOVAG\Data\AccessoryFilter;

$results = $client->search(new CarSearchCriteria(
    brand: 'volkswagen',
    model: 'golf',
    priceFrom: 5000,
    priceTo: 25000,
    yearFrom: 2018,
    mileageTo: 100000,
    bodyTypes: [CarBodyType::Hatchback],
    fuelTypes: [CarFuelType::Benzine, CarFuelType::Hybride],
    transmission: TransmissionType::Automatisch,
    accessories: [AccessoryFilter::Navigation, AccessoryFilter::CruiseControl],
    page: 1,
));
```

#### Motorcycles

```php
use NiekNijland\ViaBOVAG\Data\MotorcycleSearchCriteria;
use NiekNijland\ViaBOVAG\Data\MotorcycleBodyType;
use NiekNijland\ViaBOVAG\Data\DriversLicense;

$results = $client->search(new MotorcycleSearchCriteria(
    brand: 'suzuki',
    engineCapacityFrom: 600,
    bodyTypes: [MotorcycleBodyType::Sport, MotorcycleBodyType::Naked],
    driversLicense: DriversLicense::A,
));
```

#### Bicycles

```php
use NiekNijland\ViaBOVAG\Data\BicycleSearchCriteria;
use NiekNijland\ViaBOVAG\Data\BicycleFuelType;

$results = $client->search(new BicycleSearchCriteria(
    brand: 'gazelle',
    fuelTypes: [BicycleFuelType::Elektriciteit],
    priceTo: 3000,
));
```

#### Campers

```php
use NiekNijland\ViaBOVAG\Data\CamperSearchCriteria;

$results = $client->search(new CamperSearchCriteria(
    brand: 'volkswagen',
    yearFrom: 2020,
    priceTo: 80000,
));
```

### Working with Search Results

`SearchResult` provides pagination helpers:

```php
$results = $client->search(new CarSearchCriteria(brand: 'toyota'));

$results->totalCount;      // Total matching listings across all pages
$results->currentPage;     // Current page number
$results->totalPages();    // Total number of pages
$results->hasNextPage();   // Whether more pages exist
$results->hasPreviousPage(); // Whether previous pages exist
$results->pageSize();      // Items per page (24)

// Iterate listings
foreach ($results->listings as $listing) {
    $listing->id;          // UUID
    $listing->title;       // e.g. "Toyota Corolla 1.8 Hybrid"
    $listing->price;       // Price in whole euros
    $listing->url;         // Full URL on viabovag.nl
    $listing->imageUrl;    // Primary image URL
    $listing->vehicle;     // Vehicle DTO (brand, model, year, mileage, etc.)
    $listing->company;     // Company DTO (dealer name, city, phone)
}
```

### Getting Listing Details

Fetch full vehicle details from a listing or by URL slug:

```php
// From a listing object
$detail = $client->getDetail($listing);

// By slug and mobility type
use NiekNijland\ViaBOVAG\Data\MobilityType;

$detail = $client->getDetailBySlug('suzuki-gsx-r-1300-hayabusa-abc123', MobilityType::Motor);
```

The `ListingDetail` contains all data from the vehicle detail page:

```php
$detail->id;                 // UUID
$detail->title;              // Vehicle title
$detail->price;              // Price in whole euros
$detail->description;        // Dealer description (HTML)
$detail->licensePlate;       // License plate number
$detail->media;              // Media[] (images and videos)
$detail->vehicle;            // Vehicle DTO with full specs
$detail->company;            // Company DTO with address, coordinates, reviews
$detail->specificationGroups; // SpecificationGroup[] (grouped display specs)
$detail->accessories;        // Accessory[] (e.g. ABS, Traction Control)
$detail->optionGroups;       // OptionGroup[] (named groups of options)
```

### Resetting the Session

If you need to force a fresh build ID (e.g. after a deployment on viabovag.nl):

```php
$client->resetSession();
```

## Search Filter Reference

### Shared Filters (All Vehicle Types)

All search criteria classes share these filter parameters:

| Parameter | Type | Description |
|---|---|---|
| `brand` | `?string` | Brand name (e.g. `'volkswagen'`) |
| `model` | `?string` | Model name (e.g. `'golf'`) |
| `modelKeywords` | `?string` | Model keywords for free-text matching |
| `priceFrom` | `?int` | Minimum price in euros |
| `priceTo` | `?int` | Maximum price in euros |
| `leasePriceFrom` | `?int` | Minimum lease price |
| `leasePriceTo` | `?int` | Maximum lease price |
| `yearFrom` | `?int` | Minimum production year |
| `yearTo` | `?int` | Maximum production year |
| `modelYearFrom` | `?int` | Minimum model year |
| `modelYearTo` | `?int` | Maximum model year |
| `mileageFrom` | `?int` | Minimum mileage in km |
| `mileageTo` | `?int` | Maximum mileage in km |
| `enginePowerFrom` | `?int` | Minimum engine power in HP |
| `enginePowerTo` | `?int` | Maximum engine power in HP |
| `colors` | `?string[]` | Color filter values |
| `condition` | `?Condition` | `Condition::Occasion` or `Condition::Nieuw` |
| `postalCode` | `?string` | Postal code for location search |
| `distance` | `?Distance` | Search radius from postal code |
| `warranty` | `?BovagWarranty` | BOVAG warranty duration filter |
| `fullyServiced` | `?bool` | 100% maintained |
| `hasBovagChecklist` | `?bool` | 40-point BOVAG checklist completed |
| `hasBovagMaintenanceFree` | `?bool` | BOVAG maintenance-free |
| `hasBovagImportOdometerCheck` | `?bool` | BOVAG import odometer check |
| `carServicedOnDelivery` | `?bool` | Serviced on delivery |
| `hasNapWeblabel` | `?bool` | NAP web label present |
| `vatDeductible` | `?bool` | VAT deductible |
| `isFinanceable` | `?bool` | Online financing available |
| `isImported` | `?bool` | Imported vehicle |
| `keywords` | `?string` | Free-text keyword search |
| `availableSince` | `?AvailableSince` | Filter by listing date |
| `page` | `int` | Page number (default: `1`) |

### Car-Specific Filters

| Parameter | Type | Description |
|---|---|---|
| `engineCapacityFrom` | `?int` | Minimum engine capacity in cc |
| `engineCapacityTo` | `?int` | Maximum engine capacity in cc |
| `accelerationTo` | `?int` | Maximum 0-100 acceleration time |
| `topSpeedFrom` | `?int` | Minimum top speed |
| `bodyTypes` | `?CarBodyType[]` | Body type filters |
| `fuelTypes` | `?CarFuelType[]` | Fuel type filters |
| `transmission` | `?TransmissionType` | Transmission type |
| `gearCounts` | `?GearCount[]` | Number of gears |
| `cylinderCounts` | `?CylinderCount[]` | Number of cylinders |
| `seatCounts` | `?SeatCount[]` | Number of seats |
| `driveTypes` | `?DriveType[]` | Drive type (FWD/RWD/4WD) |
| `accessories` | `?AccessoryFilter[]` | Required accessories |
| `emptyMassTo` | `?int` | Maximum empty weight |

### Motorcycle-Specific Filters

| Parameter | Type | Description |
|---|---|---|
| `engineCapacityFrom` | `?int` | Minimum engine capacity in cc |
| `engineCapacityTo` | `?int` | Maximum engine capacity in cc |
| `bodyTypes` | `?MotorcycleBodyType[]` | Body type filters |
| `fuelTypes` | `?MotorcycleFuelType[]` | Fuel type filters |
| `transmission` | `?TransmissionType` | Transmission type |
| `driversLicense` | `?DriversLicense` | Required license (A, A1, A2) |

### Bicycle-Specific Filters

| Parameter | Type | Description |
|---|---|---|
| `bodyTypes` | `?BicycleBodyType[]` | Body type filters |
| `fuelTypes` | `?BicycleFuelType[]` | Fuel type filters |

### Camper-Specific Filters

| Parameter | Type | Description |
|---|---|---|
| `engineCapacityFrom` | `?int` | Minimum engine capacity in cc |
| `engineCapacityTo` | `?int` | Maximum engine capacity in cc |
| `transmission` | `?TransmissionType` | Transmission type |

## Data Transfer Objects

All DTOs are `readonly` classes with promoted constructor properties.

### `Listing`

Returned in search results.

| Property | Type | Description |
|---|---|---|
| `id` | `string` | UUID |
| `mobilityType` | `string` | Vehicle category (`"motor"`, `"car"`, etc.) |
| `url` | `string` | Full URL on viabovag.nl |
| `friendlyUriPart` | `string` | URL slug for detail lookup |
| `externalAdvertisementUrl` | `?string` | External dealer URL |
| `imageUrl` | `?string` | Primary image URL |
| `title` | `string` | Listing title |
| `price` | `int` | Price in whole euros |
| `isFinanceable` | `bool` | Online financing available |
| `vehicle` | `Vehicle` | Vehicle data |
| `company` | `Company` | Dealer data |

### `ListingDetail`

Returned by `getDetail()` and `getDetailBySlug()`.

| Property | Type | Description |
|---|---|---|
| `id` | `string` | UUID |
| `title` | `string` | Vehicle title |
| `price` | `int` | Price in whole euros |
| `description` | `?string` | Dealer description (HTML) |
| `media` | `Media[]` | Images and videos |
| `vehicle` | `Vehicle` | Full vehicle specifications |
| `company` | `Company` | Dealer info with address, coordinates, reviews |
| `specificationGroups` | `SpecificationGroup[]` | Grouped specifications |
| `accessories` | `Accessory[]` | Vehicle accessories |
| `optionGroups` | `OptionGroup[]` | Named option groups |
| `licensePlate` | `?string` | License plate number |
| `externalNumber` | `?string` | External reference number |
| `structuredData` | `array\|string\|null` | JSON-LD structured data |

### `Vehicle`

Vehicle data shared between search results and detail pages.

| Property | Type | Description |
|---|---|---|
| `type` | `string` | Vehicle type |
| `brand` | `string` | Brand name |
| `model` | `string` | Model name |
| `mileage` | `int` | Mileage in km |
| `mileageUnit` | `string` | Always `"kilometer"` |
| `year` | `int` | Production year |
| `month` | `?int` | Production month |
| `fuelTypes` | `string[]` | Fuel types |
| `color` | `?string` | Color |
| `bodyType` | `?string` | Body type |
| `transmissionType` | `?string` | Transmission type |
| `engineCapacity` | `?int` | Engine capacity in cc |
| `enginePower` | `?int` | Engine power in HP |
| `warranties` | `string[]` | Warranty keys |
| `certaintyKeys` | `string[]` | BOVAG certainty keys |
| `fullyServiced` | `bool` | 100% maintained |
| `hasBovagChecklist` | `bool` | 40-point BOVAG checklist done |
| `bovagWarranty` | `?string` | BOVAG warranty type |
| `hasReturnWarranty` | `bool` | Return warranty available |
| `servicedOnDelivery` | `bool` | Serviced on delivery |

### `Company`

Dealer/company information.

| Property | Type | Description |
|---|---|---|
| `name` | `string` | Company name |
| `city` | `?string` | City |
| `phoneNumber` | `?string` | Phone number |
| `websiteUrl` | `?string` | Website URL |
| `callTrackingCode` | `?string` | Call tracking identifier |
| `street` | `?string` | Street address (detail only) |
| `postalCode` | `?string` | Postal code (detail only) |
| `latitude` | `?float` | GPS latitude (detail only) |
| `longitude` | `?float` | GPS longitude (detail only) |
| `reviewScore` | `?float` | Review rating (detail only) |
| `reviewCount` | `?int` | Number of reviews (detail only) |

### `SearchResult`

Paginated search result container.

| Property / Method | Type | Description |
|---|---|---|
| `listings` | `Listing[]` | Array of listings (up to 24 per page) |
| `totalCount` | `int` | Total matching results |
| `currentPage` | `int` | Current page number |
| `totalPages()` | `int` | Calculated total pages |
| `hasNextPage()` | `bool` | More pages available |
| `hasPreviousPage()` | `bool` | Previous pages available |
| `pageSize()` | `int` | Always returns `24` |

### Other DTOs

| Class | Properties | Description |
|---|---|---|
| `Media` | `type` (`MediaType`), `url` (`string`) | Image or video item |
| `Accessory` | `name` (`string`) | Vehicle accessory |
| `OptionGroup` | `name` (`string`), `options` (`string[]`) | Named group of options |
| `SpecificationGroup` | `name` (`string`), `specifications` (`Specification[]`) | Named group of specs |
| `Specification` | `label` (`string`), `value` (`?string`), `formattedValue` (`?string`) | Single specification |

## Enums

| Enum | Cases |
|---|---|
| `MobilityType` | `Motor`, `Car`, `Bicycle`, `Camper` |
| `CarBodyType` | `Hatchback`, `Sedan`, `SuvTerreinwagen`, `Stationwagen`, `Coupe`, `Mpv`, `Cabriolet`, `Bedrijfswagen`, `Personenbus`, `Pickup`, `Overig` |
| `MotorcycleBodyType` | `AllRoad`, `Chopper`, `Classic`, `Crosser`, `Cruiser`, `Enduro`, `Minibike`, `Motorscooter`, `Naked`, `Quad`, `Racer`, `Rally`, `Sport`, `SportTouring`, `Supermotard`, `SuperSport`, `Tourer`, `TouringEnduro`, `Trial`, `Trike`, `Zijspan`, `Overig` |
| `BicycleBodyType` | `Bakfiets`, `BmxFreestyleFiets`, `Crosshybride`, `Cruiserfiets`, `HybrideFiets`, `Jeugdfiets`, `Kinderfiets`, `Ligfiets`, `Mountainbike`, `Racefiets`, `Stadsfiets`, `Tandem`, `Vouwfiets`, `Overig` |
| `CarFuelType` | `Benzine`, `Diesel`, `Hybride`, `Elektriciteit`, `Gas`, `Waterstof`, `Overige` |
| `MotorcycleFuelType` | `Benzine`, `Elektriciteit`, `Overige` |
| `BicycleFuelType` | `Elektriciteit`, `Overige` |
| `TransmissionType` | `Handgeschakeld`, `Automatisch`, `SemiAutomatisch` |
| `Condition` | `Occasion`, `Nieuw` |
| `BovagWarranty` | `TwaalfMaanden`, `ZesMaanden`, `DrieMaanden` |
| `Distance` | `Five`, `Ten`, `Twenty`, `Thirty`, `Forty`, `Fifty`, `OneHundred`, `TwoHundred`, `ThreeHundred` |
| `DriversLicense` | `A`, `A1`, `A2` |
| `AvailableSince` | `Today`, `ThreeDays`, `SevenDays`, `FourteenDays` |
| `AccessoryFilter` | `Airco`, `ClimateControl`, `AndroidAuto`, `AppleCarPlay`, `CruiseControl`, `AdaptiveCruiseControl`, `Navigation`, `ParkingSensors`, `HeatedSeats`, `LeatherInterior`, `Panoramicroof`, `Towbar`, `HeadUpDisplay`, `BlindSpotDetection` |
| `DriveType` | `FrontWheel`, `RearWheel`, `FourWheel` |
| `MediaType` | `Image`, `Video` |
| `SeatCount` | `One` through `Nine` |
| `GearCount` | `Five`, `Six`, `Seven`, `Eight`, `Nine` |
| `CylinderCount` | `Two`, `Three`, `Four`, `Five`, `Six`, `Eight`, `Ten` |

## Error Handling

All errors throw exceptions that extend `ViaBOVAGException` (which extends `RuntimeException`):

```php
use NiekNijland\ViaBOVAG\Exception\ViaBOVAGException;
use NiekNijland\ViaBOVAG\Exception\NotFoundException;

try {
    $results = $client->search(new CarSearchCriteria(brand: 'toyota'));
} catch (NotFoundException $e) {
    // Resource not found (404) -- usually a stale build ID
    // The client retries automatically once before throwing this
} catch (ViaBOVAGException $e) {
    // HTTP failures, JSON parse errors, or other issues
}
```

The client automatically handles stale build IDs: on a 404 response, it invalidates the build ID, fetches a fresh one from the homepage, and retries the request once.

## Testing

### Running the Test Suite

```bash
composer test
```

Other commands:

```bash
composer test-coverage  # Run tests with coverage
composer format         # Format code with Laravel Pint
composer analyse        # Run PHPStan (level 8)
```

### Using the Fake Client in Your Tests

The package ships with a `FakeViaBOVAG` client and factories for use in your own test suite:

```php
use NiekNijland\ViaBOVAG\Testing\FakeViaBOVAG;
use NiekNijland\ViaBOVAG\Testing\SearchResultFactory;
use NiekNijland\ViaBOVAG\Testing\ListingFactory;
use NiekNijland\ViaBOVAG\Testing\ListingDetailFactory;

// Create a fake client (implements ViaBOVAGInterface)
$fake = new FakeViaBOVAG();

// Configure return values
$fake->withSearchResult(SearchResultFactory::make([
    'totalCount' => 100,
]));
$fake->withListingDetail(ListingDetailFactory::make([
    'title' => 'Custom Vehicle',
]));

// Configure exceptions (one-shot: auto-cleared after thrown)
$fake->shouldThrow(new ViaBOVAGException('Connection failed'));

// Use it like the real client
$results = $fake->search(new CarSearchCriteria(brand: 'toyota'));

// Inspect recorded calls
$fake->getCalls();              // All recorded calls
$fake->getCallsTo('search');    // Only search() calls

// PHPUnit assertions
$fake->assertCalled('search');
$fake->assertCalled('search', times: 1);
$fake->assertNotCalled('getDetail');
$fake->assertSessionReset();
$fake->assertSessionNotReset();
```

### Factories

All factories accept an optional `$overrides` array to customize properties:

```php
use NiekNijland\ViaBOVAG\Testing\ListingFactory;
use NiekNijland\ViaBOVAG\Testing\VehicleFactory;
use NiekNijland\ViaBOVAG\Testing\CompanyFactory;
use NiekNijland\ViaBOVAG\Testing\ListingDetailFactory;
use NiekNijland\ViaBOVAG\Testing\SearchResultFactory;

// Create single instances
$listing = ListingFactory::make(['title' => 'My Vehicle']);
$vehicle = VehicleFactory::make(['brand' => 'Honda', 'model' => 'CBR']);
$company = CompanyFactory::make(['name' => 'Test Dealer']);
$detail = ListingDetailFactory::make();
$result = SearchResultFactory::make(['totalCount' => 50]);

// Create multiple listings
$listings = ListingFactory::makeMany(5, ['brand' => 'Toyota']);
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Niek Nijland](https://github.com/NiekNijland)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
