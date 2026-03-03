<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

use NiekNijland\ViaBOVAG\Data\Concerns\HasSharedFilterSlugs;
use NiekNijland\ViaBOVAG\Data\Concerns\HasSharedRequestBody;
use NiekNijland\ViaBOVAG\Data\Concerns\HasWithPage;

/** @phpstan-consistent-constructor */
readonly class CamperSearchCriteria implements SearchQuery
{
    use HasSharedFilterSlugs;
    use HasSharedRequestBody;
    use HasWithPage;

    /**
     * @param  string[]|null  $colors
     * @param  Condition[]|null  $conditions
     * @param  BovagWarranty[]|null  $warranties
     * @param  TransmissionType[]|null  $transmissions
     * @param  FilterOption[]|null  $bedLayouts
     * @param  FilterOption[]|null  $seatingLayouts
     * @param  FilterOption[]|null  $sanitaryLayouts
     * @param  FilterOption[]|null  $kitchenLayouts
     * @param  FilterOption[]|null  $camperChassisBrands
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

        // Vehicle characteristics
        public ?TransmissionType $transmission = null,
        public ?array $colors = null,
        public ?Condition $condition = null,

        // Location
        public ?string $postalCode = null,
        public ?Distance $distance = null,

        // Camper-specific
        public ?int $bedCount = null,
        public ?FilterOption $bedLayout = null,
        public ?array $bedLayouts = null,
        public ?FilterOption $seatingLayout = null,
        public ?array $seatingLayouts = null,
        public ?FilterOption $sanitaryLayout = null,
        public ?array $sanitaryLayouts = null,
        public ?FilterOption $kitchenLayout = null,
        public ?array $kitchenLayouts = null,
        public ?int $interiorHeightFrom = null,
        public ?FilterOption $camperChassisBrand = null,
        public ?array $camperChassisBrands = null,
        public ?int $maximumMassTo = null,

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

        // Multi-select
        public ?array $conditions = null,
        public ?array $warranties = null,
        public ?array $transmissions = null,
    ) {
        self::assertValidPage($page);
    }

    public function mobilityType(): MobilityType
    {
        return MobilityType::Camper;
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

        if ($this->transmission instanceof TransmissionType) {
            $filters[] = $this->transmission->slug();
        }

        if ($this->transmissions !== null) {
            foreach ($this->transmissions as $transmission) {
                $filters[] = $transmission->slug();
            }
        }

        // Camper-specific filters
        if ($this->bedCount !== null) {
            $filters[] = 'slaapplaatsen-'.$this->bedCount;
        }

        if ($this->bedLayout instanceof FilterOption) {
            $filters[] = 'bedindeling-'.$this->bedLayout->slug;
        }

        if ($this->bedLayouts !== null) {
            foreach ($this->bedLayouts as $bedLayout) {
                $filters[] = 'bedindeling-'.$bedLayout->slug;
            }
        }

        if ($this->seatingLayout instanceof FilterOption) {
            $filters[] = 'zitindeling-'.$this->seatingLayout->slug;
        }

        if ($this->seatingLayouts !== null) {
            foreach ($this->seatingLayouts as $seatingLayout) {
                $filters[] = 'zitindeling-'.$seatingLayout->slug;
            }
        }

        if ($this->sanitaryLayout instanceof FilterOption) {
            $filters[] = 'sanitaire-indeling-'.$this->sanitaryLayout->slug;
        }

        if ($this->sanitaryLayouts !== null) {
            foreach ($this->sanitaryLayouts as $sanitaryLayout) {
                $filters[] = 'sanitaire-indeling-'.$sanitaryLayout->slug;
            }
        }

        if ($this->kitchenLayout instanceof FilterOption) {
            $filters[] = 'keukenindeling-'.$this->kitchenLayout->slug;
        }

        if ($this->kitchenLayouts !== null) {
            foreach ($this->kitchenLayouts as $kitchenLayout) {
                $filters[] = 'keukenindeling-'.$kitchenLayout->slug;
            }
        }

        if ($this->interiorHeightFrom !== null) {
            $filters[] = 'stahoogte-vanaf-'.$this->interiorHeightFrom;
        }

        if ($this->camperChassisBrand instanceof FilterOption) {
            $filters[] = 'chassis-merk-'.$this->camperChassisBrand->slug;
        }

        if ($this->camperChassisBrands !== null) {
            foreach ($this->camperChassisBrands as $camperChassisBrand) {
                $filters[] = 'chassis-merk-'.$camperChassisBrand->slug;
            }
        }

        if ($this->maximumMassTo !== null) {
            $filters[] = 'maximale-massa-tot-en-met-'.$this->maximumMassTo;
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

        // Camper-specific filters
        if ($this->bedCount !== null) {
            $body['BedCount'] = $this->bedCount;
        }

        $bedLayouts = [];

        if ($this->bedLayout instanceof FilterOption) {
            $bedLayouts[] = $this->bedLayout->slug;
        }

        if ($this->bedLayouts !== null) {
            foreach ($this->bedLayouts as $bedLayout) {
                $bedLayouts[] = $bedLayout->slug;
            }
        }

        $bedLayouts = array_values(array_unique($bedLayouts));

        if ($bedLayouts !== []) {
            $body['BedLayout'] = count($bedLayouts) === 1 ? $bedLayouts[0] : $bedLayouts;
        }

        $seatingLayouts = [];

        if ($this->seatingLayout instanceof FilterOption) {
            $seatingLayouts[] = $this->seatingLayout->slug;
        }

        if ($this->seatingLayouts !== null) {
            foreach ($this->seatingLayouts as $seatingLayout) {
                $seatingLayouts[] = $seatingLayout->slug;
            }
        }

        $seatingLayouts = array_values(array_unique($seatingLayouts));

        if ($seatingLayouts !== []) {
            $body['SeatingLayout'] = count($seatingLayouts) === 1 ? $seatingLayouts[0] : $seatingLayouts;
        }

        $sanitaryLayouts = [];

        if ($this->sanitaryLayout instanceof FilterOption) {
            $sanitaryLayouts[] = $this->sanitaryLayout->slug;
        }

        if ($this->sanitaryLayouts !== null) {
            foreach ($this->sanitaryLayouts as $sanitaryLayout) {
                $sanitaryLayouts[] = $sanitaryLayout->slug;
            }
        }

        $sanitaryLayouts = array_values(array_unique($sanitaryLayouts));

        if ($sanitaryLayouts !== []) {
            $body['SanitaryLayout'] = count($sanitaryLayouts) === 1 ? $sanitaryLayouts[0] : $sanitaryLayouts;
        }

        $kitchenLayouts = [];

        if ($this->kitchenLayout instanceof FilterOption) {
            $kitchenLayouts[] = $this->kitchenLayout->slug;
        }

        if ($this->kitchenLayouts !== null) {
            foreach ($this->kitchenLayouts as $kitchenLayout) {
                $kitchenLayouts[] = $kitchenLayout->slug;
            }
        }

        $kitchenLayouts = array_values(array_unique($kitchenLayouts));

        if ($kitchenLayouts !== []) {
            $body['KitchenLayout'] = count($kitchenLayouts) === 1 ? $kitchenLayouts[0] : $kitchenLayouts;
        }

        if ($this->interiorHeightFrom !== null) {
            $body['InteriorHeightFrom'] = $this->interiorHeightFrom;
        }

        $camperChassisBrands = [];

        if ($this->camperChassisBrand instanceof FilterOption) {
            $camperChassisBrands[] = $this->camperChassisBrand->slug;
        }

        if ($this->camperChassisBrands !== null) {
            foreach ($this->camperChassisBrands as $camperChassisBrand) {
                $camperChassisBrands[] = $camperChassisBrand->slug;
            }
        }

        $camperChassisBrands = array_values(array_unique($camperChassisBrands));

        if ($camperChassisBrands !== []) {
            $body['CamperChassisBrand'] = count($camperChassisBrands) === 1
                ? $camperChassisBrands[0]
                : $camperChassisBrands;
        }

        if ($this->maximumMassTo !== null) {
            $body['MaximumMassTo'] = $this->maximumMassTo;
        }

        return $body;
    }
}
