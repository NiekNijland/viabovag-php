<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data\Concerns;

use NiekNijland\ViaBOVAG\Data\AvailableSince;
use NiekNijland\ViaBOVAG\Data\BovagWarranty;
use NiekNijland\ViaBOVAG\Data\Brand;
use NiekNijland\ViaBOVAG\Data\Condition;
use NiekNijland\ViaBOVAG\Data\Distance;
use NiekNijland\ViaBOVAG\Data\MobilityType;
use NiekNijland\ViaBOVAG\Data\Model;
use NiekNijland\ViaBOVAG\Data\SortOrder;

/**
 * Shared request body generation for properties common across all mobility types.
 *
 * Expects the using class to define the following public properties:
 *
 * @property ?Brand $brand
 * @property ?Model $model
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
 * @property Condition[]|null $conditions
 * @property ?string $postalCode
 * @property ?Distance $distance
 * @property ?BovagWarranty $warranty
 * @property BovagWarranty[]|null $warranties
 * @property ?bool $fullyServiced
 * @property ?bool $hasBovagChecklist
 * @property ?bool $hasBovagMaintenanceFree
 * @property ?bool $hasBovagImportOdometerCheck
 * @property ?bool $servicedOnDelivery
 * @property ?bool $hasNapWeblabel
 * @property ?bool $vatDeductible
 * @property ?bool $isFinanceable
 * @property ?bool $isImported
 * @property ?string $keywords
 * @property ?AvailableSince $availableSince
 * @property ?SortOrder $sortOrder
 */
trait HasSharedRequestBody
{
    /**
     * Build the shared portion of the REST API request body.
     *
     * The using class must implement `mobilityType(): MobilityType` and `page(): int`.
     *
     * @return array<string, mixed>
     */
    protected function sharedRequestBody(): array
    {
        $body = [
            'MobilityType' => $this->mobilityType()->value,
            'InStock' => true,
            'ShowCommercialVehicles' => true,
            'HideVatExcludedPrices' => true,
        ];

        if ($this->page() > 1) {
            $body['PageNumber'] = $this->page();
        }

        if ($this->brand instanceof Brand) {
            $body['Brand'] = [$this->brand->slug];
        }

        if ($this->model instanceof Model) {
            $body['Model'] = $this->model->slug;
        }

        if ($this->modelKeywords !== null) {
            $normalizedModelKeywords = trim($this->modelKeywords);

            if ($normalizedModelKeywords !== '') {
                // The API tokenizes model keywords and does not handle slug-style dashes reliably.
                if (! str_contains($normalizedModelKeywords, ' ')) {
                    $normalizedModelKeywords = str_replace('-', ' ', $normalizedModelKeywords);
                }

                $body['ModelKeywords'] = $normalizedModelKeywords;
            }
        }

        if ($this->priceFrom !== null) {
            $body['PriceFrom'] = $this->priceFrom;
        }

        if ($this->priceTo !== null) {
            $body['PriceTo'] = $this->priceTo;
        }

        if ($this->leasePriceFrom !== null) {
            $body['LeasePriceFrom'] = $this->leasePriceFrom;
        }

        if ($this->leasePriceTo !== null) {
            $body['LeasePriceTo'] = $this->leasePriceTo;
        }

        if ($this->yearFrom !== null) {
            $body['YearFrom'] = $this->yearFrom;
        }

        if ($this->yearTo !== null) {
            $body['YearTo'] = $this->yearTo;
        }

        if ($this->modelYearFrom !== null) {
            $body['ModelYearFrom'] = $this->modelYearFrom;
        }

        if ($this->modelYearTo !== null) {
            $body['ModelYearTo'] = $this->modelYearTo;
        }

        if ($this->mileageFrom !== null) {
            $body['MileageFrom'] = $this->mileageFrom;
        }

        if ($this->mileageTo !== null) {
            $body['MileageTo'] = $this->mileageTo;
        }

        if ($this->enginePowerFrom !== null) {
            $body['EnginePowerFrom'] = $this->enginePowerFrom;
        }

        if ($this->enginePowerTo !== null) {
            $body['EnginePowerTo'] = $this->enginePowerTo;
        }

        if ($this->colors !== null) {
            $body['Color'] = $this->colors;
        }

        $conditions = [];

        if ($this->condition !== null) {
            $conditions[] = $this->condition->value;
        }

        if (is_array($this->conditions)) {
            foreach ($this->conditions as $condition) {
                $conditions[] = $condition->value;
            }
        }

        $conditions = array_values(array_unique($conditions));

        if ($conditions !== []) {
            $body['Condition'] = $conditions;
        }

        if ($this->postalCode !== null) {
            $body['PostalCode'] = $this->postalCode;
        }

        if ($this->distance !== null) {
            $body['Distance'] = $this->distance->value;
        }

        $warranties = [];

        if ($this->warranty !== null) {
            $warranties[] = $this->warranty->value;
        }

        if (is_array($this->warranties)) {
            foreach ($this->warranties as $warranty) {
                $warranties[] = $warranty->value;
            }
        }

        $warranties = array_values(array_unique($warranties));

        if ($warranties !== []) {
            $body['Warranty'] = $warranties;
        }

        if ($this->keywords !== null) {
            $body['Keywords'] = $this->keywords;
        }

        if ($this->availableSince !== null) {
            $body['AvailableSince'] = $this->availableSince->requestValue();
        }

        // Boolean filters
        if ($this->fullyServiced === true) {
            $body['FullyServiced'] = true;
        }

        if ($this->hasBovagChecklist === true) {
            $body['HasBovagChecklist'] = true;
        }

        if ($this->hasBovagMaintenanceFree === true) {
            $body['HasBovagMaintenanceFree'] = true;
        }

        if ($this->hasBovagImportOdometerCheck === true) {
            $body['HasBovagImportOdometerCheck'] = true;
        }

        if ($this->servicedOnDelivery === true) {
            $body['CarServicedOnDelivery'] = true;
        }

        if ($this->hasNapWeblabel === true) {
            if ($this->mobilityType() === MobilityType::Motorcycle) {
                $body['HasNapOrBit'] = true;
            } else {
                $body['HasNapWeblabel'] = true;
            }
        }

        if ($this->vatDeductible === true) {
            $body['VatDeductible'] = true;
        }

        if ($this->isFinanceable === true) {
            $body['IsFinanceable'] = true;
        }

        if ($this->isImported === true) {
            $body['Import'] = ['Ja'];
        } elseif ($this->isImported === false) {
            $body['Import'] = ['Nee'];
        }

        if ($this->sortOrder !== null) {
            $body['SortOrder'] = $this->sortOrder->value;
        }

        return $body;
    }
}
