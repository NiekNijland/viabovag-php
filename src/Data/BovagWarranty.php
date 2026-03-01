<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

enum BovagWarranty: string
{
    case TwaalfMaanden = 'TwaalfMaanden';
    case ZesMaanden = 'ZesMaanden';
    case DrieMaanden = 'DrieMaanden';

    public function slug(): string
    {
        return match ($this) {
            self::TwaalfMaanden => 'bovag-garantie-12-maanden',
            self::ZesMaanden => 'bovag-garantie-6-maanden',
            self::DrieMaanden => 'bovag-garantie-3-maanden',
        };
    }
}
