<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data\Filters;

use NiekNijland\ViaBOVAG\Data\FilterOption;
use NiekNijland\ViaBOVAG\Data\TransmissionType;

readonly class CamperSearchFilters
{
    /**
     * @param  TransmissionType[]|null  $transmissions
     * @param  FilterOption[]|null  $bedLayouts
     * @param  FilterOption[]|null  $seatingLayouts
     * @param  FilterOption[]|null  $sanitaryLayouts
     * @param  FilterOption[]|null  $kitchenLayouts
     * @param  FilterOption[]|null  $camperChassisBrands
     */
    public function __construct(
        public ?int $engineCapacityFrom = null,
        public ?int $engineCapacityTo = null,
        public ?array $transmissions = null,
        public ?int $bedCount = null,
        public ?array $bedLayouts = null,
        public ?array $seatingLayouts = null,
        public ?array $sanitaryLayouts = null,
        public ?array $kitchenLayouts = null,
        public ?int $interiorHeightFrom = null,
        public ?array $camperChassisBrands = null,
        public ?int $maximumMassTo = null,
    ) {}
}
