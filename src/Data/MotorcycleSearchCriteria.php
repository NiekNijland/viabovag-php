<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

use NiekNijland\ViaBOVAG\Data\Concerns\HasSharedFilterSlugs;
use NiekNijland\ViaBOVAG\Data\Concerns\HasSharedRequestBody;
use NiekNijland\ViaBOVAG\Data\Concerns\HasWithPage;
use NiekNijland\ViaBOVAG\Data\Filters\MotorcycleSearchFilters;
use NiekNijland\ViaBOVAG\Data\Filters\SharedSearchFilters;

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
        public ?array $colors = null,

        // Location
        public ?string $postalCode = null,
        public ?Distance $distance = null,

        // BOVAG certifications
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
    ) {
        self::assertValidPage($page);
    }

    public static function fromFilters(
        SharedSearchFilters $shared = new SharedSearchFilters,
        MotorcycleSearchFilters $filters = new MotorcycleSearchFilters,
        int $page = 1,
    ): self {
        return new self(
            brand: $shared->brand,
            model: $shared->model,
            modelKeywords: $shared->modelKeywords,
            priceFrom: $shared->priceFrom,
            priceTo: $shared->priceTo,
            leasePriceFrom: $shared->leasePriceFrom,
            leasePriceTo: $shared->leasePriceTo,
            yearFrom: $shared->yearFrom,
            yearTo: $shared->yearTo,
            modelYearFrom: $shared->modelYearFrom,
            modelYearTo: $shared->modelYearTo,
            mileageFrom: $shared->mileageFrom,
            mileageTo: $shared->mileageTo,
            enginePowerFrom: $shared->enginePowerFrom,
            enginePowerTo: $shared->enginePowerTo,
            engineCapacityFrom: $filters->engineCapacityFrom,
            engineCapacityTo: $filters->engineCapacityTo,
            accelerationTo: $filters->accelerationTo,
            topSpeedFrom: $filters->topSpeedFrom,
            bodyTypes: $filters->bodyTypes,
            fuelTypes: $filters->fuelTypes,
            colors: $shared->colors,
            postalCode: $shared->postalCode,
            distance: $shared->distance,
            fullyServiced: $shared->fullyServiced,
            hasBovagChecklist: $shared->hasBovagChecklist,
            hasBovagMaintenanceFree: $shared->hasBovagMaintenanceFree,
            hasBovagImportOdometerCheck: $shared->hasBovagImportOdometerCheck,
            servicedOnDelivery: $shared->servicedOnDelivery,
            hasNapWeblabel: $shared->hasNapWeblabel,
            vatDeductible: $shared->vatDeductible,
            isFinanceable: $shared->isFinanceable,
            isImported: $shared->isImported,
            keywords: $shared->keywords,
            availableSince: $shared->availableSince,
            sortOrder: $shared->sortOrder,
            page: $page,
            conditions: $shared->conditions,
            warranties: $shared->warranties,
            transmissions: $filters->transmissions,
            driversLicenses: $filters->driversLicenses,
            accessories: $filters->accessories,
        );
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

        foreach ($this->collectTransmissionSlugs() as $transmissionSlug) {
            $filters[] = $transmissionSlug;
        }

        foreach ($this->collectDriversLicenseSlugs() as $driversLicenseSlug) {
            $filters[] = $driversLicenseSlug;
        }

        foreach ($this->collectAccessorySlugs() as $accessorySlug) {
            $filters[] = $accessorySlug;
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

        $transmissions = $this->collectTransmissionValues();

        if ($transmissions !== []) {
            $body['Transmission'] = $transmissions;
        }

        $driversLicenses = $this->collectDriversLicenseValues();

        if ($driversLicenses !== []) {
            $body['DriversLicense'] = $driversLicenses;
        }

        $accessories = $this->collectAccessorySlugs();

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
     * @return string[]
     */
    private function collectTransmissionSlugs(): array
    {
        if ($this->transmissions === null) {
            return [];
        }

        return array_values(array_unique(array_map(
            fn (TransmissionType $transmission): string => $transmission->slug(),
            $this->transmissions,
        )));
    }

    /**
     * @return string[]
     */
    private function collectTransmissionValues(): array
    {
        if ($this->transmissions === null) {
            return [];
        }

        return array_values(array_unique(array_map(
            fn (TransmissionType $transmission): string => $transmission->value,
            $this->transmissions,
        )));
    }

    /**
     * @return string[]
     */
    private function collectDriversLicenseSlugs(): array
    {
        if ($this->driversLicenses === null) {
            return [];
        }

        return array_values(array_unique(array_map(
            fn (DriversLicense $driversLicense): string => $driversLicense->slug(),
            $this->driversLicenses,
        )));
    }

    /**
     * @return string[]
     */
    private function collectDriversLicenseValues(): array
    {
        if ($this->driversLicenses === null) {
            return [];
        }

        return array_values(array_unique(array_map(
            fn (DriversLicense $driversLicense): string => $driversLicense->value,
            $this->driversLicenses,
        )));
    }

    /**
     * @return string[]
     */
    private function collectAccessorySlugs(): array
    {
        if ($this->accessories === null) {
            return [];
        }

        return array_values(array_unique(array_map(
            fn (FilterOption $accessory): string => $accessory->slug,
            $this->accessories,
        )));
    }
}
