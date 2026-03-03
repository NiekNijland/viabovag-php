<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

use NiekNijland\ViaBOVAG\Data\Concerns\HasSharedFilterSlugs;
use NiekNijland\ViaBOVAG\Data\Concerns\HasSharedRequestBody;
use NiekNijland\ViaBOVAG\Data\Concerns\HasWithPage;
use NiekNijland\ViaBOVAG\Data\Filters\BicycleSearchFilters;
use NiekNijland\ViaBOVAG\Data\Filters\SharedSearchFilters;

/** @phpstan-consistent-constructor */
readonly class BicycleSearchCriteria implements SearchQuery
{
    use HasSharedFilterSlugs;
    use HasSharedRequestBody;
    use HasWithPage;

    /**
     * @param  BicycleBodyType[]|null  $bodyTypes
     * @param  BicycleFuelType[]|null  $fuelTypes
     * @param  string[]|null  $colors
     * @param  Condition[]|null  $conditions
     * @param  BovagWarranty[]|null  $warranties
     * @param  FilterOption[]|null  $frameMaterials
     * @param  FilterOption[]|null  $brakeTypes
     * @param  FilterOption[]|null  $engineBrands
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

        // Vehicle characteristics
        public ?array $bodyTypes = null,
        public ?array $fuelTypes = null,
        public ?array $colors = null,

        // Location
        public ?string $postalCode = null,
        public ?Distance $distance = null,

        // Bicycle-specific
        public ?int $frameHeightFrom = null,
        public ?int $frameHeightTo = null,
        public ?array $frameMaterials = null,
        public ?array $brakeTypes = null,
        public ?bool $batteryRemovable = null,
        public ?int $batteryCapacityFrom = null,
        public ?int $batteryCapacityTo = null,
        public ?array $engineBrands = null,
        public ?FilterOption $specifiedBatteryRange = null,

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
    ) {
        self::assertValidPage($page);
    }

    public static function fromFilters(
        SharedSearchFilters $shared = new SharedSearchFilters,
        BicycleSearchFilters $filters = new BicycleSearchFilters,
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
            bodyTypes: $filters->bodyTypes,
            fuelTypes: $filters->fuelTypes,
            colors: $shared->colors,
            postalCode: $shared->postalCode,
            distance: $shared->distance,
            frameHeightFrom: $filters->frameHeightFrom,
            frameHeightTo: $filters->frameHeightTo,
            frameMaterials: $filters->frameMaterials,
            brakeTypes: $filters->brakeTypes,
            batteryRemovable: $filters->batteryRemovable,
            batteryCapacityFrom: $filters->batteryCapacityFrom,
            batteryCapacityTo: $filters->batteryCapacityTo,
            engineBrands: $filters->engineBrands,
            specifiedBatteryRange: $filters->specifiedBatteryRange,
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
        );
    }

    public function mobilityType(): MobilityType
    {
        return MobilityType::Bicycle;
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

        // Bicycle-specific filters
        if ($this->frameHeightFrom !== null) {
            $filters[] = 'framehoogte-vanaf-'.$this->frameHeightFrom;
        }

        if ($this->frameHeightTo !== null) {
            $filters[] = 'framehoogte-tot-en-met-'.$this->frameHeightTo;
        }

        foreach ($this->collectFrameMaterialSlugs() as $frameMaterialSlug) {
            $filters[] = 'framemateriaal-'.$frameMaterialSlug;
        }

        foreach ($this->collectBrakeTypeSlugs() as $brakeTypeSlug) {
            $filters[] = 'remtype-'.$brakeTypeSlug;
        }

        if ($this->batteryRemovable === true) {
            $filters[] = 'batterij-verwijderbaar';
        }

        if ($this->batteryCapacityFrom !== null) {
            $filters[] = 'batterijcapaciteit-vanaf-'.$this->batteryCapacityFrom;
        }

        if ($this->batteryCapacityTo !== null) {
            $filters[] = 'batterijcapaciteit-tot-en-met-'.$this->batteryCapacityTo;
        }

        foreach ($this->collectEngineBrandSlugs() as $engineBrandSlug) {
            $filters[] = 'motormerk-'.$engineBrandSlug;
        }

        if ($this->specifiedBatteryRange instanceof FilterOption) {
            $filters[] = 'opgegeven-bereik-'.$this->specifiedBatteryRange->slug;
        }

        return $filters;
    }

    /**
     * @return array<string, mixed>
     */
    public function toRequestBody(): array
    {
        $body = $this->sharedRequestBody();

        if ($this->bodyTypes !== null) {
            $body['BodyType'] = array_map(
                fn (BicycleBodyType $bodyType): string => $bodyType->value,
                $this->bodyTypes,
            );
        }

        if ($this->fuelTypes !== null) {
            $body['FuelType'] = array_map(
                fn (BicycleFuelType $fuelType): string => $fuelType->value,
                $this->fuelTypes,
            );
        }

        // Bicycle-specific filters
        if ($this->frameHeightFrom !== null) {
            $body['FrameHeightFrom'] = $this->frameHeightFrom;
        }

        if ($this->frameHeightTo !== null) {
            $body['FrameHeightTo'] = $this->frameHeightTo;
        }

        $frameMaterials = $this->collectFrameMaterialSlugs();

        if ($frameMaterials !== []) {
            $body['FrameMaterial'] = count($frameMaterials) === 1 ? $frameMaterials[0] : $frameMaterials;
        }

        $brakeTypes = $this->collectBrakeTypeSlugs();

        if ($brakeTypes !== []) {
            $body['BrakeType'] = count($brakeTypes) === 1 ? $brakeTypes[0] : $brakeTypes;
        }

        if ($this->batteryRemovable === true) {
            $body['BatteryRemovable'] = true;
        }

        if ($this->batteryCapacityFrom !== null) {
            $body['BatteryCapacityFrom'] = $this->batteryCapacityFrom;
        }

        if ($this->batteryCapacityTo !== null) {
            $body['BatteryCapacityTo'] = $this->batteryCapacityTo;
        }

        $engineBrands = $this->collectEngineBrandSlugs();

        if ($engineBrands !== []) {
            $body['EngineBrand'] = count($engineBrands) === 1 ? $engineBrands[0] : $engineBrands;
        }

        if ($this->specifiedBatteryRange instanceof FilterOption) {
            $body['SpecifiedBatteryRange'] = $this->specifiedBatteryRange->slug;
        }

        return $body;
    }

    /**
     * @return string[]
     */
    private function collectFrameMaterialSlugs(): array
    {
        return $this->collectFilterOptionSlugs($this->frameMaterials);
    }

    /**
     * @return string[]
     */
    private function collectBrakeTypeSlugs(): array
    {
        return $this->collectFilterOptionSlugs($this->brakeTypes);
    }

    /**
     * @return string[]
     */
    private function collectEngineBrandSlugs(): array
    {
        return $this->collectFilterOptionSlugs($this->engineBrands);
    }

    /**
     * @param  FilterOption[]|null  $options
     * @return string[]
     */
    private function collectFilterOptionSlugs(?array $options): array
    {
        if ($options === null) {
            return [];
        }

        return array_values(array_unique(array_map(
            fn (FilterOption $option): string => $option->slug,
            $options,
        )));
    }
}
