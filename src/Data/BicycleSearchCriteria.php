<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

use NiekNijland\ViaBOVAG\Data\Concerns\HasSharedFilterSlugs;
use NiekNijland\ViaBOVAG\Data\Concerns\HasSharedRequestBody;
use NiekNijland\ViaBOVAG\Data\Concerns\HasWithPage;

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
     * @param  FilterOption[]|null  $specifiedBatteryRanges
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
        public ?Condition $condition = null,

        // Location
        public ?string $postalCode = null,
        public ?Distance $distance = null,

        // Bicycle-specific
        public ?int $frameHeightFrom = null,
        public ?int $frameHeightTo = null,
        public ?FilterOption $frameMaterial = null,
        public ?array $frameMaterials = null,
        public ?FilterOption $brakeType = null,
        public ?array $brakeTypes = null,
        public ?bool $batteryRemovable = null,
        public ?int $batteryCapacityFrom = null,
        public ?int $batteryCapacityTo = null,
        public ?FilterOption $engineBrand = null,
        public ?array $engineBrands = null,
        public ?FilterOption $specifiedBatteryRange = null,
        public ?array $specifiedBatteryRanges = null,

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
    ) {
        self::assertValidPage($page);
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

        if ($this->frameMaterial instanceof FilterOption) {
            $filters[] = 'framemateriaal-'.$this->frameMaterial->slug;
        }

        if ($this->frameMaterials !== null) {
            foreach ($this->frameMaterials as $frameMaterial) {
                $filters[] = 'framemateriaal-'.$frameMaterial->slug;
            }
        }

        if ($this->brakeType instanceof FilterOption) {
            $filters[] = 'remtype-'.$this->brakeType->slug;
        }

        if ($this->brakeTypes !== null) {
            foreach ($this->brakeTypes as $brakeType) {
                $filters[] = 'remtype-'.$brakeType->slug;
            }
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

        if ($this->engineBrand instanceof FilterOption) {
            $filters[] = 'motormerk-'.$this->engineBrand->slug;
        }

        if ($this->engineBrands !== null) {
            foreach ($this->engineBrands as $engineBrand) {
                $filters[] = 'motormerk-'.$engineBrand->slug;
            }
        }

        if ($this->specifiedBatteryRange instanceof FilterOption) {
            $filters[] = 'opgegeven-bereik-'.$this->specifiedBatteryRange->slug;
        }

        if ($this->specifiedBatteryRanges !== null) {
            foreach ($this->specifiedBatteryRanges as $specifiedBatteryRange) {
                $filters[] = 'opgegeven-bereik-'.$specifiedBatteryRange->slug;
            }
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

        $frameMaterials = [];

        if ($this->frameMaterial instanceof FilterOption) {
            $frameMaterials[] = $this->frameMaterial->slug;
        }

        if ($this->frameMaterials !== null) {
            foreach ($this->frameMaterials as $frameMaterial) {
                $frameMaterials[] = $frameMaterial->slug;
            }
        }

        $frameMaterials = array_values(array_unique($frameMaterials));

        if ($frameMaterials !== []) {
            $body['FrameMaterial'] = count($frameMaterials) === 1 ? $frameMaterials[0] : $frameMaterials;
        }

        $brakeTypes = [];

        if ($this->brakeType instanceof FilterOption) {
            $brakeTypes[] = $this->brakeType->slug;
        }

        if ($this->brakeTypes !== null) {
            foreach ($this->brakeTypes as $brakeType) {
                $brakeTypes[] = $brakeType->slug;
            }
        }

        $brakeTypes = array_values(array_unique($brakeTypes));

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

        $engineBrands = [];

        if ($this->engineBrand instanceof FilterOption) {
            $engineBrands[] = $this->engineBrand->slug;
        }

        if ($this->engineBrands !== null) {
            foreach ($this->engineBrands as $engineBrand) {
                $engineBrands[] = $engineBrand->slug;
            }
        }

        $engineBrands = array_values(array_unique($engineBrands));

        if ($engineBrands !== []) {
            $body['EngineBrand'] = count($engineBrands) === 1 ? $engineBrands[0] : $engineBrands;
        }

        $specifiedBatteryRanges = [];

        if ($this->specifiedBatteryRange instanceof FilterOption) {
            $specifiedBatteryRanges[] = $this->specifiedBatteryRange->slug;
        }

        if ($this->specifiedBatteryRanges !== null) {
            foreach ($this->specifiedBatteryRanges as $specifiedBatteryRange) {
                $specifiedBatteryRanges[] = $specifiedBatteryRange->slug;
            }
        }

        $specifiedBatteryRanges = array_values(array_unique($specifiedBatteryRanges));

        if ($specifiedBatteryRanges !== []) {
            $body['SpecifiedBatteryRange'] = count($specifiedBatteryRanges) === 1
                ? $specifiedBatteryRanges[0]
                : $specifiedBatteryRanges;
        }

        return $body;
    }
}
