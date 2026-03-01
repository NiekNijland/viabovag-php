<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

enum MobilityType: string
{
    case Motor = 'motor';
    case Car = 'car';
    case Bicycle = 'bicycle';
    case Camper = 'camper';

    public function searchSlug(): string
    {
        return match ($this) {
            self::Motor => 'motoren',
            self::Car => 'auto',
            self::Bicycle => 'fietsen',
            self::Camper => 'campers',
        };
    }

    public function detailSlug(): string
    {
        return match ($this) {
            self::Motor => 'motor',
            self::Car => 'auto',
            self::Bicycle => 'fiets',
            self::Camper => 'camper',
        };
    }
}
