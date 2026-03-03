<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

use NiekNijland\ViaBOVAG\Data\Concerns\HasSharedFilterSlugs;
use NiekNijland\ViaBOVAG\Data\Concerns\HasSharedRequestBody;
use NiekNijland\ViaBOVAG\Data\Concerns\HasWithPage;

/** @phpstan-consistent-constructor */
readonly class CarSearchCriteria implements SearchQuery
{
    use HasSharedFilterSlugs;
    use HasSharedRequestBody;
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
     * @param  Condition[]|null  $conditions
     * @param  BovagWarranty[]|null  $warranties
     * @param  TransmissionType[]|null  $transmissions
     * @param  FilterOption[]|null  $cities
     * @param  FilterOption[]|null  $energyLabels
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
        public ?array $cities = null,

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
        public ?array $energyLabels = null,
        public ?array $specifiedBatteryRanges = null,

        // Search
        public ?string $keywords = null,
        public ?array $accessories = null,
        public ?AvailableSince $availableSince = null,

        // Sorting
        public ?SortOrder $sortOrder = null,

        // Pagination
        public int $page = 1,

        // Multi-select
        public ?array $conditions = null,
        public ?array $warranties = null,
        public ?array $transmissions = null,
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

        if ($this->transmissions !== null) {
            foreach ($this->transmissions as $transmission) {
                $filters[] = $transmission->slug();
            }
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

        if ($this->cities !== null) {
            foreach ($this->cities as $city) {
                $filters[] = 'stad-'.$city->slug;
            }
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

        if ($this->energyLabels !== null) {
            foreach ($this->energyLabels as $energyLabel) {
                $filters[] = 'energielabel-'.$energyLabel->slug;
            }
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
            $filters[] = 'opgegeven-bereik-'.$specifiedBatteryRanges[0];
        }

        return $filters;
    }

    /**
     * @return array<string, mixed>
     */
    public function toRequestBody(): array
    {
        $body = $this->sharedRequestBody();

        if ($this->engineCapacityFrom !== null) {
            $body['EngineCapacityFrom'] = $this->engineCapacityFrom;
        }

        if ($this->engineCapacityTo !== null) {
            $body['EngineCapacityTo'] = $this->engineCapacityTo;
        }

        if ($this->bodyTypes !== null) {
            $body['BodyType'] = array_map(
                fn (CarBodyType $bodyType): string => $bodyType->value,
                $this->bodyTypes,
            );
        }

        if ($this->fuelTypes !== null) {
            $body['FuelType'] = array_map(
                fn (CarFuelType $fuelType): string => $fuelType->value,
                $this->fuelTypes,
            );
        }

        $transmissions = [];

        if ($this->transmission instanceof TransmissionType) {
            $transmissions[] = $this->transmission->value;
        }

        if ($this->transmissions !== null) {
            foreach ($this->transmissions as $transmission) {
                $transmissions[] = $transmission->value;
            }
        }

        $transmissions = array_values(array_unique($transmissions));

        if ($transmissions !== []) {
            $body['Transmission'] = $transmissions;
        }

        if ($this->gearCounts !== null) {
            $body['GearCount'] = array_map(
                fn (GearCount $gearCount): string => $gearCount->requestValue(),
                $this->gearCounts,
            );
        }

        if ($this->cylinderCounts !== null) {
            $body['CylinderCount'] = array_map(
                fn (CylinderCount $cylinderCount): string => $cylinderCount->requestValue(),
                $this->cylinderCounts,
            );
        }

        if ($this->seatCounts !== null) {
            $body['SeatCount'] = array_map(
                fn (SeatCount $seatCount): string => $seatCount->requestValue(),
                $this->seatCounts,
            );
        }

        if ($this->driveTypes !== null) {
            $body['DriveType'] = array_map(
                fn (DriveType $driveType): string => $driveType->value,
                $this->driveTypes,
            );
        }

        if ($this->accessories !== null) {
            $body['Accessory'] = array_map(
                fn (AccessoryFilter $accessory): string => $accessory->value,
                $this->accessories,
            );
        }

        if ($this->accelerationTo !== null) {
            $body['AccelerationTo'] = $this->accelerationTo;
        }

        if ($this->topSpeedFrom !== null) {
            $body['TopSpeedFrom'] = $this->topSpeedFrom;
        }

        if ($this->emptyMassTo !== null) {
            $body['EmptyMassTo'] = $this->emptyMassTo;
        }

        $cities = [];

        if ($this->city instanceof FilterOption) {
            $cities[] = $this->city->slug;
        }

        if ($this->cities !== null) {
            foreach ($this->cities as $city) {
                $cities[] = $city->slug;
            }
        }

        $cities = array_values(array_unique($cities));

        if ($cities !== []) {
            $body['City'] = count($cities) === 1 ? $cities[0] : $cities;
        }

        if ($this->isLeaseable === true) {
            $body['IsLeaseable'] = true;
        }

        if ($this->doorCountFrom !== null) {
            $body['DoorCountFrom'] = $this->doorCountFrom;
        }

        if ($this->doorCountTo !== null) {
            $body['DoorCountTo'] = $this->doorCountTo;
        }

        if ($this->wheelSizeFrom !== null) {
            $body['WheelSizeFrom'] = $this->wheelSizeFrom;
        }

        if ($this->wheelSizeTo !== null) {
            $body['WheelSizeTo'] = $this->wheelSizeTo;
        }

        if ($this->brakedTowingWeightFrom !== null) {
            $body['BrakedTowingWeightFrom'] = $this->brakedTowingWeightFrom;
        }

        if ($this->brakedTowingWeightTo !== null) {
            $body['BrakedTowingWeightTo'] = $this->brakedTowingWeightTo;
        }

        if ($this->maximumMassTo !== null) {
            $body['MaximumMassTo'] = $this->maximumMassTo;
        }

        // EV / Hybrid filters
        if ($this->batteryCapacityFrom !== null) {
            $body['BatteryCapacityFrom'] = $this->batteryCapacityFrom;
        }

        if ($this->batteryCapacityTo !== null) {
            $body['BatteryCapacityTo'] = $this->batteryCapacityTo;
        }

        if ($this->maxChargingPowerHome !== null) {
            $body['MaxChargingPowerHome'] = $this->maxChargingPowerHome;
        }

        if ($this->maxQuickChargingPower !== null) {
            $body['MaxQuickChargingPower'] = $this->maxQuickChargingPower;
        }

        if ($this->isPluginHybrid === true) {
            $body['IsPluginHybrid'] = true;
        }

        $energyLabels = [];

        if ($this->energyLabel instanceof FilterOption) {
            $energyLabels[] = $this->energyLabel->slug;
        }

        if ($this->energyLabels !== null) {
            foreach ($this->energyLabels as $energyLabel) {
                $energyLabels[] = $energyLabel->slug;
            }
        }

        $energyLabels = array_values(array_unique($energyLabels));

        if ($energyLabels !== []) {
            $body['EnergyLabel'] = $energyLabels;
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
            $body['SpecifiedBatteryRange'] = $specifiedBatteryRanges[0];
        }

        return $body;
    }
}
