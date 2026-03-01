<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Testing;

use NiekNijland\ViaBOVAG\Data\SearchResult;

class SearchResultFactory
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function make(array $overrides = []): SearchResult
    {
        $defaults = [
            'listings' => null,
            'totalCount' => 100,
            'currentPage' => 1,
        ];

        $data = array_merge($defaults, $overrides);

        return new SearchResult(
            listings: $data['listings'] ?? ListingFactory::makeMany(3),
            totalCount: $data['totalCount'],
            currentPage: $data['currentPage'],
        );
    }
}
