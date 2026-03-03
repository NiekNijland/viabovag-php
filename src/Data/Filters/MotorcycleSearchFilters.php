<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data\Filters;

use NiekNijland\ViaBOVAG\Data\DriversLicense;
use NiekNijland\ViaBOVAG\Data\FilterOption;
use NiekNijland\ViaBOVAG\Data\MotorcycleBodyType;
use NiekNijland\ViaBOVAG\Data\MotorcycleFuelType;
use NiekNijland\ViaBOVAG\Data\TransmissionType;

readonly class MotorcycleSearchFilters
{
    /**
     * @param  MotorcycleBodyType[]|null  $bodyTypes
     * @param  MotorcycleFuelType[]|null  $fuelTypes
     * @param  TransmissionType[]|null  $transmissions
     * @param  DriversLicense[]|null  $driversLicenses
     * @param  FilterOption[]|null  $accessories
     */
    public function __construct(
        public ?int $engineCapacityFrom = null,
        public ?int $engineCapacityTo = null,
        public ?int $accelerationTo = null,
        public ?int $topSpeedFrom = null,
        public ?array $bodyTypes = null,
        public ?array $fuelTypes = null,
        public ?array $transmissions = null,
        public ?array $driversLicenses = null,
        public ?array $accessories = null,
    ) {}
}
