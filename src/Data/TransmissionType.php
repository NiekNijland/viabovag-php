<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

enum TransmissionType: string
{
    case Handgeschakeld = 'Handgeschakeld';
    case Automatisch = 'Automatisch';
    case SemiAutomatisch = 'SemiAutomatisch';

    public function slug(): string
    {
        return strtolower($this->value);
    }
}
