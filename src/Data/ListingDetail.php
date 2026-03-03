<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

readonly class ListingDetail
{
    /**
     * @param  Media[]  $media
     * @param  SpecificationGroup[]  $specificationGroups
     * @param  Accessory[]  $accessories
     * @param  OptionGroup[]  $optionGroups
     * @param  array<string, mixed>|string|null  $structuredData
     */
    public function __construct(
        public string $id,
        public string $title,
        public int $price,
        public ?string $description,
        public array $media,
        public Vehicle $vehicle,
        public Company $company,
        public array $specificationGroups,
        public array $accessories,
        public array $optionGroups,
        public ?string $licensePlate,
        public ?string $externalNumber,
        public array|string|null $structuredData,
        public bool $priceExcludesVat = false,
        public ?string $url = null,
        public ?MobilityType $mobilityType = null,
        public bool $isEligibleForVehicleReport = false,
        public ?string $financingProvider = null,
        public ?int $leasePrice = null,
        public ?int $roadTax = null,
        public ?string $fuelConsumption = null,
        public ?string $bijtellingPercentage = null,
        public ?int $returnWarrantyMileage = null,
        public ?DriversLicense $driversLicense = null,
    ) {}

    public function descriptionText(): ?string
    {
        if ($this->description === null) {
            return null;
        }

        $description = preg_replace('/<br\s*\/?>/i', "\n", $this->description) ?? $this->description;
        $description = strip_tags($description);
        $description = html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $description = preg_replace('/[\t ]+/', ' ', $description) ?? $description;
        $description = preg_replace('/ *\n */', "\n", $description) ?? $description;
        $description = preg_replace('/\n{3,}/', "\n\n", $description) ?? $description;
        $description = trim($description);

        return $description !== '' ? $description : null;
    }
}
