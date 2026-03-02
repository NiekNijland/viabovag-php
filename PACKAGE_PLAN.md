# ViaBOVAG PHP Package — Implementation Plan

## API Discovery

### Endpoints

| Endpoint | URL Pattern |
|---|---|
| **Build ID** | Extracted from `<script id="__NEXT_DATA__">` on any page via regex `"buildId":"([^"]+)"` |
| **Search (SRP)** | `GET /_next/data/{buildId}/nl-NL/srp.json?mobilityType=motoren&selectedFilters=...&page=N` |
| **Detail (VDP)** | `GET /_next/data/{buildId}/nl-NL/vdp.json?mobilityType=motor&vehicleUrl={slug}` |

### Key Characteristics

- Next.js application backed by Azure App Service
- Build ID changes on every deployment — client needs stale-ID retry (404 → re-fetch → retry once)
- JSON API via Next.js data routes — no HTML parsing needed except for build ID extraction
- 24 results per page, total count in `serverSearchResults.count`
- No explicit pagination metadata in response — client calculates from `count` + page size (24)
- Required header: `x-nextjs-data: 1`
- Images hosted on Azure Blob Storage: `stsharedprdweu.blob.core.windows.net/vehicles-media/{uuid}/media.{NNNN}.jpg`
- Responsive variants available: `media.{NNNN}-{width}w.jpg` (640w, 1024w, 1280w, 1920w)

### Search Response Structure

```
pageProps.serverSearchResults.results[]    → Listing items (24 per page)
pageProps.serverSearchResults.count        → Total matching results
pageProps.serverSearchFacets               → Filter options with counts (not used in v1)
pageProps.serverSearchRequest              → Echo of the search query
```

### Detail Response Structure

```
pageProps.vehicle.id                       → UUID
pageProps.vehicle.advertisement            → Title, price, description, media[], company, certainties
pageProps.vehicle.vehicle                  → Full specs (general, fuel, technical, financial, history, etc.)
pageProps.vehicle.vehicle.specificationGroups  → Pre-grouped specs for display
pageProps.vehicle.vehicle.accessories      → Vehicle accessories
pageProps.vehicle.vehicle.optionGroups     → Vehicle options
pageProps.vehicle.structuredData           → JSON-LD string with raw numeric values
```

### Search Listing Object (from `results[]`)

| Field | Type | Example |
|---|---|---|
| `id` | string (UUID) | `"96363b6a-38de-4ba9-a681-7335a86f8c08"` |
| `mobilityType` | string | `"motor"` |
| `url` | string | `"https://www.viabovag.nl/motor/aanbod/suzuki-gsx-r-1300-hayabusa-f0fe1ht"` |
| `friendlyUriPart` | string | `"suzuki-gsx-r-1300-hayabusa-f0fe1ht"` |
| `externalAdvertisementUrl` | string | `""` |
| `imageUrl` | string | Azure Blob URL |
| `title` | string | `"Suzuki GSX-R 1300 HAYABUSA"` |
| `price` | int | `15499` (EUR, no cents) |
| `isFinanceable` | bool | `false` |
| `vehicle.type` | string | `"motor"` |
| `vehicle.brand` | string | `"Suzuki"` |
| `vehicle.model` | string | `"GSX-R 1300 Hayabusa"` |
| `vehicle.mileage` | int | `15469` |
| `vehicle.mileageUnit` | string | `"kilometer"` |
| `vehicle.year` | int | `2018` |
| `vehicle.month` | int | `5` |
| `vehicle.fuelTypes` | string[] | `[]` |
| `vehicle.color` | string | `"wit"` |
| `vehicle.bodyType` | string | `"superSport"` |
| `vehicle.transmissionType` | string | `"Handgeschakeld"` |
| `vehicle.engineCapacity` | int | `1340` |
| `vehicle.enginePower` | int | `197` |
| `vehicle.warranties` | string[] | `["bovag12maanden"]` |
| `vehicle.certaintyKeys` | string[] | `["BovagChecklist40Point", ...]` |
| `vehicle.fullyServiced` | bool | `false` |
| `vehicle.hasBovagChecklist` | bool | `true` |
| `vehicle.bovagWarranty` | string | `"TwaalfMaanden"` |
| `vehicle.hasReturnWarranty` | bool | `true` |
| `vehicle.servicedOnDelivery` | bool | `true` |
| `company.name` | string | `"Gebben Motoren"` |
| `company.city` | string | `"Rogat"` |
| `company.phoneNumber` | string | `"0522 443820"` |
| `company.websiteUrl` | string | `"http://www.gebbenmotoren.nl"` |
| `company.callTrackingCode` | string | `"AEN2100-2710"` |

