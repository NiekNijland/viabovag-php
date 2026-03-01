<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

use NiekNijland\ViaBOVAG\Data\Concerns\HasSharedFilterSlugs;

readonly class CarSearchCriteria implements SearchQuery
{
    use HasSharedFilterSlugs;

    /**
     * @param  CarBodyType[]|null  $bodyTypes
     * @param  CarFuelType[]|null  $fuelTypes
     * @param  string[]|null  $colors
     * @param  GearCount[]|null  $gearCounts
     * @param  CylinderCount[]|null  $cylinderCounts
     * @param  SeatCount[]|null  $seatCounts
     * @param  DriveType[]|null  $driveTypes
     * @param  AccessoryFilter[]|null  $accessories
     */
    public function __construct(
        // Core
        public ?string $brand = null,
        public ?string $model = null,
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
        public ?TransmissionType $transmission = null,
        public ?array $gearCounts = null,
        public ?array $driveTypes = null,
        public ?array $colors = null,
        public ?Condition $condition = null,
        public ?array $cylinderCounts = null,

        // Location
        public ?string $postalCode = null,
        public ?Distance $distance = null,

        // BOVAG certifications
        public ?BovagWarranty $warranty = null,
        public ?bool $fullyServiced = null,
        public ?bool $hasBovagChecklist = null,
        public ?bool $hasBovagMaintenanceFree = null,
        public ?bool $hasBovagImportOdometerCheck = null,
        public ?bool $carServicedOnDelivery = null,
        public ?bool $hasNapWeblabel = null,

        // Financial
        public ?bool $vatDeductible = null,
        public ?bool $isFinanceable = null,
        public ?bool $isImported = null,

        // Dimensions/weight
        public ?array $seatCounts = null,
        public ?int $emptyMassTo = null,

        // Search
        public ?string $keywords = null,
        public ?array $accessories = null,
        public ?AvailableSince $availableSince = null,

        // Pagination
        public int $page = 1,
    ) {}

    public function mobilityType(): MobilityType
    {
        return MobilityType::Car;
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

        if ($this->transmission instanceof TransmissionType) {
            $filters[] = $this->transmission->slug();
        }

        if ($this->gearCounts !== null) {
            foreach ($this->gearCounts as $gearCount) {
                $filters[] = $gearCount->slug();
            }
        }

        if ($this->cylinderCounts !== null) {
            foreach ($this->cylinderCounts as $cylinderCount) {
                $filters[] = $cylinderCount->slug();
            }
        }

        if ($this->seatCounts !== null) {
            foreach ($this->seatCounts as $seatCount) {
                $filters[] = $seatCount->slug();
            }
        }

        if ($this->driveTypes !== null) {
            foreach ($this->driveTypes as $driveType) {
                $filters[] = $driveType->slug();
            }
        }

        if ($this->accessories !== null) {
            foreach ($this->accessories as $accessory) {
                $filters[] = $accessory->slug();
            }
        }

        if ($this->accelerationTo !== null) {
            $filters[] = 'acceleratie-tot-en-met-'.$this->accelerationTo;
        }

        if ($this->topSpeedFrom !== null) {
            $filters[] = 'topsnelheid-vanaf-'.$this->topSpeedFrom;
        }

        if ($this->emptyMassTo !== null) {
            $filters[] = 'gewicht-tot-en-met-'.$this->emptyMassTo;
        }

        return $filters;
    }
}
