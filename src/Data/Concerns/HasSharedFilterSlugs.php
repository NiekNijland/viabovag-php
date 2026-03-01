<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data\Concerns;

use NiekNijland\ViaBOVAG\Data\AvailableSince;
use NiekNijland\ViaBOVAG\Data\BovagWarranty;
use NiekNijland\ViaBOVAG\Data\Condition;
use NiekNijland\ViaBOVAG\Data\Distance;

/**
 * Shared filter slug generation for properties common across all mobility types.
 *
 * Expects the using class to define the following public properties:
 *
 * @property ?string $brand
 * @property ?string $model
 * @property ?string $modelKeywords
 * @property ?int $priceFrom
 * @property ?int $priceTo
 * @property ?int $leasePriceFrom
 * @property ?int $leasePriceTo
 * @property ?int $yearFrom
 * @property ?int $yearTo
 * @property ?int $modelYearFrom
 * @property ?int $modelYearTo
 * @property ?int $mileageFrom
 * @property ?int $mileageTo
 * @property ?int $enginePowerFrom
 * @property ?int $enginePowerTo
 * @property string[]|null $colors
 * @property ?Condition $condition
 * @property ?string $postalCode
 * @property ?Distance $distance
 * @property ?BovagWarranty $warranty
 * @property ?bool $fullyServiced
 * @property ?bool $hasBovagChecklist
 * @property ?bool $hasBovagMaintenanceFree
 * @property ?bool $hasBovagImportOdometerCheck
 * @property ?bool $carServicedOnDelivery
 * @property ?bool $hasNapWeblabel
 * @property ?bool $vatDeductible
 * @property ?bool $isFinanceable
 * @property ?bool $isImported
 * @property ?string $keywords
 * @property ?AvailableSince $availableSince
 */
trait HasSharedFilterSlugs
{
    /**
     * @return string[]
     */
    protected function sharedFilterSlugs(): array
    {
        $filters = [];

        if ($this->brand !== null) {
            $filters[] = 'merk-'.strtolower($this->brand);
        }

        if ($this->model !== null) {
            $filters[] = 'model-'.strtolower($this->model);
        }

        if ($this->modelKeywords !== null) {
            $filters[] = 'model-trefwoorden-'.strtolower($this->modelKeywords);
        }

        if ($this->priceFrom !== null) {
            $filters[] = 'prijs-vanaf-'.$this->priceFrom;
        }

        if ($this->priceTo !== null) {
            $filters[] = 'prijs-tot-en-met-'.$this->priceTo;
        }

        if ($this->leasePriceFrom !== null) {
            $filters[] = 'leaseprijs-vanaf-'.$this->leasePriceFrom;
        }

        if ($this->leasePriceTo !== null) {
            $filters[] = 'leaseprijs-tot-en-met-'.$this->leasePriceTo;
        }

        if ($this->yearFrom !== null) {
            $filters[] = 'bouwjaar-vanaf-'.$this->yearFrom;
        }

        if ($this->yearTo !== null) {
            $filters[] = 'bouwjaar-tot-en-met-'.$this->yearTo;
        }

        if ($this->modelYearFrom !== null) {
            $filters[] = 'modeljaar-vanaf-'.$this->modelYearFrom;
        }

        if ($this->modelYearTo !== null) {
            $filters[] = 'modeljaar-tot-en-met-'.$this->modelYearTo;
        }

        if ($this->mileageFrom !== null) {
            $filters[] = 'kilometerstand-vanaf-'.$this->mileageFrom;
        }

        if ($this->mileageTo !== null) {
            $filters[] = 'kilometerstand-tot-en-met-'.$this->mileageTo;
        }

        if ($this->enginePowerFrom !== null) {
            $filters[] = 'vermogen-pk-vanaf-'.$this->enginePowerFrom;
        }

        if ($this->enginePowerTo !== null) {
            $filters[] = 'vermogen-pk-tot-en-met-'.$this->enginePowerTo;
        }

        if ($this->colors !== null) {
            foreach ($this->colors as $color) {
                $filters[] = 'kleur-'.strtolower((string) $color);
            }
        }

        if ($this->condition !== null) {
            $filters[] = $this->condition->slug();
        }

        if ($this->postalCode !== null) {
            $filters[] = 'postcode-'.$this->postalCode;
        }

        if ($this->distance !== null) {
            $filters[] = $this->distance->slug();
        }

        if ($this->warranty !== null) {
            $filters[] = $this->warranty->slug();
        }

        if ($this->keywords !== null) {
            $filters[] = 'trefwoorden-'.strtolower($this->keywords);
        }

        if ($this->availableSince !== null) {
            $filters[] = $this->availableSince->slug();
        }

        // Boolean filters
        if ($this->fullyServiced === true) {
            $filters[] = '100-procent-onderhouden';
        }

        if ($this->hasNapWeblabel === true) {
            $filters[] = 'nap-weblabel';
        }

        if ($this->hasBovagChecklist === true) {
            $filters[] = '40-puntencheck';
        }

        if ($this->hasBovagMaintenanceFree === true) {
            $filters[] = 'onderhoudsvrij';
        }

        if ($this->hasBovagImportOdometerCheck === true) {
            $filters[] = 'import-teller-check';
        }

        if ($this->carServicedOnDelivery === true) {
            $filters[] = 'afleverbeurt';
        }

        if ($this->vatDeductible === true) {
            $filters[] = 'btw-verrekenbaar';
        }

        if ($this->isFinanceable === true) {
            $filters[] = 'online-te-financieren';
        }

        if ($this->isImported === true) {
            $filters[] = 'import-ja';
        } elseif ($this->isImported === false) {
            $filters[] = 'import-nee';
        }

        return $filters;
    }
}