### Detail Page Additional Data (not in search)

| Field | Location | Description |
|---|---|---|
| `advertisement.comments` | HTML string | Full dealer description |
| `advertisement.media[]` | Array of `{type, url}` | All images (23+ per listing) |
| `advertisement.company` | Full company object | Address, coordinates, reviews, phone, callTrackingCode |
| `vehicle.identification` | Licence plate, external number | `"77MJRJ"`, `"0025173"` |
| `vehicle.specificationGroups[]` | 9 named groups | All specs grouped for display |
| `vehicle.accessories[]` | Array | ABS, TCS, etc. |
| `vehicle.optionGroups[]` | Array | Named option groups |
| `vehicle.findieProducten[]` | Array | Third-party financial/insurance products |
| `structuredData` | JSON-LD string | Schema.org with raw values, availability dates |

### Known Enum Values

| Enum | Values |
|---|---|
| MobilityType | `motor`, `auto`, `fiets`, `camper` |
| BodyType (motorcycle) | `AllRoad`, `Chopper`, `Classic`, `Crosser`, `Cruiser`, `Enduro`, `Minibike`, `Motorscooter`, `Naked`, `Overig`, `Quad`, `Racer`, `Rally`, `Sport`, `SportTouring`, `Supermotard`, `SuperSport`, `Tourer`, `TouringEnduro`, `Trial`, `Trike`, `Zijspan` |
| FuelType | `Benzine`, `Diesel`, `Elektrisch`, `LPG`, `CNG` |
| TransmissionType | `Handgeschakeld`, `Automatisch`, `SemiAutomatisch` |
| Condition | `Occasion`, `Nieuw` |
| BovagWarranty | `Bovag12maanden`, `Bovag24maanden`, `Fabrieksgarantie`, `Merkgarantie` |
| MileageUnit | `kilometer` |
| DriversLicense | `A`, `A1`, `A2` |
| Distance | `Five`, `Ten`, `Twenty`, `Thirty`, `Forty`, `Fifty`, `OneHundred`, `TwoHundred`, `ThreeHundred` |
| MediaType | `image`, `video` |

---

## Package Architecture

### File Structure

```
src/
  ViaBOVAG.php                       # Main client — HTTP orchestration, build ID caching
  ViaBOVAGInterface.php              # Public API contract (interface)
  Parser/
    JsonParser.php                   # Build ID extraction + JSON → DTO mapping
  Data/
    # DTOs (readonly classes)
    Listing.php                      # Search result item
    ListingDetail.php                # Full vehicle detail page
    SearchResult.php                 # Results + count + pagination helpers
    SearchCriteria.php               # All search filter parameters
    Vehicle.php                      # Vehicle data from search result
    Company.php                      # Dealer info (flat, optional fields for detail enrichment)
    Media.php                        # Image/video item (type + url)
    SpecificationGroup.php           # Named group of specs (detail page)
    Specification.php                # Single spec (label + value + formattedValue)
    Accessory.php                    # Vehicle accessory
    OptionGroup.php                  # Named group of options
    # Enums (string-backed)
    MobilityType.php
    BodyType.php
    FuelType.php
    TransmissionType.php
    Condition.php
    BovagWarranty.php
    MileageUnit.php
    MediaType.php
    DriversLicense.php
    Distance.php
    AvailableSince.php
  Exception/
    ViaBOVAGException.php            # Single exception (extends RuntimeException)
  Testing/
    FakeViaBOVAG.php                 # In-memory fake with call recording + assertions
    ListingFactory.php               # make() + makeMany()
    ListingDetailFactory.php         # make()
    VehicleFactory.php               # make()
    CompanyFactory.php               # make()
    SearchResultFactory.php          # make()
tests/
  ViaBOVAGTest.php                   # Unit tests (Guzzle MockHandler)
  IntegrationTest.php                # Live site tests (separate suite)
  Testing/
    FakeViaBOVAGTest.php             # Fake client tests
    FactoryTest.php                  # Factory tests
  ArrayCache.php                     # In-memory PSR-16 cache for tests
  Fixtures/
    homepage.html                    # For build ID extraction
    search-results.json              # SRP response fixture
    listing-detail.json              # VDP response fixture
```

### Public API (`ViaBOVAGInterface`)

