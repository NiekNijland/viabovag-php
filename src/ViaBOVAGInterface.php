<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG;

use Generator;
use NiekNijland\ViaBOVAG\Data\Brand;
use NiekNijland\ViaBOVAG\Data\FacetName;
use NiekNijland\ViaBOVAG\Data\FilterOption;
use NiekNijland\ViaBOVAG\Data\Listing;
use NiekNijland\ViaBOVAG\Data\ListingDetail;
use NiekNijland\ViaBOVAG\Data\MobilityType;
use NiekNijland\ViaBOVAG\Data\Model;
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
     * Iterate through all pages of search results, yielding each listing.
     *
     * Starts from the page specified in the query and fetches subsequent pages
     * automatically until all results have been yielded.
     *
     * @return Generator<int, Listing>
     *
     * @throws ViaBOVAGException
     */
    public function searchAll(SearchQuery $query): Generator;

    /**
     * Get all available brands for a mobility type.
     *
     * @return Brand[]
     *
     * @throws ViaBOVAGException
     */
    public function getBrands(MobilityType $mobilityType): array;

    /**
     * Get all available options for a specific facet.
     *
     * The optional brand and model can be used to scope dependent facets.
     *
     * @return FilterOption[]
     *
     * @throws ViaBOVAGException
     */
    public function getFacetOptions(
        MobilityType $mobilityType,
        FacetName $facetName,
        ?Brand $brand = null,
        ?Model $model = null,
    ): array;

    /**
     * Get all available models for a mobility type.
     *
     * If a brand is provided, models are fetched for that brand.
     *
     * @return Model[]
     *
     * @throws ViaBOVAGException
     */
    public function getModels(MobilityType $mobilityType, ?Brand $brand = null): array;

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
    public function getDetailBySlug(string $slug, MobilityType $mobilityType): ListingDetail;

    /**
     * Force a fresh build ID on the next request.
     */
    public function resetSession(): void;
}
