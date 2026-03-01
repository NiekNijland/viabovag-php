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
        public string $mileageUnit,
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
    ) {}
}
