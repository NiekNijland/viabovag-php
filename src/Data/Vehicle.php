<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

readonly class Vehicle
{
    /**
     * @param  string[]  $fuelTypes
     * @param  string[]  $warranties
     * @param  string[]  $certaintyKeys
     */
    public function __construct(
        public string $type,
        public string $brand,
        public string $model,
        public int $mileage,
        public MileageUnit $mileageUnit,
        public int $year,
        public ?int $month,
        public array $fuelTypes,
        public ?string $color,
        public ?string $bodyType,
        public ?string $transmissionType,
        public ?int $engineCapacity,
        public ?int $enginePower,
        public array $warranties,
        public array $certaintyKeys,
        public bool $fullyServiced,
        public bool $hasBovagChecklist,
        public ?string $bovagWarranty,
        public bool $hasReturnWarranty,
        public bool $servicedOnDelivery,

        // Extended fields (populated from detail responses)
        public ?string $edition = null,
        public ?string $condition = null,
        public ?int $modelYear = null,
        public ?string $frameType = null,
        public ?string $primaryFuelType = null,
        public ?string $secondaryFuelType = null,
        public ?bool $isHybridVehicle = null,
        public ?string $energyLabel = null,
        public ?string $fuelConsumptionCombined = null,
        public ?int $gearCount = null,
        public ?bool $isImported = null,
        public ?bool $hasNapLabel = null,
        public ?string $wheelSize = null,
        public ?int $emptyWeight = null,
        public ?int $maxWeight = null,
        public ?int $bedCount = null,
        public ?string $sanitary = null,
    ) {}
}
