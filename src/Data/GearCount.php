<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

enum GearCount: int
{
    case Five = 5;
    case Six = 6;
    case Seven = 7;
    case Eight = 8;
    case Nine = 9;

    public function slug(): string
    {
        return 'versnellingen-'.$this->value;
    }
}
