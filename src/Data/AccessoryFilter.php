<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

enum AccessoryFilter: string
{
    case Airco = 'airco';
    case ClimateControl = 'climatecontrol';
    case AndroidAuto = 'androidauto';
    case AppleCarPlay = 'applecarplay';
    case CruiseControl = 'cruisecontrol';
    case AdaptiveCruiseControl = 'adaptivecruisecontrol';
    case Navigation = 'navigatie';
    case ParkingSensors = 'parkeersensoren';
    case HeatedSeats = 'stoelverwarming';
    case LeatherInterior = 'lederenbekleding';
    case Panoramicroof = 'panoramadak';
    case Towbar = 'trekhaak';
    case HeadUpDisplay = 'headupdisplay';
    case BlindSpotDetection = 'dodehoekdetectie';

    public function slug(): string
    {
        return $this->value;
    }
}
