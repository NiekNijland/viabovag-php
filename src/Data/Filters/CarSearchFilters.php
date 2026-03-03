<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data\Filters;

use NiekNijland\ViaBOVAG\Data\AccessoryFilter;
use NiekNijland\ViaBOVAG\Data\CarBodyType;
use NiekNijland\ViaBOVAG\Data\CarFuelType;
use NiekNijland\ViaBOVAG\Data\CylinderCount;
use NiekNijland\ViaBOVAG\Data\DriveType;
use NiekNijland\ViaBOVAG\Data\FilterOption;
use NiekNijland\ViaBOVAG\Data\GearCount;
use NiekNijland\ViaBOVAG\Data\SeatCount;
use NiekNijland\ViaBOVAG\Data\TransmissionType;

readonly class CarSearchFilters
{
    /**
     * @param  CarBodyType[]|null  $bodyTypes
     * @param  CarFuelType[]|null  $fuelTypes
     * @param  TransmissionType[]|null  $transmissions
     * @param  GearCount[]|null  $gearCounts
     * @param  CylinderCount[]|null  $cylinderCounts
     * @param  SeatCount[]|null  $seatCounts
     * @param  DriveType[]|null  $driveTypes
     * @param  AccessoryFilter[]|null  $accessories
     * @param  FilterOption[]|null  $cities
     * @param  FilterOption[]|null  $energyLabels
     */
    public function __construct(
        public ?int $engineCapacityFrom = null,
        public ?int $engineCapacityTo = null,
        public ?int $accelerationTo = null,
        public ?int $topSpeedFrom = null,
        public ?array $bodyTypes = null,
        public ?array $fuelTypes = null,
        public ?array $transmissions = null,
        public ?array $gearCounts = null,
        public ?array $cylinderCounts = null,
        public ?array $seatCounts = null,
        public ?array $driveTypes = null,
        public ?array $accessories = null,
        public ?int $emptyMassTo = null,
        public ?array $cities = null,
        public ?bool $isLeaseable = null,
        public ?int $doorCountFrom = null,
        public ?int $doorCountTo = null,
        public ?int $wheelSizeFrom = null,
        public ?int $wheelSizeTo = null,
        public ?int $brakedTowingWeightFrom = null,
        public ?int $brakedTowingWeightTo = null,
        public ?int $maximumMassTo = null,
        public ?int $batteryCapacityFrom = null,
        public ?int $batteryCapacityTo = null,
        public ?int $maxChargingPowerHome = null,
        public ?int $maxQuickChargingPower = null,
        public ?bool $isPluginHybrid = null,
        public ?array $energyLabels = null,
        public ?FilterOption $specifiedBatteryRange = null,
    ) {}
}
