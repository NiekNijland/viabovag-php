<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

enum MediaType: string
{
    case Image = 'image';
    case Video = 'video';
}
