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
 * @property Condition[]|null $conditions
 * @property ?string $postalCode
 * @property ?Distance $distance
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
    /** @var int[] */
    private const array ENGINE_POWER_BUCKETS = [50, 75, 90, 100, 110, 125, 150, 170, 200, 250, 300, 350];

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

        $modelKeywords = $this->normalizeOptionalTextForRequestBody($this->modelKeywords);

        if ($modelKeywords !== null) {
            // The API tokenizes model keywords and does not handle slug-style dashes reliably.
            if (! str_contains($modelKeywords, ' ')) {
                $modelKeywords = str_replace('-', ' ', $modelKeywords);
            }

            $body['ModelKeywords'] = $modelKeywords;
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
            $body['EnginePowerFrom'] = $this->normalizeEnginePowerFrom($this->enginePowerFrom);
        }

        if ($this->enginePowerTo !== null) {
            $body['EnginePowerTo'] = $this->normalizeEnginePowerTo($this->enginePowerTo);
        }

        if ($this->colors !== null) {
            $body['Color'] = $this->colors;
        }

        $conditions = $this->collectConditionValues();

        if ($conditions !== []) {
            $body['Condition'] = $conditions;
        }

        if ($this->postalCode !== null) {
            $body['PostalCode'] = $this->postalCode;
        }

        if ($this->distance !== null) {
            $body['Distance'] = $this->distance->value;
        }

        $warranties = $this->collectWarrantyValues();

        if ($warranties !== []) {
            $body['Warranty'] = $warranties;
        }

        $keywords = $this->normalizeOptionalTextForRequestBody($this->keywords);

        if ($keywords !== null) {
            $body['Keywords'] = $keywords;
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

    private function normalizeEnginePowerFrom(int $enginePower): string
    {
        foreach (self::ENGINE_POWER_BUCKETS as $bucket) {
            if ($bucket >= $enginePower) {
                return 'EnginePower'.$bucket;
            }
        }

        return 'EnginePower'.self::ENGINE_POWER_BUCKETS[array_key_last(self::ENGINE_POWER_BUCKETS)];
    }

    private function normalizeEnginePowerTo(int $enginePower): string
    {
        for ($index = count(self::ENGINE_POWER_BUCKETS) - 1; $index >= 0; $index--) {
            $bucket = self::ENGINE_POWER_BUCKETS[$index];

            if ($bucket <= $enginePower) {
                return 'EnginePower'.$bucket;
            }
        }

        return 'EnginePower'.self::ENGINE_POWER_BUCKETS[0];
    }

    /**
     * @return string[]
     */
    private function collectConditionValues(): array
    {
        $conditions = [];

        if (is_array($this->conditions)) {
            foreach ($this->conditions as $condition) {
                $conditions[] = $condition->value;
            }
        }

        return array_values(array_unique($conditions));
    }

    /**
     * @return string[]
     */
    private function collectWarrantyValues(): array
    {
        $warranties = [];

        if (is_array($this->warranties)) {
            foreach ($this->warranties as $warranty) {
                $warranties[] = $warranty->value;
            }
        }

        return array_values(array_unique($warranties));
    }

    private function normalizeOptionalTextForRequestBody(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }
}