```php
interface ViaBOVAGInterface
{
    /**
     * Search for vehicle listings.
     *
     * @throws ViaBOVAGException
     */
    public function getListings(SearchCriteria $criteria): SearchResult;

    /**
     * Get full detail for a listing from search results.
     *
     * @throws ViaBOVAGException
     */
    public function getDetail(Listing $listing): ListingDetail;

    /**
     * Get full detail for a listing by its URL slug.
     *
     * @throws ViaBOVAGException
     */
    public function getDetailBySlug(string $slug, MobilityType $mobilityType = MobilityType::Motor): ListingDetail;

    /**
     * Force a fresh build ID on the next request.
     */
    public function resetSession(): void;
}
```

### Client Constructor

```php
public function __construct(
    private readonly ClientInterface $httpClient = new Client(),
    private readonly ?CacheInterface $cache = null,
    private readonly int $cacheTtl = 3600,
)
```

### Client Internals

1. **Build ID management**
   - `ensureBuildId()` — checks in-memory, then cache, then fetches homepage HTML
   - Extraction via regex on `<script id="__NEXT_DATA__">`: `"buildId":"([^"]+)"`
   - Cached in PSR-16 with key `viabovag:build-id`

2. **Stale build ID retry**
   - If JSON API returns 404, invalidate cached build ID
   - Re-fetch build ID from homepage
   - Retry the original request once
   - If 404 again, throw exception

3. **Search URL builder**
   - Maps `SearchCriteria` properties → `selectedFilters=` query params
   - Uses Dutch slug mapping (see filter mapping table below)
   - `page` is a separate query param (not a selectedFilter)
   - `mobilityType` mapped to plural Dutch form for URL

4. **Detail URL builder**
   - `/_next/data/{buildId}/nl-NL/vdp.json?mobilityType={type}&vehicleUrl={slug}`

### SearchCriteria Properties

```php
readonly class SearchCriteria
{
    public function __construct(
        // Core
        public MobilityType $mobilityType = MobilityType::Motor,
        public ?string $brand = null,
        public ?string $model = null,
        public ?string $modelKeywords = null,

        // Pricing
        public ?int $priceFrom = null,
        public ?int $priceTo = null,
        public ?int $leasePriceFrom = null,
        public ?int $leasePriceTo = null,

        // Year
        public ?int $yearFrom = null,
        public ?int $yearTo = null,
        public ?int $modelYearFrom = null,
        public ?int $modelYearTo = null,

        // Mileage
        public ?int $mileageFrom = null,
        public ?int $mileageTo = null,

        // Performance
        public ?int $enginePowerFrom = null,
        public ?int $enginePowerTo = null,
        public ?int $engineCapacityFrom = null,
        public ?int $engineCapacityTo = null,
        public ?int $accelerationTo = null,
        public ?int $topSpeedFrom = null,

        // Vehicle characteristics
        /** @var BodyType[]|null */
        public ?array $bodyTypes = null,
        /** @var FuelType[]|null */
        public ?array $fuelTypes = null,
        public ?TransmissionType $transmission = null,
        public ?int $gearCount = null,
        public ?string $driveType = null,
        /** @var string[]|null */
        public ?array $colors = null,
        public ?Condition $condition = null,
        public ?int $cylinderCount = null,

        // Location
        public ?string $postalCode = null,
        public ?string $city = null,
        public ?Distance $distance = null,

        // Motorcycle-specific
        public ?DriversLicense $driversLicense = null,

        // BOVAG certifications
        public ?BovagWarranty $warranty = null,
        public ?bool $fullyServiced = null,
        public ?bool $hasBovagChecklist = null,
        public ?bool $hasBovagMaintenanceFree = null,
        public ?bool $hasNapOrBit = null,
        public ?bool $hasBovagImportOdometerCheck = null,
        public ?bool $servicedOnDelivery = null,
        public ?bool $hasNapWeblabel = null,

        // Financial
        public ?bool $vatDeductible = null,
        public ?bool $hideVatExcludedPrices = null,
        public ?bool $isFinanceable = null,
        public ?bool $isImported = null,

        // Dimensions/weight
        public ?int $seatCount = null,
        public ?int $emptyMassTo = null,

        // Search
        public ?string $keywords = null,
        /** @var string[]|null */
        public ?array $accessories = null,
        public ?AvailableSince $availableSince = null,
        public ?bool $inStock = null,
        public ?bool $showCommercialVehicles = null,

        // Pagination
        public int $page = 1,
    ) {
    }
}
```

