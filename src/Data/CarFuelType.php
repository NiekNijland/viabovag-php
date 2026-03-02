<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

enum CarFuelType: string
{
    case Petrol = 'Benzine';
    case Diesel = 'Diesel';
    case Hybrid = 'Hybride';
    case Electric = 'Elektriciteit';
    case Gas = 'Gas';
    case Hydrogen = 'Waterstof';
    case Other = 'Overige';

    public function slug(): string
    {
        return strtolower($this->value);
    }
}
