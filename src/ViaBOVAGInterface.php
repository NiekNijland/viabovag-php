<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG;

use NiekNijland\ViaBOVAG\Data\Listing;
use NiekNijland\ViaBOVAG\Data\ListingDetail;
use NiekNijland\ViaBOVAG\Data\MobilityType;
use NiekNijland\ViaBOVAG\Data\SearchQuery;
use NiekNijland\ViaBOVAG\Data\SearchResult;
use NiekNijland\ViaBOVAG\Exception\ViaBOVAGException;

interface ViaBOVAGInterface
{
    /**
     * Search for vehicle listings.
     *
     * @throws ViaBOVAGException
     */
    public function search(SearchQuery $query): SearchResult;

    /**
     * Get full detail for a listing from search results.
     *
     * @throws ViaBOVAGException
     */
    public function getDetail(Listing $listing): ListingDetail;

    /**
     * Get full detail for a listing by its URL slug.
     *
     * @throws ViaBOVAGException
     */
    public function getDetailBySlug(string $slug, MobilityType $mobilityType = MobilityType::Motor): ListingDetail;

    /**
     * Force a fresh build ID on the next request.
     */
    public function resetSession(): void;
}