### Filter → URL Slug Mapping

| Property | URL slug format | Example |
|---|---|---|
| `brand` | `merk-{value}` | `merk-suzuki` |
| `model` | `model-{value}` | `model-gsx-r-1300-hayabusa` |
| `priceFrom` / `priceTo` | `prijs-vanaf-{n}` / `prijs-tot-en-met-{n}` | `prijs-vanaf-500` |
| `yearFrom` / `yearTo` | `bouwjaar-vanaf-{n}` / `bouwjaar-tot-en-met-{n}` | `bouwjaar-vanaf-1970` |
| `modelYearFrom` / `modelYearTo` | `modeljaar-vanaf-{n}` / `modeljaar-tot-en-met-{n}` | `modeljaar-vanaf-2020` |
| `mileageFrom` / `mileageTo` | `kilometerstand-vanaf-{n}` / `kilometerstand-tot-en-met-{n}` | `kilometerstand-vanaf-2500` |
| `enginePowerFrom` / `enginePowerTo` | `vermogen-pk-vanaf-{n}` / `vermogen-pk-tot-en-met-{n}` | `vermogen-pk-vanaf-100` |
| `engineCapacityFrom` / `engineCapacityTo` | `motorinhoud-cc-vanaf-{n}` / `motorinhoud-cc-tot-en-met-{n}` | `motorinhoud-cc-vanaf-600` |
| `bodyTypes[]` | `{value}` (lowercase) | `supersport` |
| `fuelTypes[]` | `brandstof-{value}` | `brandstof-benzine` |
| `transmission` | `transmissie-{value}` | `transmissie-handgeschakeld` |
| `colors[]` | `kleur-{value}` | `kleur-wit` |
| `condition` | `staat-{value}` | `staat-occasion` |
| `driversLicense` | `rijbewijs-{value}` | `rijbewijs-a2` |
| `warranty` | `garantie-{value}` | `garantie-bovag12maanden` |
| `distance` | `afstand-{value}` | `afstand-fifty` |
| `keywords` | `trefwoorden-{value}` | `trefwoorden-abs` |
| `availableSince` | `aangeboden-sinds-{value}` | `aangeboden-sinds-today` |
| `page` | `page={n}` (query param) | `page=2` |

### MobilityType → URL Slug Mapping

| MobilityType | Search URL `mobilityType=` | Detail URL `mobilityType=` |
|---|---|---|
| Motor | `motoren` | `motor` |
| Car | `auto` | `auto` |
| Bicycle | `fietsen` | `fiets` |
| Camper | `camper` | `camper` |

---

## Testing Strategy

### Unit Tests (`tests/ViaBOVAGTest.php`)

- Guzzle `MockHandler` + `HandlerStack` — zero real HTTP
- Test build ID extraction from HTML fixture
- Test search results parsing from JSON fixture
- Test detail parsing from JSON fixture
- Test SearchCriteria → URL parameter mapping
- Test stale build ID retry (first request 404, then success)
- Test cache hit/miss scenarios
- Test error handling (network errors, invalid JSON, missing fields)

### Integration Tests (`tests/IntegrationTest.php`)

- Separate PHPUnit suite (`--testsuite Integration`)
- Fetch data once in `setUpBeforeClass()` to minimize HTTP
- Verify live search returns results with expected structure
- Verify live detail page returns complete data
- Verify build ID extraction works with current live site

### Testing Utilities (`src/Testing/`)

- **FakeViaBOVAG**: in-memory fake with call recording, fluent setters, `shouldThrow()`, assertions
- **Factories**: `make()` with sensible defaults, `makeMany()` for collections

---

## Implementation Order

1. Config files (composer.json dependencies, phpstan.neon.dist, rector.php, phpunit.xml.dist)
2. Exception class (`ViaBOVAGException`)
3. Enums (all 11 string-backed enums)
4. Simple DTOs (`Media`, `Specification`, `SpecificationGroup`, `Accessory`, `OptionGroup`)
5. Core DTOs (`Vehicle`, `Company`, `Listing`)
6. Complex DTOs (`ListingDetail`, `SearchResult`, `SearchCriteria`)
7. `JsonParser` (build ID extraction + JSON → DTO parsing)
8. `ViaBOVAGInterface` + `ViaBOVAG` client
9. Testing utilities (all factories + `FakeViaBOVAG`)
10. Save fixture files from live API responses
11. Unit tests
12. Integration tests
13. Run PHPStan, Rector, Pint — fix all issues
