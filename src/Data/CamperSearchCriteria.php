<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

use NiekNijland\ViaBOVAG\Data\Concerns\HasSharedFilterSlugs;
use NiekNijland\ViaBOVAG\Data\Concerns\HasWithPage;

/** @phpstan-consistent-constructor */
readonly class CamperSearchCriteria implements SearchQuery
{
    use HasSharedFilterSlugs;
    use HasWithPage;

    /**
     * @param  string[]|null  $colors
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
        public ?FilterOption $seatingLayout = null,
        public ?FilterOption $sanitaryLayout = null,
        public ?FilterOption $kitchenLayout = null,
        public ?int $interiorHeightFrom = null,
        public ?FilterOption $camperChassisBrand = null,
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

        // Camper-specific filters
        if ($this->bedCount !== null) {
            $filters[] = 'slaapplaatsen-'.$this->bedCount;
        }

        if ($this->bedLayout instanceof FilterOption) {
            $filters[] = 'bedindeling-'.$this->bedLayout->slug;
        }

        if ($this->seatingLayout instanceof FilterOption) {
            $filters[] = 'zitindeling-'.$this->seatingLayout->slug;
        }

        if ($this->sanitaryLayout instanceof FilterOption) {
            $filters[] = 'sanitaire-indeling-'.$this->sanitaryLayout->slug;
        }

        if ($this->kitchenLayout instanceof FilterOption) {
            $filters[] = 'keukenindeling-'.$this->kitchenLayout->slug;
        }

        if ($this->interiorHeightFrom !== null) {
            $filters[] = 'stahoogte-vanaf-'.$this->interiorHeightFrom;
        }

        if ($this->camperChassisBrand instanceof FilterOption) {
            $filters[] = 'chassis-merk-'.$this->camperChassisBrand->slug;
        }

        if ($this->maximumMassTo !== null) {
            $filters[] = 'maximale-massa-tot-en-met-'.$this->maximumMassTo;
        }

        return $filters;
    }
}
