<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

readonly class Listing
{
    public function __construct(
        public string $id,
        public string $mobilityType,
        public string $url,
        public string $friendlyUriPart,
        public ?string $externalAdvertisementUrl,
        public ?string $imageUrl,
        public string $title,
        public int $price,
        public bool $isFinanceable,
        public Vehicle $vehicle,
        public Company $company,
    ) {}
}
