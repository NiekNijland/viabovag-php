<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

enum CarBodyType: string
{
    case Hatchback = 'Hatchback';
    case Sedan = 'Sedan';
    case SuvTerreinwagen = 'SuvTerreinwagen';
    case Stationwagen = 'Stationwagen';
    case Coupe = 'Coupe';
    case Mpv = 'Mpv';
    case Cabriolet = 'Cabriolet';
    case Bedrijfswagen = 'Bedrijfswagen';
    case Personenbus = 'Personenbus';
    case Pickup = 'Pickup';
    case Overig = 'Overig';

    public function slug(): string
    {
        return strtolower($this->value);
    }
}
