<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data\Filters;

use NiekNijland\ViaBOVAG\Data\BicycleBodyType;
use NiekNijland\ViaBOVAG\Data\BicycleFuelType;
use NiekNijland\ViaBOVAG\Data\FilterOption;

readonly class BicycleSearchFilters
{
    /**
     * @param  BicycleBodyType[]|null  $bodyTypes
     * @param  BicycleFuelType[]|null  $fuelTypes
     * @param  FilterOption[]|null  $frameMaterials
     * @param  FilterOption[]|null  $brakeTypes
     * @param  FilterOption[]|null  $engineBrands
     */
    public function __construct(
        public ?array $bodyTypes = null,
        public ?array $fuelTypes = null,
        public ?int $frameHeightFrom = null,
        public ?int $frameHeightTo = null,
        public ?array $frameMaterials = null,
        public ?array $brakeTypes = null,
        public ?bool $batteryRemovable = null,
        public ?int $batteryCapacityFrom = null,
        public ?int $batteryCapacityTo = null,
        public ?array $engineBrands = null,
        public ?FilterOption $specifiedBatteryRange = null,
    ) {}
}
