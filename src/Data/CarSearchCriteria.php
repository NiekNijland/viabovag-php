<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

use NiekNijland\ViaBOVAG\Data\Concerns\HasSharedFilterSlugs;
use NiekNijland\ViaBOVAG\Data\Concerns\HasWithPage;

/** @phpstan-consistent-constructor */
readonly class CarSearchCriteria implements SearchQuery
{
    use HasSharedFilterSlugs;
    use HasWithPage;

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
        public ?FilterOption $city = null,

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
        public ?bool $isLeaseable = null,

        // Dimensions/weight
        public ?array $seatCounts = null,
        public ?int $emptyMassTo = null,
        public ?int $doorCountFrom = null,
        public ?int $doorCountTo = null,
        public ?int $wheelSizeFrom = null,
        public ?int $wheelSizeTo = null,
        public ?int $brakedTowingWeightFrom = null,
        public ?int $brakedTowingWeightTo = null,
        public ?int $maximumMassTo = null,

        // EV / Hybrid
        public ?int $batteryCapacityFrom = null,
        public ?int $batteryCapacityTo = null,
        public ?int $maxChargingPowerHome = null,
        public ?int $maxQuickChargingPower = null,
        public ?bool $isPluginHybrid = null,
        public ?FilterOption $energyLabel = null,
        public ?FilterOption $specifiedBatteryRange = null,

        // Search
        public ?string $keywords = null,
        public ?array $accessories = null,
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

        // Car-specific filters
        if ($this->city instanceof FilterOption) {
            $filters[] = 'stad-'.$this->city->slug;
        }

        if ($this->isLeaseable === true) {
            $filters[] = 'online-te-leasen';
        }

        if ($this->doorCountFrom !== null) {
            $filters[] = 'deuren-vanaf-'.$this->doorCountFrom;
        }

        if ($this->doorCountTo !== null) {
            $filters[] = 'deuren-tot-en-met-'.$this->doorCountTo;
        }

        if ($this->wheelSizeFrom !== null) {
            $filters[] = 'wielmaat-vanaf-'.$this->wheelSizeFrom;
        }

        if ($this->wheelSizeTo !== null) {
            $filters[] = 'wielmaat-tot-en-met-'.$this->wheelSizeTo;
        }

        if ($this->brakedTowingWeightFrom !== null) {
            $filters[] = 'geremde-aanhangermassa-vanaf-'.$this->brakedTowingWeightFrom;
        }

        if ($this->brakedTowingWeightTo !== null) {
            $filters[] = 'geremde-aanhangermassa-tot-en-met-'.$this->brakedTowingWeightTo;
        }

        if ($this->maximumMassTo !== null) {
            $filters[] = 'maximale-massa-tot-en-met-'.$this->maximumMassTo;
        }

        // EV / Hybrid filters
        if ($this->batteryCapacityFrom !== null) {
            $filters[] = 'batterijcapaciteit-vanaf-'.$this->batteryCapacityFrom;
        }

        if ($this->batteryCapacityTo !== null) {
            $filters[] = 'batterijcapaciteit-tot-en-met-'.$this->batteryCapacityTo;
        }

        if ($this->maxChargingPowerHome !== null) {
            $filters[] = 'max-laadvermogen-thuis-vanaf-'.$this->maxChargingPowerHome;
        }

        if ($this->maxQuickChargingPower !== null) {
            $filters[] = 'max-snellaadvermogen-vanaf-'.$this->maxQuickChargingPower;
        }

        if ($this->isPluginHybrid === true) {
            $filters[] = 'plug-in-hybride';
        }

        if ($this->energyLabel instanceof FilterOption) {
            $filters[] = 'energielabel-'.$this->energyLabel->slug;
        }

        if ($this->specifiedBatteryRange instanceof FilterOption) {
            $filters[] = 'opgegeven-bereik-'.$this->specifiedBatteryRange->slug;
        }

        return $filters;
    }
}
