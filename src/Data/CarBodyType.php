<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

enum CarBodyType: string
{
    case Hatchback = 'Hatchback';
    case Sedan = 'Sedan';
    case SuvOffRoad = 'SuvTerreinwagen';
    case StationWagon = 'Stationwagen';
    case Coupe = 'Coupe';
    case Mpv = 'Mpv';
    case Cabriolet = 'Cabriolet';
    case CommercialVehicle = 'Bedrijfswagen';
    case PassengerBus = 'Personenbus';
    case Pickup = 'Pickup';
    case Other = 'Overig';

    public function slug(): string
    {
        return strtolower($this->value);
    }
}
