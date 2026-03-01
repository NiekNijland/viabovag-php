<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

enum DriversLicense: string
{
    case A = 'A';
    case A1 = 'A1';
    case A2 = 'A2';

    public function slug(): string
    {
        return 'rijbewijs-'.strtolower($this->value);
    }
}
