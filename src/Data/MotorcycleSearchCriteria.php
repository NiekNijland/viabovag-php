<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

use InvalidArgumentException;
use NiekNijland\ViaBOVAG\Data\Concerns\HasSharedFilterSlugs;
use NiekNijland\ViaBOVAG\Data\Concerns\HasSharedRequestBody;
use NiekNijland\ViaBOVAG\Data\Concerns\HasWithPage;

/** @phpstan-consistent-constructor */
readonly class MotorcycleSearchCriteria implements SearchQuery
{
    use HasSharedFilterSlugs;
    use HasSharedRequestBody;
    use HasWithPage;

    /**
     * @param  MotorcycleBodyType[]|null  $bodyTypes
     * @param  MotorcycleFuelType[]|null  $fuelTypes
     * @param  string[]|null  $colors
     * @param  Condition[]|null  $conditions
     * @param  BovagWarranty[]|null  $warranties
     * @param  TransmissionType[]|null  $transmissions
     * @param  DriversLicense[]|null  $driversLicenses
     * @param  FilterOption[]|null  $accessories
     * @param  FilterOption[]|null  $frameTypes
     */
    public function __construct(
        // Core
        public ?Brand $brand = null,
        public ?Model $model = null,
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
        public ?array $bodyTypes = null,
        public ?array $fuelTypes = null,
        public ?TransmissionType $transmission = null,
        public ?array $colors = null,
        public ?Condition $condition = null,
        public ?FilterOption $accessory = null,
        public ?FilterOption $frameType = null,

        // Location
        public ?string $postalCode = null,
        public ?Distance $distance = null,

        // Motorcycle-specific
        public ?DriversLicense $driversLicense = null,

        // BOVAG certifications
        public ?BovagWarranty $warranty = null,
        public ?bool $fullyServiced = null,
        public ?bool $hasBovagChecklist = null,
        public ?bool $hasBovagMaintenanceFree = null,
        public ?bool $hasBovagImportOdometerCheck = null,
        public ?bool $servicedOnDelivery = null,
        public ?bool $hasNapWeblabel = null,

        // Financial
        public ?bool $vatDeductible = null,
        public ?bool $isFinanceable = null,
        public ?bool $isImported = null,

        // Search
        public ?string $keywords = null,
        public ?AvailableSince $availableSince = null,

        // Sorting
        public ?SortOrder $sortOrder = null,

        // Pagination
        public int $page = 1,

        // Multi-select
        public ?array $conditions = null,
        public ?array $warranties = null,
        public ?array $transmissions = null,
        public ?array $driversLicenses = null,
        public ?array $accessories = null,
        public ?array $frameTypes = null,
    ) {
        self::assertValidPage($page);
        self::assertNoFrameTypeFilters($frameType, $frameTypes);
    }

    public function mobilityType(): MobilityType
    {
        return MobilityType::Motorcycle;
    }

    public function page(): int
    {
        return $this->page;
    }

    /**
     * @return string[]
     */
    public function toFilterSlugs(): array
    {
        $filters = $this->sharedFilterSlugs();

        if ($this->engineCapacityFrom !== null) {
            $filters[] = 'motorinhoud-cc-vanaf-'.$this->engineCapacityFrom;
        }

        if ($this->engineCapacityTo !== null) {
            $filters[] = 'motorinhoud-cc-tot-en-met-'.$this->engineCapacityTo;
        }

        if ($this->bodyTypes !== null) {
            foreach ($this->bodyTypes as $bodyType) {
                $filters[] = $bodyType->slug();
            }
        }

        if ($this->fuelTypes !== null) {
            foreach ($this->fuelTypes as $fuelType) {
                $filters[] = $fuelType->slug();
            }
        }

        if ($this->transmission instanceof TransmissionType) {
            $filters[] = $this->transmission->slug();
        }

        if ($this->transmissions !== null) {
            foreach ($this->transmissions as $transmission) {
                $filters[] = $transmission->slug();
            }
        }

        if ($this->driversLicense instanceof DriversLicense) {
            $filters[] = $this->driversLicense->slug();
        }

        if ($this->driversLicenses !== null) {
            foreach ($this->driversLicenses as $driversLicense) {
                $filters[] = $driversLicense->slug();
            }
        }

        $accessories = [];

        if ($this->accessory instanceof FilterOption) {
            $accessories[] = $this->accessory->slug;
        }

        if ($this->accessories !== null) {
            foreach ($this->accessories as $accessory) {
                $accessories[] = $accessory->slug;
            }
        }

        $accessories = array_values(array_unique($accessories));

        foreach ($accessories as $accessory) {
            $filters[] = $accessory;
        }

        if ($this->accelerationTo !== null) {
            $filters[] = 'acceleratie-tot-en-met-'.$this->accelerationTo;
        }

        if ($this->topSpeedFrom !== null) {
            $filters[] = 'topsnelheid-vanaf-'.$this->topSpeedFrom;
        }

        return $filters;
    }

    /**
     * @return array<string, mixed>
     */
    public function toRequestBody(): array
    {
        $body = $this->sharedRequestBody();

        if ($this->engineCapacityFrom !== null) {
            $body['EngineCapacityFrom'] = $this->engineCapacityFrom;
        }

        if ($this->engineCapacityTo !== null) {
            $body['EngineCapacityTo'] = $this->engineCapacityTo;
        }

        if ($this->bodyTypes !== null) {
            $body['BodyType'] = array_map(
                fn (MotorcycleBodyType $bodyType): string => $bodyType->value,
                $this->bodyTypes,
            );
        }

        if ($this->fuelTypes !== null) {
            $body['FuelType'] = array_map(
                fn (MotorcycleFuelType $fuelType): string => $fuelType->value,
                $this->fuelTypes,
            );
        }

        $transmissions = [];

        if ($this->transmission instanceof TransmissionType) {
            $transmissions[] = $this->transmission->value;
        }

        if ($this->transmissions !== null) {
            foreach ($this->transmissions as $transmission) {
                $transmissions[] = $transmission->value;
            }
        }

        $transmissions = array_values(array_unique($transmissions));

        if ($transmissions !== []) {
            $body['Transmission'] = $transmissions;
        }

        $driversLicenses = [];

        if ($this->driversLicense instanceof DriversLicense) {
            $driversLicenses[] = $this->driversLicense->value;
        }

        if ($this->driversLicenses !== null) {
            foreach ($this->driversLicenses as $driversLicense) {
                $driversLicenses[] = $driversLicense->value;
            }
        }

        $driversLicenses = array_values(array_unique($driversLicenses));

        if ($driversLicenses !== []) {
            $body['DriversLicense'] = $driversLicenses;
        }

        $accessories = [];

        if ($this->accessory instanceof FilterOption) {
            $accessories[] = $this->accessory->slug;
        }

        if ($this->accessories !== null) {
            foreach ($this->accessories as $accessory) {
                $accessories[] = $accessory->slug;
            }
        }

        $accessories = array_values(array_unique($accessories));

        if ($accessories !== []) {
            $body['Accessory'] = $accessories;
        }

        if ($this->accelerationTo !== null) {
            $body['AccelerationTo'] = $this->accelerationTo;
        }

        if ($this->topSpeedFrom !== null) {
            $body['TopSpeedFrom'] = $this->topSpeedFrom;
        }

        return $body;
    }

    /**
     * @param  FilterOption[]|null  $frameTypes
     */
    private static function assertNoFrameTypeFilters(?FilterOption $frameType, ?array $frameTypes): void
    {
        if ($frameType instanceof FilterOption || ($frameTypes !== null && $frameTypes !== [])) {
            throw new InvalidArgumentException('FrameType filters are not supported for motorcycles. Use bodyTypes (category) instead.');
        }
    }
}
