<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

use NiekNijland\ViaBOVAG\Data\Concerns\HasSharedFilterSlugs;
use NiekNijland\ViaBOVAG\Data\Concerns\HasWithPage;

/** @phpstan-consistent-constructor */
readonly class BicycleSearchCriteria implements SearchQuery
{
    use HasSharedFilterSlugs;
    use HasWithPage;

    /**
     * @param  BicycleBodyType[]|null  $bodyTypes
     * @param  BicycleFuelType[]|null  $fuelTypes
     * @param  string[]|null  $colors
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
        public ?FilterOption $brakeType = null,
        public ?bool $batteryRemovable = null,
        public ?int $batteryCapacityFrom = null,
        public ?int $batteryCapacityTo = null,
        public ?FilterOption $engineBrand = null,
        public ?FilterOption $specifiedBatteryRange = null,

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

        if ($this->brakeType instanceof FilterOption) {
            $filters[] = 'remtype-'.$this->brakeType->slug;
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

        if ($this->specifiedBatteryRange instanceof FilterOption) {
            $filters[] = 'opgegeven-bereik-'.$this->specifiedBatteryRange->slug;
        }

        return $filters;
    }
}
