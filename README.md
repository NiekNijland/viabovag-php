# ViaBOVAG PHP

[![Latest Version on Packagist](https://img.shields.io/packagist/v/nieknijland/viabovag.svg?style=flat-square)](https://packagist.org/packages/nieknijland/viabovag)
[![Tests](https://img.shields.io/github/actions/workflow/status/nieknijland/viabovag-php/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/nieknijland/viabovag-php/actions/workflows/run-tests.yml)
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
use NiekNijland\ViaBOVAG\Data\Brand;
use NiekNijland\ViaBOVAG\Data\CarSearchCriteria;
use NiekNijland\ViaBOVAG\Data\MobilityType;

$client = new ViaBOVAG;

$brands = $client->getBrands(MobilityType::Car);
$volkswagenBrand = null;

foreach ($brands as $brand) {
    if ($brand->slug === 'volkswagen') {
        $volkswagenBrand = $brand;

        break;
    }
}

// Search for cars
$results = $client->search(new CarSearchCriteria(
    brand: $volkswagenBrand,
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
$client = new ViaBOVAG;

// With custom HTTP client and cache
$client = new ViaBOVAG(
    httpClient: $yourPsr18Client,
    cache: $yourPsr16Cache,
    cacheTtl: 3600,    // Build ID cache TTL in seconds (default: 1 hour)
);
```

The cache is used to persist the viabovag.nl Next.js build ID between requests. Without a cache, the build ID is fetched from the homepage on every new client instance.

### Fetching Brand Value Objects

`brand` filters now accept a `Brand` value object instead of a raw string. Use `getBrands()` to retrieve valid values from viabovag:

```php
use NiekNijland\ViaBOVAG\Data\Brand;
use NiekNijland\ViaBOVAG\Data\MobilityType;

$brands = $client->getBrands(MobilityType::Motorcycle);

foreach ($brands as $brand) {
    echo "{$brand->label} ({$brand->slug}) - {$brand->count}\n";
}
```

### Fetching Model Value Objects

`model` filters now accept a `Model` value object. Use `getModels()` and optionally provide a `Brand` to fetch models for a specific marque:

```php
use NiekNijland\ViaBOVAG\Data\Brand;
use NiekNijland\ViaBOVAG\Data\MobilityType;

$models = $client->getModels(
    MobilityType::Car,
    new Brand(slug: 'volkswagen', label: 'Volkswagen'),
);

foreach ($models as $model) {
    echo "{$model->label} ({$model->slug}) - {$model->count}\n";
}
```

### Fetching Generic Facet Options

Use `getFacetOptions()` with the `FacetName` enum for all other facet-backed filter values. You can scope by brand and model when needed:

```php
use NiekNijland\ViaBOVAG\Data\Brand;
use NiekNijland\ViaBOVAG\Data\FacetName;
use NiekNijland\ViaBOVAG\Data\Model;
use NiekNijland\ViaBOVAG\Data\MobilityType;

$categories = $client->getFacetOptions(
    MobilityType::Motorcycle,
    FacetName::BodyType,
    new Brand(slug: 'yamaha', label: 'Yamaha'),
    new Model(slug: 'mt-07', label: 'MT-07'),
);

foreach ($categories as $category) {
    echo "{$category->label} ({$category->slug}) - {$category->count}\n";
}
```

### Searching Listings

Use a search criteria class matching the vehicle type you want to search for. All filter parameters are optional -- pass only what you need:

#### Cars

```php
use NiekNijland\ViaBOVAG\Data\CarSearchCriteria;
use NiekNijland\ViaBOVAG\Data\Brand;
use NiekNijland\ViaBOVAG\Data\Model;
use NiekNijland\ViaBOVAG\Data\CarBodyType;
use NiekNijland\ViaBOVAG\Data\CarFuelType;
use NiekNijland\ViaBOVAG\Data\TransmissionType;
use NiekNijland\ViaBOVAG\Data\AccessoryFilter;

$results = $client->search(new CarSearchCriteria(
    brand: new Brand(slug: 'volkswagen', label: 'Volkswagen'),
    model: new Model(slug: 'golf', label: 'Golf'),
    priceFrom: 5000,
    priceTo: 25000,
    yearFrom: 2018,
    mileageTo: 100000,
    bodyTypes: [CarBodyType::Hatchback],
    fuelTypes: [CarFuelType::Petrol, CarFuelType::Hybrid],
    transmissions: [TransmissionType::Automatic],
    accessories: [AccessoryFilter::Navigation, AccessoryFilter::CruiseControl],
    page: 1,
));
```

#### Motorcycles

```php
use NiekNijland\ViaBOVAG\Data\MotorcycleSearchCriteria;
use NiekNijland\ViaBOVAG\Data\Brand;
use NiekNijland\ViaBOVAG\Data\MotorcycleBodyType;
use NiekNijland\ViaBOVAG\Data\DriversLicense;
use NiekNijland\ViaBOVAG\Data\SortOrder;

$results = $client->search(new MotorcycleSearchCriteria(
    brand: new Brand(slug: 'suzuki', label: 'Suzuki'),
    engineCapacityFrom: 600,
    bodyTypes: [MotorcycleBodyType::Sport, MotorcycleBodyType::Naked],
    driversLicenses: [DriversLicense::A],
    sortOrder: SortOrder::PriceAscending,
));
```

#### Bicycles

```php
use NiekNijland\ViaBOVAG\Data\BicycleSearchCriteria;
use NiekNijland\ViaBOVAG\Data\BicycleFuelType;
use NiekNijland\ViaBOVAG\Data\Brand;

$results = $client->search(new BicycleSearchCriteria(
    brand: new Brand(slug: 'gazelle', label: 'Gazelle'),
    fuelTypes: [BicycleFuelType::Electric],
    priceTo: 3000,
));
```

#### Campers

```php
use NiekNijland\ViaBOVAG\Data\CamperSearchCriteria;
use NiekNijland\ViaBOVAG\Data\Brand;

$results = $client->search(new CamperSearchCriteria(
    brand: new Brand(slug: 'volkswagen', label: 'Volkswagen'),
    yearFrom: 2020,
    priceTo: 80000,
));
```

### Multi-Select Filters

Filters that support multiple values are array-based. Pass one value as a single-item array.

```php
use NiekNijland\ViaBOVAG\Data\BovagWarranty;
use NiekNijland\ViaBOVAG\Data\Brand;
use NiekNijland\ViaBOVAG\Data\Condition;
use NiekNijland\ViaBOVAG\Data\DriversLicense;
use NiekNijland\ViaBOVAG\Data\FilterOption;
use NiekNijland\ViaBOVAG\Data\Model;
use NiekNijland\ViaBOVAG\Data\MotorcycleSearchCriteria;
use NiekNijland\ViaBOVAG\Data\TransmissionType;

$results = $client->search(new MotorcycleSearchCriteria(
    brand: new Brand(slug: 'yamaha', label: 'Yamaha'),
    model: new Model(slug: 'mt-10-sp', label: 'MT-10 SP'),

    // Shared multi-select
    conditions: [Condition::New, Condition::Used],
    warranties: [BovagWarranty::TwelveMonths, BovagWarranty::Manufacturer],

    // Motorcycle multi-select
    transmissions: [TransmissionType::Manual, TransmissionType::Automatic],
    driversLicenses: [DriversLicense::A, DriversLicense::A2],
    accessories: [
        new FilterOption(slug: 'cruisecontrol', label: 'Cruise Control'),
        new FilterOption(slug: 'buddyseat', label: 'Buddyseat'),
    ],
));
```

`specifiedBatteryRange` remains a single `FilterOption`, because the live API accepts one battery-range value.

### Grouped Filter Objects

For cleaner construction, you can build criteria from grouped value objects:

```php
use NiekNijland\ViaBOVAG\Data\CarSearchCriteria;
use NiekNijland\ViaBOVAG\Data\FilterOption;
use NiekNijland\ViaBOVAG\Data\TransmissionType;
use NiekNijland\ViaBOVAG\Data\Filters\CarSearchFilters;
use NiekNijland\ViaBOVAG\Data\Filters\SharedSearchFilters;

$criteria = CarSearchCriteria::fromFilters(
    shared: new SharedSearchFilters(
        priceFrom: 5000,
        priceTo: 25000,
    ),
    filters: new CarSearchFilters(
        transmissions: [TransmissionType::Automatic],
        cities: [new FilterOption(slug: 'utrecht', label: 'Utrecht')],
    ),
    page: 1,
);

$results = $client->search($criteria);
```

### Working with Search Results

`SearchResult` provides pagination helpers:

```php
$results = $client->search(new CarSearchCriteria(
    brand: new Brand(slug: 'toyota', label: 'Toyota'),
));

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

### Iterating All Pages

Use `searchAll()` to iterate through all matching listings across all pages. It returns a lazy `Generator` that fetches the next page only when needed:

```php
foreach ($client->searchAll(new CarSearchCriteria(
    brand: new Brand(slug: 'volkswagen', label: 'Volkswagen'),
)) as $listing) {
    echo "{$listing->title} - EUR {$listing->price}\n";
}
```

### Getting Listing Details

Fetch full vehicle details from a listing, by full URL, or by URL slug:

```php
use NiekNijland\ViaBOVAG\Data\MobilityType;

// From a listing object (mobility type is inferred)
$detail = $client->getDetail($listing);

// By full listing URL (slug + mobility type are parsed automatically)
$detail = $client->getDetailByUrl('https://www.viabovag.nl/motor/aanbod/suzuki-gsx-r-1300-hayabusa-abc123');

// By slug (mobility type is required)
$detail = $client->getDetailBySlug('suzuki-gsx-r-1300-hayabusa-abc123', MobilityType::Motorcycle);
$detail = $client->getDetailBySlug('volkswagen-golf-test-abc123', MobilityType::Car);
```

The `ListingDetail` contains all data from the vehicle detail page:

```php
$detail->id;                 // UUID
$detail->title;              // Vehicle title
$detail->price;              // Price in whole euros
$detail->description;        // Dealer description (HTML)
$detail->descriptionText();  // Dealer description (plain text)
$detail->licensePlate;       // License plate number
$detail->driversLicense;     // Required license (DriversLicense enum)
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
| `brand` | `?Brand` | Brand value object (use `getBrands()` to fetch valid options) |
| `model` | `?Model` | Model value object (use `getModels()` to fetch valid options) |
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
| `conditions` | `?Condition[]` | Condition filters (`Condition::Used`, `Condition::New`) |
| `postalCode` | `?string` | Postal code for location search |
| `distance` | `?Distance` | Currently ignored by viabovag API (postal code searches use 20 km default) |
| `warranties` | `?BovagWarranty[]` | BOVAG warranty filters |
| `fullyServiced` | `?bool` | 100% maintained |
| `hasBovagChecklist` | `?bool` | 40-point BOVAG checklist completed |
| `hasBovagMaintenanceFree` | `?bool` | BOVAG maintenance-free |
| `hasBovagImportOdometerCheck` | `?bool` | BOVAG import odometer check |
| `servicedOnDelivery` | `?bool` | Serviced on delivery |
| `hasNapWeblabel` | `?bool` | NAP web label present |
| `vatDeductible` | `?bool` | VAT deductible |
| `isFinanceable` | `?bool` | Online financing available |
| `isImported` | `?bool` | Imported vehicle |
| `keywords` | `?string` | Free-text keyword search |
| `availableSince` | `?AvailableSince` | Filter by listing date |
| `sortOrder` | `?SortOrder` | Sort order for results |
| `page` | `int` | Page number (default: `1`) |

`keywords` is free-text search across listing content and is not equivalent to model matching. For model name filtering, prefer `modelKeywords` or an explicit `model` value object.

For `enginePowerFrom` / `enginePowerTo`, viabovag uses discrete power buckets internally (`EnginePower50`, `EnginePower75`, etc.). This package automatically formats and normalizes your numeric values to those API tokens (`from` rounds up to the next bucket, `to` rounds down to the previous bucket). For best precision, use values returned by `getFacetOptions()` for `FacetName::EnginePowerFrom` / `FacetName::EnginePowerTo`.

### Car-Specific Filters

| Parameter | Type | Description |
|---|---|---|
| `engineCapacityFrom` | `?int` | Minimum engine capacity in cc |
| `engineCapacityTo` | `?int` | Maximum engine capacity in cc |
| `accelerationTo` | `?int` | Maximum 0-100 acceleration time |
| `topSpeedFrom` | `?int` | Minimum top speed |
| `bodyTypes` | `?CarBodyType[]` | Body type filters |
| `fuelTypes` | `?CarFuelType[]` | Fuel type filters |
| `transmissions` | `?TransmissionType[]` | Transmission filters |
| `gearCounts` | `?GearCount[]` | Number of gears |
| `cylinderCounts` | `?CylinderCount[]` | Number of cylinders |
| `seatCounts` | `?SeatCount[]` | Number of seats |
| `driveTypes` | `?DriveType[]` | Drive type (FWD/RWD/4WD) |
| `accessories` | `?AccessoryFilter[]` | Required accessories |
| `emptyMassTo` | `?int` | Maximum empty weight |
| `cities` | `?FilterOption[]` | City filters (`City` facet options) |
| `isLeaseable` | `?bool` | Online leaseable |
| `doorCountFrom` | `?int` | Minimum door count |
| `doorCountTo` | `?int` | Maximum door count |
| `wheelSizeFrom` | `?int` | Minimum wheel size in inches |
| `wheelSizeTo` | `?int` | Maximum wheel size in inches |
| `brakedTowingWeightFrom` | `?int` | Minimum braked towing weight |
| `brakedTowingWeightTo` | `?int` | Maximum braked towing weight |
| `maximumMassTo` | `?int` | Maximum mass |
| `batteryCapacityFrom` | `?int` | Minimum battery capacity (kWh) |
| `batteryCapacityTo` | `?int` | Maximum battery capacity (kWh) |
| `maxChargingPowerHome` | `?int` | Minimum home charging power (kW) |
| `maxQuickChargingPower` | `?int` | Minimum quick charging power (kW) |
| `isPluginHybrid` | `?bool` | Plugin hybrid vehicle |
| `energyLabels` | `?FilterOption[]` | Energy label filters (`EnergyLabel` facet options) |
| `specifiedBatteryRange` | `?FilterOption` | Battery range (`SpecifiedBatteryRange` facet, single value) |

### Motorcycle-Specific Filters

| Parameter | Type | Description |
|---|---|---|
| `engineCapacityFrom` | `?int` | Minimum engine capacity in cc |
| `engineCapacityTo` | `?int` | Maximum engine capacity in cc |
| `accelerationTo` | `?int` | Maximum 0-100 acceleration time |
| `topSpeedFrom` | `?int` | Minimum top speed |
| `bodyTypes` | `?MotorcycleBodyType[]` | Category filters (`BodyType` facet, e.g. Crosser, Naked, Tourer) |
| `fuelTypes` | `?MotorcycleFuelType[]` | Fuel type filters |
| `transmissions` | `?TransmissionType[]` | Transmission filters |
| `driversLicenses` | `?DriversLicense[]` | Required license filters (A, A1, A2) |
| `accessories` | `?FilterOption[]` | Accessory filters (`Accessory` facet options) |

### Bicycle-Specific Filters

| Parameter | Type | Description |
|---|---|---|
| `bodyTypes` | `?BicycleBodyType[]` | Body type filters |
| `fuelTypes` | `?BicycleFuelType[]` | Fuel type filters |
| `frameHeightFrom` | `?int` | Minimum frame height in cm |
| `frameHeightTo` | `?int` | Maximum frame height in cm |
| `frameMaterials` | `?FilterOption[]` | Frame material filters (`FrameMaterial` facet options) |
| `brakeTypes` | `?FilterOption[]` | Brake type filters (`BrakeType` facet options) |
| `batteryRemovable` | `?bool` | Removable battery |
| `batteryCapacityFrom` | `?int` | Minimum battery capacity (Wh) |
| `batteryCapacityTo` | `?int` | Maximum battery capacity (Wh) |
| `engineBrands` | `?FilterOption[]` | Motor brand filters (`EngineBrand` facet options) |
| `specifiedBatteryRange` | `?FilterOption` | Battery range option value object (`SpecifiedBatteryRange` facet) |

### Camper-Specific Filters

| Parameter | Type | Description |
|---|---|---|
| `engineCapacityFrom` | `?int` | Minimum engine capacity in cc |
| `engineCapacityTo` | `?int` | Maximum engine capacity in cc |
| `transmissions` | `?TransmissionType[]` | Transmission filters |
| `bedCount` | `?int` | Number of sleeping places |
| `bedLayouts` | `?FilterOption[]` | Bed layout filters (`BedLayout` facet options) |
| `seatingLayouts` | `?FilterOption[]` | Seating layout filters (`SeatingLayout` facet options) |
| `sanitaryLayouts` | `?FilterOption[]` | Sanitary layout filters (`SanitaryLayout` facet options) |
| `kitchenLayouts` | `?FilterOption[]` | Kitchen layout filters (`KitchenLayout` facet options) |
| `interiorHeightFrom` | `?int` | Minimum interior standing height in cm |
| `camperChassisBrands` | `?FilterOption[]` | Chassis brand filters (`CamperChassisBrand` facet options) |
| `maximumMassTo` | `?int` | Maximum mass limit |

## Data Transfer Objects

All DTOs are `readonly` classes with promoted constructor properties.

### `Listing`

Returned in search results.

| Property | Type | Description |
|---|---|---|
| `id` | `string` | UUID |
| `mobilityType` | `MobilityType` | Vehicle category enum |
| `url` | `string` | Full URL on viabovag.nl |
| `friendlyUriPart` | `string` | URL slug for detail lookup |
| `externalAdvertisementUrl` | `?string` | External dealer URL |
| `imageUrl` | `?string` | Primary image URL |
| `title` | `string` | Listing title |
| `price` | `int` | Price in whole euros |
| `priceExcludesVat` | `bool` | Whether price excludes VAT |
| `isFinanceable` | `bool` | Online financing available |
| `vehicle` | `Vehicle` | Vehicle data |
| `company` | `Company` | Dealer data |

### `ListingDetail`

Returned by `getDetail()`, `getDetailByUrl()`, and `getDetailBySlug()`.

| Property | Type | Description |
|---|---|---|
| `id` | `string` | UUID |
| `title` | `string` | Vehicle title |
| `price` | `int` | Price in whole euros |
| `priceExcludesVat` | `bool` | Whether price excludes VAT |
| `description` | `?string` | Dealer description (HTML) |
| `descriptionText()` | `?string` | Dealer description (plain text accessor) |
| `url` | `?string` | Full URL on viabovag.nl |
| `mobilityType` | `?MobilityType` | Vehicle category enum |
| `media` | `Media[]` | Images and videos |
| `vehicle` | `Vehicle` | Full vehicle specifications |
| `company` | `Company` | Dealer info with address, coordinates, reviews |
| `specificationGroups` | `SpecificationGroup[]` | Grouped specifications |
| `accessories` | `Accessory[]` | Vehicle accessories |
| `optionGroups` | `OptionGroup[]` | Named option groups |
| `licensePlate` | `?string` | License plate number |
| `driversLicense` | `?DriversLicense` | Required license category |
| `externalNumber` | `?string` | External reference number |
| `structuredData` | `array\|string\|null` | JSON-LD structured data |
| `isEligibleForVehicleReport` | `bool` | Eligible for vehicle history report |
| `financingProvider` | `?string` | Financing provider name |
| `leasePrice` | `?int` | Monthly lease price in euros |
| `roadTax` | `?int` | Road tax in whole euros |
| `fuelConsumption` | `?string` | Fuel consumption (formatted string) |
| `bijtellingPercentage` | `?string` | Addition percentage for company cars |
| `returnWarrantyMileage` | `?int` | Return warranty mileage limit in km |

### `Vehicle`

Vehicle data shared between search results and detail pages. Extended fields (marked *detail only*) are populated only from detail responses.

| Property | Type | Description |
|---|---|---|
| `type` | `string` | Vehicle type |
| `brand` | `string` | Brand name |
| `model` | `string` | Model name |
| `mileage` | `int` | Mileage in km |
| `mileageUnit` | `MileageUnit` | Mileage unit enum |
| `year` | `int` | Production year |
| `month` | `?int` | Production month |
| `fuelTypes` | `string[]` | Fuel types |
| `color` | `?string` | Color |
| `bodyType` | `?string` | Body type |
| `transmissionType` | `?string` | Transmission type |
| `engineCapacity` | `?int` | Engine capacity in cc |
| `enginePower` | `?int` | Engine power in kW |
| `warranties` | `string[]` | Warranty keys |
| `certaintyKeys` | `string[]` | BOVAG certainty keys |
| `fullyServiced` | `bool` | 100% maintained |
| `hasBovagChecklist` | `bool` | 40-point BOVAG checklist done |
| `bovagWarranty` | `?string` | BOVAG warranty type |
| `hasReturnWarranty` | `bool` | Return warranty available |
| `servicedOnDelivery` | `bool` | Serviced on delivery |
| `edition` | `?string` | Vehicle edition *(detail only)* |
| `condition` | `?string` | Condition: `"occasion"`, `"nieuw"` *(detail only)* |
| `modelYear` | `?int` | Model year *(detail only)* |
| `frameType` | `?string` | Frame type *(detail only)* |
| `primaryFuelType` | `?string` | Primary fuel type *(detail only)* |
| `secondaryFuelType` | `?string` | Secondary fuel type *(detail only)* |
| `isHybridVehicle` | `?bool` | Whether vehicle is hybrid *(detail only)* |
| `energyLabel` | `?string` | Energy label *(detail only)* |
| `fuelConsumptionCombined` | `?string` | Combined fuel consumption *(detail only)* |
| `gearCount` | `?int` | Number of gears *(detail only)* |
| `isImported` | `?bool` | Whether vehicle is imported *(detail only)* |
| `hasNapLabel` | `?bool` | NAP label present *(detail only)* |
| `wheelSize` | `?string` | Wheel size *(detail only)* |
| `emptyWeight` | `?int` | Empty weight in kg *(detail only)* |
| `maxWeight` | `?int` | Maximum weight in kg *(detail only)* |
| `bedCount` | `?int` | Number of beds (campers) *(detail only)* |
| `sanitary` | `?string` | Sanitary description (campers) *(detail only)* |

### `Company`

Dealer/company information. Fields marked *(detail only)* are populated only from detail responses.

| Property | Type | Description |
|---|---|---|
| `name` | `string` | Company name |
| `city` | `?string` | City |
| `phoneNumber` | `?string` | Phone number |
| `websiteUrl` | `?string` | Website URL |
| `callTrackingCode` | `?string` | Call tracking identifier |
| `id` | `?int` | Company ID *(detail only)* |
| `street` | `?string` | Street address *(detail only)* |
| `houseNumber` | `?string` | House number *(detail only)* |
| `houseNumberExtension` | `?string` | House number extension *(detail only)* |
| `postalCode` | `?string` | Postal code *(detail only)* |
| `countryCode` | `?string` | Country code *(detail only)* |
| `latitude` | `?float` | GPS latitude *(detail only)* |
| `longitude` | `?float` | GPS longitude *(detail only)* |
| `reviewScore` | `?float` | Review rating *(detail only)* |
| `reviewCount` | `?int` | Number of reviews *(detail only)* |
| `reviewProvider` | `?string` | Review provider (e.g. `"google"`) *(detail only)* |
| `isOpenNow` | `?bool` | Whether currently open *(detail only)* |

### `SearchResult`

Paginated search result container.

| Property / Method | Type | Description |
|---|---|---|
| `listings` | `Listing[]` | Array of listings (up to 24 per page) |
| `totalCount` | `int` | Total matching results |
| `currentPage` | `int` | Current page number |
| `facets` | `SearchFacet[]` | Available search facets (brands, body types, etc.) |
| `totalPages()` | `int` | Calculated total pages |
| `hasNextPage()` | `bool` | More pages available |
| `hasPreviousPage()` | `bool` | Previous pages available |
| `pageSize()` | `int` | Always returns `24` |

### Other DTOs

| Class | Properties | Description |
|---|---|---|
| `Brand` | `slug` (`string`), `label` (`string`), `count` (`?int`) | Search brand option value object |
| `Model` | `slug` (`string`), `label` (`string`), `count` (`?int`) | Search model option value object |
| `FilterOption` | `slug` (`string`), `label` (`string`), `count` (`?int`) | Generic facet option value object |
| `Media` | `type` (`MediaType`), `url` (`string`) | Image or video item |
| `Accessory` | `name` (`string`) | Vehicle accessory |
| `OptionGroup` | `name` (`string`), `options` (`string[]`) | Named group of options |
| `SpecificationGroup` | `name`, `specifications`, `group` (`?string`), `iconName` (`?string`) | Named group of specs |
| `Specification` | `label`, `value`, `formattedValue`, `hasValue` (`bool`), `formattedValueWithoutUnit` (`?string`) | Single specification |

## Enums

| Enum | Cases |
|---|---|
| `MobilityType` | `Motorcycle`, `Car`, `Bicycle`, `Camper` |
| `CarBodyType` | `Hatchback`, `Sedan`, `SuvOffRoad`, `StationWagon`, `Coupe`, `Mpv`, `Cabriolet`, `CommercialVehicle`, `PassengerBus`, `Pickup`, `Other` |
| `MotorcycleBodyType` | `AllRoad`, `Chopper`, `Classic`, `Crosser`, `Cruiser`, `Enduro`, `Minibike`, `Motorscooter`, `Naked`, `Quad`, `Racer`, `Rally`, `Sport`, `SportTouring`, `Supermotard`, `SuperSport`, `Tourer`, `TouringEnduro`, `Trial`, `Trike`, `Sidecar`, `Other` |
| `BicycleBodyType` | `CargoBike`, `BmxFreestyleBike`, `CrossHybrid`, `CruiserBike`, `HybridBike`, `YouthBike`, `ChildBike`, `RecumbentBike`, `Mountainbike`, `RoadBike`, `CityBike`, `Tandem`, `FoldingBike`, `Other` |
| `CarFuelType` | `Petrol`, `Diesel`, `Hybrid`, `Electric`, `Gas`, `Hydrogen`, `Other` |
| `MotorcycleFuelType` | `Petrol`, `Electric`, `Other` |
| `BicycleFuelType` | `Electric`, `Other` |
| `TransmissionType` | `Manual`, `Automatic`, `SemiAutomatic` |
| `Condition` | `Used`, `New` |
| `SortOrder` | `BestResult`, `LastAdded`, `PriceAscending`, `PriceDescending`, `YearDescending`, `YearAscending`, `MileageAscending`, `MileageDescending`, `Distance` |
| `BovagWarranty` | `TwelveMonths`, `TwentyFourMonths`, `Manufacturer`, `Brand` |
| `Distance` | `Five`, `Ten`, `Twenty`, `Thirty`, `Forty`, `Fifty`, `OneHundred`, `TwoHundred`, `ThreeHundred` |
| `DriversLicense` | `A`, `A1`, `A2` |
| `AvailableSince` | `Today`, `Yesterday`, `TheDayBeforeYesterday`, `OneWeek`, `TwoWeeks`, `OneMonth` |
| `AccessoryFilter` | `Airco`, `ClimateControl`, `AndroidAuto`, `AppleCarPlay`, `CruiseControl`, `AdaptiveCruiseControl`, `Navigation`, `ParkingSensors`, `HeatedSeats`, `LeatherInterior`, `Panoramicroof`, `Towbar`, `HeadUpDisplay`, `BlindSpotDetection` |
| `DriveType` | `FrontWheel`, `RearWheel`, `FourWheel` |
| `MileageUnit` | `Kilometer` |
| `MediaType` | `Image`, `Video` |
| `SeatCount` | `One` through `Nine` |
| `GearCount` | `Five`, `Six`, `Seven`, `Eight`, `Nine` |
| `CylinderCount` | `Two`, `Three`, `Four`, `Five`, `Six`, `Eight`, `Ten` |

## Error Handling

All errors throw exceptions that extend `ViaBOVAGException` (which extends `RuntimeException`):

```php
use NiekNijland\ViaBOVAG\Exception\ViaBOVAGException;
use NiekNijland\ViaBOVAG\Exception\NotFoundException;
use NiekNijland\ViaBOVAG\Data\CarSearchCriteria;
use NiekNijland\ViaBOVAG\Data\Brand;

try {
    $results = $client->search(new CarSearchCriteria(
        brand: new Brand(slug: 'toyota', label: 'Toyota'),
    ));
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
composer test-coverage  # Run tests with coverage (requires Xdebug/PCOV)
composer test-integration # Run live integration suite without coverage
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
use NiekNijland\ViaBOVAG\Data\Brand;
use NiekNijland\ViaBOVAG\Data\CarSearchCriteria;
use NiekNijland\ViaBOVAG\Exception\ViaBOVAGException;

// Create a fake client (implements ViaBOVAGInterface)
$fake = new FakeViaBOVAG;

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
$results = $fake->search(new CarSearchCriteria(
    brand: new Brand(slug: 'toyota', label: 'Toyota'),
));

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
$listings = ListingFactory::makeMany(5, ['title' => 'Toyota Corolla']);
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Security Vulnerabilities

Please review [our security policy](https://github.com/NiekNijland/viabovag-php/security/policy) on how to report security vulnerabilities.

## Credits

- [Niek Nijland](https://github.com/NiekNijland)
- [All Contributors](https://github.com/NiekNijland/viabovag-php/contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
