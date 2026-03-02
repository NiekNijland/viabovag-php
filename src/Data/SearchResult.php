<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

readonly class SearchResult
{
    private const int PAGE_SIZE = 24;

    /**
     * @param  Listing[]  $listings
     * @param  SearchFacet[]  $facets
     */
    public function __construct(
        public array $listings,
        public int $totalCount,
        public int $currentPage,
        public array $facets = [],
    ) {}

    public function totalPages(): int
    {
        if ($this->totalCount === 0) {
            return 0;
        }

        return (int) ceil($this->totalCount / self::PAGE_SIZE);
    }

    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->totalPages();
    }

    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }

    public function pageSize(): int
    {
        return self::PAGE_SIZE;
    }
}
