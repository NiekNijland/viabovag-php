<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

use NiekNijland\ViaBOVAG\Data\Concerns\HasSharedFilterSlugs;
use NiekNijland\ViaBOVAG\Data\Concerns\HasSharedRequestBody;
use NiekNijland\ViaBOVAG\Data\Concerns\HasWithPage;
use NiekNijland\ViaBOVAG\Data\Filters\CamperSearchFilters;
use NiekNijland\ViaBOVAG\Data\Filters\SharedSearchFilters;

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
        public ?array $colors = null,

        // Location
        public ?string $postalCode = null,
        public ?Distance $distance = null,

        // Camper-specific
        public ?int $bedCount = null,
        public ?array $bedLayouts = null,
        public ?array $seatingLayouts = null,
        public ?array $sanitaryLayouts = null,
        public ?array $kitchenLayouts = null,
        public ?int $interiorHeightFrom = null,
        public ?array $camperChassisBrands = null,
        public ?int $maximumMassTo = null,

        // BOVAG certifications
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

    public static function fromFilters(
        SharedSearchFilters $shared = new SharedSearchFilters,
        CamperSearchFilters $filters = new CamperSearchFilters,
        int $page = 1,
    ): self {
        return new self(
            brand: $shared->brand,
            model: $shared->model,
            modelKeywords: $shared->modelKeywords,
            priceFrom: $shared->priceFrom,
            priceTo: $shared->priceTo,
            leasePriceFrom: $shared->leasePriceFrom,
            leasePriceTo: $shared->leasePriceTo,
            yearFrom: $shared->yearFrom,
            yearTo: $shared->yearTo,
            modelYearFrom: $shared->modelYearFrom,
            modelYearTo: $shared->modelYearTo,
            mileageFrom: $shared->mileageFrom,
            mileageTo: $shared->mileageTo,
            enginePowerFrom: $shared->enginePowerFrom,
            enginePowerTo: $shared->enginePowerTo,
            engineCapacityFrom: $filters->engineCapacityFrom,
            engineCapacityTo: $filters->engineCapacityTo,
            colors: $shared->colors,
            postalCode: $shared->postalCode,
            distance: $shared->distance,
            bedCount: $filters->bedCount,
            bedLayouts: $filters->bedLayouts,
            seatingLayouts: $filters->seatingLayouts,
            sanitaryLayouts: $filters->sanitaryLayouts,
            kitchenLayouts: $filters->kitchenLayouts,
            interiorHeightFrom: $filters->interiorHeightFrom,
            camperChassisBrands: $filters->camperChassisBrands,
            maximumMassTo: $filters->maximumMassTo,
            fullyServiced: $shared->fullyServiced,
            hasBovagChecklist: $shared->hasBovagChecklist,
            hasBovagMaintenanceFree: $shared->hasBovagMaintenanceFree,
            hasBovagImportOdometerCheck: $shared->hasBovagImportOdometerCheck,
            servicedOnDelivery: $shared->servicedOnDelivery,
            hasNapWeblabel: $shared->hasNapWeblabel,
            vatDeductible: $shared->vatDeductible,
            isFinanceable: $shared->isFinanceable,
            isImported: $shared->isImported,
            keywords: $shared->keywords,
            availableSince: $shared->availableSince,
            sortOrder: $shared->sortOrder,
            page: $page,
            conditions: $shared->conditions,
            warranties: $shared->warranties,
            transmissions: $filters->transmissions,
        );
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

        foreach ($this->collectTransmissionSlugs() as $transmissionSlug) {
            $filters[] = $transmissionSlug;
        }

        // Camper-specific filters
        if ($this->bedCount !== null) {
            $filters[] = 'slaapplaatsen-'.$this->bedCount;
        }

        foreach ($this->collectBedLayoutSlugs() as $bedLayoutSlug) {
            $filters[] = 'bedindeling-'.$bedLayoutSlug;
        }

        foreach ($this->collectSeatingLayoutSlugs() as $seatingLayoutSlug) {
            $filters[] = 'zitindeling-'.$seatingLayoutSlug;
        }

        foreach ($this->collectSanitaryLayoutSlugs() as $sanitaryLayoutSlug) {
            $filters[] = 'sanitaire-indeling-'.$sanitaryLayoutSlug;
        }

        foreach ($this->collectKitchenLayoutSlugs() as $kitchenLayoutSlug) {
            $filters[] = 'keukenindeling-'.$kitchenLayoutSlug;
        }

        if ($this->interiorHeightFrom !== null) {
            $filters[] = 'stahoogte-vanaf-'.$this->interiorHeightFrom;
        }

        foreach ($this->collectCamperChassisBrandSlugs() as $camperChassisBrandSlug) {
            $filters[] = 'chassis-merk-'.$camperChassisBrandSlug;
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

        $transmissions = $this->collectTransmissionValues();

        if ($transmissions !== []) {
            $body['Transmission'] = $transmissions;
        }

        // Camper-specific filters
        if ($this->bedCount !== null) {
            $body['BedCount'] = $this->bedCount;
        }

        $bedLayouts = $this->collectBedLayoutSlugs();

        if ($bedLayouts !== []) {
            $body['BedLayout'] = count($bedLayouts) === 1 ? $bedLayouts[0] : $bedLayouts;
        }

        $seatingLayouts = $this->collectSeatingLayoutSlugs();

        if ($seatingLayouts !== []) {
            $body['SeatingLayout'] = count($seatingLayouts) === 1 ? $seatingLayouts[0] : $seatingLayouts;
        }

        $sanitaryLayouts = $this->collectSanitaryLayoutSlugs();

        if ($sanitaryLayouts !== []) {
            $body['SanitaryLayout'] = count($sanitaryLayouts) === 1 ? $sanitaryLayouts[0] : $sanitaryLayouts;
        }

        $kitchenLayouts = $this->collectKitchenLayoutSlugs();

        if ($kitchenLayouts !== []) {
            $body['KitchenLayout'] = count($kitchenLayouts) === 1 ? $kitchenLayouts[0] : $kitchenLayouts;
        }

        if ($this->interiorHeightFrom !== null) {
            $body['InteriorHeightFrom'] = $this->interiorHeightFrom;
        }

        $camperChassisBrands = $this->collectCamperChassisBrandSlugs();

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

    /**
     * @return string[]
     */
    private function collectTransmissionSlugs(): array
    {
        if ($this->transmissions === null) {
            return [];
        }

        return array_values(array_unique(array_map(
            fn (TransmissionType $transmission): string => $transmission->slug(),
            $this->transmissions,
        )));
    }

    /**
     * @return string[]
     */
    private function collectTransmissionValues(): array
    {
        if ($this->transmissions === null) {
            return [];
        }

        return array_values(array_unique(array_map(
            fn (TransmissionType $transmission): string => $transmission->value,
            $this->transmissions,
        )));
    }

    /**
     * @return string[]
     */
    private function collectBedLayoutSlugs(): array
    {
        return $this->collectFilterOptionSlugs($this->bedLayouts);
    }

    /**
     * @return string[]
     */
    private function collectSeatingLayoutSlugs(): array
    {
        return $this->collectFilterOptionSlugs($this->seatingLayouts);
    }

    /**
     * @return string[]
     */
    private function collectSanitaryLayoutSlugs(): array
    {
        return $this->collectFilterOptionSlugs($this->sanitaryLayouts);
    }

    /**
     * @return string[]
     */
    private function collectKitchenLayoutSlugs(): array
    {
        return $this->collectFilterOptionSlugs($this->kitchenLayouts);
    }

    /**
     * @return string[]
     */
    private function collectCamperChassisBrandSlugs(): array
    {
        return $this->collectFilterOptionSlugs($this->camperChassisBrands);
    }

    /**
     * @param  FilterOption[]|null  $options
     * @return string[]
     */
    private function collectFilterOptionSlugs(?array $options): array
    {
        if ($options === null) {
            return [];
        }

        return array_values(array_unique(array_map(
            fn (FilterOption $option): string => $option->slug,
            $options,
        )));
    }
}
