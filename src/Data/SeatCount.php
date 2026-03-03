<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

enum SeatCount: int
{
    case One = 1;
    case Two = 2;
    case Three = 3;
    case Four = 4;
    case Five = 5;
    case Six = 6;
    case Seven = 7;
    case Eight = 8;
    case Nine = 9;

    public function slug(): string
    {
        return 'zitplaatsen-'.$this->value;
    }

    public function requestValue(): string
    {
        return $this->name;
    }
}
