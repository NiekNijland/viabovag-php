<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

readonly class Company
{
    public function __construct(
        public string $name,
        public ?string $city,
        public ?string $phoneNumber,
        public ?string $websiteUrl,
        public ?string $callTrackingCode,
        public ?string $street = null,
        public ?string $postalCode = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?float $reviewScore = null,
        public ?int $reviewCount = null,
    ) {}
}
