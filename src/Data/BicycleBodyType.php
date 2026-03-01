<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

enum BicycleBodyType: string
{
    case Bakfiets = 'Bakfiets';
    case BmxFreestyleFiets = 'BmxFreestyleFiets';
    case Crosshybride = 'Crosshybride';
    case Cruiserfiets = 'Cruiserfiets';
    case HybrideFiets = 'HybrideFiets';
    case Jeugdfiets = 'Jeugdfiets';
    case Kinderfiets = 'Kinderfiets';
    case Ligfiets = 'Ligfiets';
    case Mountainbike = 'Mountainbike';
    case Racefiets = 'Racefiets';
    case Stadsfiets = 'Stadsfiets';
    case Tandem = 'Tandem';
    case Vouwfiets = 'Vouwfiets';
    case Overig = 'Overig';

    public function slug(): string
    {
        return strtolower($this->value);
    }
}
