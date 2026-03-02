<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

enum BicycleBodyType: string
{
    case CargoBike = 'Bakfiets';
    case BmxFreestyleBike = 'BmxFreestyleFiets';
    case CrossHybrid = 'Crosshybride';
    case CruiserBike = 'Cruiserfiets';
    case HybridBike = 'HybrideFiets';
    case YouthBike = 'Jeugdfiets';
    case ChildBike = 'Kinderfiets';
    case RecumbentBike = 'Ligfiets';
    case Mountainbike = 'Mountainbike';
    case RoadBike = 'Racefiets';
    case CityBike = 'Stadsfiets';
    case Tandem = 'Tandem';
    case FoldingBike = 'Vouwfiets';
    case Other = 'Overig';

    public function slug(): string
    {
        return strtolower($this->value);
    }
}
