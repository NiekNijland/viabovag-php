<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

enum MobilityType: string
{
    case Motorcycle = 'motor';
    case Car = 'auto';
    case Bicycle = 'fiets';
    case Camper = 'camper';

    public static function fromApiValue(string $value): ?self
    {
        return match (strtolower($value)) {
            'motor', 'motorcycle' => self::Motorcycle,
            'auto', 'car' => self::Car,
            'fiets', 'bicycle' => self::Bicycle,
            'camper', 'caramper', 'caravan' => self::Camper,
            default => null,
        };
    }

    public function searchSlug(): string
    {
        return match ($this) {
            self::Motorcycle => 'motoren',
            self::Car => 'auto',
            self::Bicycle => 'fietsen',
            self::Camper => 'camper',
        };
    }

    public function detailSlug(): string
    {
        return match ($this) {
            self::Motorcycle => 'motor',
            self::Car => 'auto',
            self::Bicycle => 'fiets',
            self::Camper => 'camper',
        };
    }
}
