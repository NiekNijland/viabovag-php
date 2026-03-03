<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data\Filters;

use NiekNijland\ViaBOVAG\Data\AvailableSince;
use NiekNijland\ViaBOVAG\Data\BovagWarranty;
use NiekNijland\ViaBOVAG\Data\Brand;
use NiekNijland\ViaBOVAG\Data\Condition;
use NiekNijland\ViaBOVAG\Data\Distance;
use NiekNijland\ViaBOVAG\Data\Model;
use NiekNijland\ViaBOVAG\Data\SortOrder;

readonly class SharedSearchFilters
{
    /**
     * @param  string[]|null  $colors
     * @param  Condition[]|null  $conditions
     * @param  BovagWarranty[]|null  $warranties
     */
    public function __construct(
        public ?Brand $brand = null,
        public ?Model $model = null,
        public ?string $modelKeywords = null,
        public ?int $priceFrom = null,
        public ?int $priceTo = null,
        public ?int $leasePriceFrom = null,
        public ?int $leasePriceTo = null,
        public ?int $yearFrom = null,
        public ?int $yearTo = null,
        public ?int $modelYearFrom = null,
        public ?int $modelYearTo = null,
        public ?int $mileageFrom = null,
        public ?int $mileageTo = null,
        public ?int $enginePowerFrom = null,
        public ?int $enginePowerTo = null,
        public ?array $colors = null,
        public ?array $conditions = null,
        public ?string $postalCode = null,
        public ?Distance $distance = null,
        public ?array $warranties = null,
        public ?bool $fullyServiced = null,
        public ?bool $hasBovagChecklist = null,
        public ?bool $hasBovagMaintenanceFree = null,
        public ?bool $hasBovagImportOdometerCheck = null,
        public ?bool $servicedOnDelivery = null,
        public ?bool $hasNapWeblabel = null,
        public ?bool $vatDeductible = null,
        public ?bool $isFinanceable = null,
        public ?bool $isImported = null,
        public ?string $keywords = null,
        public ?AvailableSince $availableSince = null,
        public ?SortOrder $sortOrder = null,
    ) {}
}
