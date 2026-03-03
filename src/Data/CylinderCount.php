<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

enum CylinderCount: int
{
    case Two = 2;
    case Three = 3;
    case Four = 4;
    case Five = 5;
    case Six = 6;
    case Eight = 8;
    case Ten = 10;

    public function slug(): string
    {
        return 'cilinders-'.$this->value;
    }

    public function requestValue(): string
    {
        return match ($this) {
            self::Two => 'Two',
            self::Three => 'Three',
            self::Four => 'Four',
            self::Five => 'Five',
            self::Six => 'Six',
            self::Eight => 'Eight',
            self::Ten => 'TenOrMore',
        };
    }
}
