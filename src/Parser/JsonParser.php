<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Parser;

use JsonException;
use NiekNijland\ViaBOVAG\Data\Accessory;
use NiekNijland\ViaBOVAG\Data\Company;
use NiekNijland\ViaBOVAG\Data\Listing;
use NiekNijland\ViaBOVAG\Data\ListingDetail;
use NiekNijland\ViaBOVAG\Data\Media;
use NiekNijland\ViaBOVAG\Data\MediaType;
use NiekNijland\ViaBOVAG\Data\MileageUnit;
use NiekNijland\ViaBOVAG\Data\MobilityType;
use NiekNijland\ViaBOVAG\Data\OptionGroup;
use NiekNijland\ViaBOVAG\Data\SearchFacet;
use NiekNijland\ViaBOVAG\Data\SearchFacetOption;
use NiekNijland\ViaBOVAG\Data\SearchFacetOptionCategory;
use NiekNijland\ViaBOVAG\Data\SearchResult;
use NiekNijland\ViaBOVAG\Data\Specification;
use NiekNijland\ViaBOVAG\Data\SpecificationGroup;
use NiekNijland\ViaBOVAG\Data\Vehicle;
use NiekNijland\ViaBOVAG\Exception\ViaBOVAGException;

class JsonParser
{
    /**
     * Extract the Next.js build ID from an HTML page.
     *
     * @throws ViaBOVAGException if the build ID cannot be found in the HTML
     */
    public function extractBuildId(string $html): string
    {
        if (preg_match('/"buildId":"([^"]+)"/', $html, $matches) !== 1) {
            throw new ViaBOVAGException('Could not extract build ID from HTML.');
        }

        return $matches[1];
    }

    /**
     * Parse a search results JSON response from the REST API.
     *
     * Expects the direct REST API shape: `{ results: [...], count: N }`.
     *
     * @throws ViaBOVAGException if the JSON is invalid or missing expected structure
     */
    public function parseSearchResults(string $json, int $currentPage): SearchResult
    {
        $data = $this->decodeJson($json);

        $listings = array_map(
            $this->mapListing(...),
            $data['results'] ?? [],
        );

        return new SearchResult(
            listings: $listings,
            totalCount: (int) ($data['count'] ?? 0),
            currentPage: $currentPage,
        );
    }

    /**
     * Parse facet data from the REST API facets endpoint.
     *
     * Expects the direct REST API shape: `{ count, facets: [...], ... }`.
     *
     * @return SearchFacet[]
     *
     * @throws ViaBOVAGException if the JSON is invalid
     */
    public function parseSearchFacets(string $json): array
    {
        $data = $this->decodeJson($json);

        return $this->mapFacets($data);
    }

    /**
     * Parse a vehicle detail JSON response into a ListingDetail DTO.
     *
     * @throws ViaBOVAGException if the JSON is invalid or missing expected structure
     */
    public function parseDetail(string $json): ListingDetail
    {
        $data = $this->decodeJson($json);

        $vehicleData = $data['pageProps']['vehicle'] ?? null;

        if ($vehicleData === null) {
            throw new ViaBOVAGException('Invalid detail response: missing vehicle data.');
        }

        $advertisement = $vehicleData['advertisement'] ?? [];
        $vehicleSpecs = $vehicleData['vehicle'] ?? [];
        $identification = $vehicleSpecs['identification'] ?? [];
        $financial = $vehicleSpecs['financial'] ?? [];
        $certainties = $advertisement['certainties'] ?? [];

        $media = $this->mapDetailMedia($advertisement['media'] ?? []);
        $specGroups = $this->mapSpecificationGroups($vehicleSpecs['specificationGroups'] ?? []);
        $accessories = $this->mapAccessories($vehicleSpecs['accessories'] ?? []);
        $optionGroups = $this->mapOptionGroups($vehicleSpecs['optionGroups'] ?? []);

        $company = $this->mapDetailCompany($advertisement['company'] ?? []);

        $vehicle = $this->mapDetailVehicle($vehicleSpecs);

        // Title and price in the detail response are structured objects
        $title = $this->extractFormattedValue($advertisement['title'] ?? '');
        $price = $this->extractPriceFromDetail($advertisement['price'] ?? 0);

        // Financial data from the vehicle section
        $leasePrice = $this->extractPriceOrNull($financial['leasePrice'] ?? null);
        $roadTax = $this->extractPriceOrNull($financial['roadTax'] ?? null);
        $fuelConsumption = $this->extractFormattedValueOrNull($financial['fuelConsumption'] ?? null);
        $bijtellingPercentage = $this->extractFormattedValueOrNull($financial['bijtellingPercentage'] ?? null);
        $financingProvider = $this->extractFinancingProvider($advertisement, $financial);

        return new ListingDetail(
            id: $vehicleData['id'] ?? '',
            title: $title,
            price: $price,
            description: $this->extractFormattedValueOrNull($advertisement['comments'] ?? null),
            media: $media,
            vehicle: $vehicle,
            company: $company,
            specificationGroups: $specGroups,
            accessories: $accessories,
            optionGroups: $optionGroups,
            licensePlate: $this->extractFormattedValueOrNull($identification['vehicleLicencePlate'] ?? null),
            externalNumber: $this->extractFormattedValueOrNull($identification['vehicleNumberExternal'] ?? null),
            structuredData: $vehicleData['structuredData'] ?? null,
            priceExcludesVat: (bool) ($advertisement['priceExcludesVat'] ?? false),
            url: $vehicleData['url'] ?? null,
            mobilityType: MobilityType::fromApiValue($vehicleData['mobilityType'] ?? ''),
            isEligibleForVehicleReport: (bool) ($advertisement['isEligibleForVehicleReport'] ?? false),
            financingProvider: $financingProvider,
            leasePrice: $leasePrice,
            roadTax: $roadTax,
            fuelConsumption: $fuelConsumption,
            bijtellingPercentage: $bijtellingPercentage,
            returnWarrantyMileage: isset($certainties['returnWarrantyMileage']) ? (int) $certainties['returnWarrantyMileage'] : null,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $mediaItems
     * @return Media[]
     */
    private function mapDetailMedia(array $mediaItems): array
    {
        return array_map(
            fn (array $item): Media => new Media(
                type: MediaType::tryFrom($item['type'] ?? 'image') ?? MediaType::Image,
                url: $item['url'] ?? '',
            ),
            $mediaItems,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     * @return SpecificationGroup[]
     */
    private function mapSpecificationGroups(array $groups): array
    {
        return array_map(
            fn (array $group): SpecificationGroup => new SpecificationGroup(
                name: $group['name'] ?? '',
                specifications: array_map(
                    fn (array $spec): Specification => new Specification(
                        label: $spec['label'] ?? '',
                        value: isset($spec['value']) && is_string($spec['value']) ? $spec['value'] : null,
                        formattedValue: $spec['formattedValue'] ?? null,
                        hasValue: (bool) ($spec['hasValue'] ?? false),
                        formattedValueWithoutUnit: $spec['formattedValueWithoutUnit'] ?? null,
                    ),
                    $group['specifications'] ?? [],
                ),
                group: $group['group'] ?? null,
                iconName: $group['iconName'] ?? null,
            ),
            $groups,
        );
    }

    /**
     * @param  array<int, array<string, mixed>|string>  $items
     * @return Accessory[]
     */
    private function mapAccessories(array $items): array
    {
        return array_map(
            fn (array|string $item): Accessory => new Accessory(
                name: is_array($item) ? ($item['name'] ?? '') : $item,
            ),
            $items,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     * @return OptionGroup[]
     */
    private function mapOptionGroups(array $groups): array
    {
        return array_map(
            fn (array $group): OptionGroup => new OptionGroup(
                name: $group['name'] ?? '',
                options: $group['options'] ?? [],
            ),
            $groups,
        );
    }

    /**
     * @param  array<string, mixed>  $advertisement
     * @param  array<string, mixed>  $financial
     */
    private function extractFinancingProvider(array $advertisement, array $financial): ?string
    {
        $financing = $advertisement['financing'] ?? $financial['financing'] ?? null;

        return is_array($financing) ? ($financing['provider'] ?? null) : null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function mapListing(array $item): Listing
    {
        $vehicleData = $item['vehicle'] ?? [];
        $companyData = $item['company'] ?? [];

        return new Listing(
            id: $item['id'] ?? '',
            mobilityType: MobilityType::fromApiValue($item['mobilityType'] ?? '') ?? throw new ViaBOVAGException('Unknown mobility type: '.($item['mobilityType'] ?? '')),
            url: $item['url'] ?? '',
            friendlyUriPart: $item['friendlyUriPart'] ?? '',
            externalAdvertisementUrl: $item['externalAdvertisementUrl'] ?? null,
            imageUrl: $item['imageUrl'] ?? null,
            title: $item['title'] ?? '',
            price: (int) ($item['price'] ?? 0),
            isFinanceable: (bool) ($item['isFinanceable'] ?? false),
            vehicle: new Vehicle(
                type: $vehicleData['type'] ?? '',
                brand: $vehicleData['brand'] ?? '',
                model: $vehicleData['model'] ?? '',
                mileage: (int) ($vehicleData['mileage'] ?? 0),
                mileageUnit: MileageUnit::tryFrom($vehicleData['mileageUnit'] ?? 'kilometer') ?? MileageUnit::Kilometer,
                year: (int) ($vehicleData['year'] ?? 0),
                month: isset($vehicleData['month']) ? (int) $vehicleData['month'] : null,
                fuelTypes: $vehicleData['fuelTypes'] ?? [],
                color: $vehicleData['color'] ?? null,
                bodyType: $vehicleData['bodyType'] ?? null,
                transmissionType: $vehicleData['transmissionType'] ?? null,
                engineCapacity: isset($vehicleData['engineCapacity']) ? (int) $vehicleData['engineCapacity'] : null,
                enginePower: isset($vehicleData['enginePower']) ? (int) $vehicleData['enginePower'] : null,
                warranties: $vehicleData['warranties'] ?? [],
                certaintyKeys: $vehicleData['certaintyKeys'] ?? [],
                fullyServiced: (bool) ($vehicleData['fullyServiced'] ?? false),
                hasBovagChecklist: (bool) ($vehicleData['hasBovagChecklist'] ?? false),
                bovagWarranty: $vehicleData['bovagWarranty'] ?? null,
                hasReturnWarranty: (bool) ($vehicleData['hasReturnWarranty'] ?? false),
                servicedOnDelivery: (bool) ($vehicleData['servicedOnDelivery'] ?? false),
            ),
            company: new Company(
                name: $companyData['name'] ?? '',
                city: $companyData['city'] ?? null,
                phoneNumber: $companyData['phoneNumber'] ?? null,
                websiteUrl: $companyData['websiteUrl'] ?? null,
                callTrackingCode: $companyData['callTrackingCode'] ?? null,
            ),
            priceExcludesVat: (bool) ($item['priceExcludesVat'] ?? false),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function mapDetailCompany(array $data): Company
    {
        $address = $data['address'] ?? [];
        $contact = $data['contact'] ?? [];
        $review = $data['review'] ?? [];
        $coordinates = $address['coordinates'] ?? [];

        return new Company(
            name: $data['name'] ?? '',
            city: $address['city'] ?? null,
            phoneNumber: $contact['phoneNumber'] ?? null,
            websiteUrl: $data['websiteUrl'] ?? null,
            callTrackingCode: $contact['callTrackingCode'] ?? null,
            street: $address['street'] ?? null,
            postalCode: $address['postalCode'] ?? null,
            latitude: isset($coordinates['latitude']) ? (float) $coordinates['latitude'] : null,
            longitude: isset($coordinates['longitude']) ? (float) $coordinates['longitude'] : null,
            reviewScore: isset($review['rating']) ? (float) $review['rating'] : null,
            reviewCount: isset($review['numberOfReviews']) ? (int) $review['numberOfReviews'] : null,
            id: isset($data['id']) ? (int) $data['id'] : null,
            houseNumber: $address['houseNumber'] ?? null,
            houseNumberExtension: isset($address['houseNumberExtension']) && $address['houseNumberExtension'] !== ''
                ? $address['houseNumberExtension']
                : null,
            countryCode: $address['countryCode'] ?? null,
            isOpenNow: isset($contact['isOpenNow']) ? (bool) $contact['isOpenNow'] : null,
            reviewProvider: $review['provider'] ?? null,
        );
    }

    /**
     * @param  array<string, mixed>  $specs
     */
    private function mapDetailVehicle(array $specs): Vehicle
    {
        $general = $specs['general'] ?? [];
        $technical = $specs['technical'] ?? [];
        $history = $specs['history'] ?? [];
        $performance = $specs['performance'] ?? [];
        $fuel = $specs['fuel'] ?? [];
        $dimensions = $specs['dimensions'] ?? [];
        $weight = $specs['weight'] ?? [];
        $interior = $specs['interior'] ?? [];
        $certainties = $specs['certainties'] ?? [];

        // Build fuelTypes from detail response fuel section
        $fuelTypes = [];
        $primaryFuelType = $this->extractFormattedValueOrNull($fuel['primaryFuelType'] ?? null);
        $secondaryFuelType = $this->extractFormattedValueOrNull($fuel['secondaryFuelType'] ?? null);

        if ($primaryFuelType !== null) {
            $fuelTypes[] = $primaryFuelType;
        }

        if ($secondaryFuelType !== null) {
            $fuelTypes[] = $secondaryFuelType;
        }

        // Derive bovagWarranty from certaintyKeys
        $certaintyKeys = $specs['certaintyKeys'] ?? [];
        $bovagWarranty = $this->deriveBovagWarrantyFromKeys($certaintyKeys);

        // Derive warranties from certaintyKeys
        $warranties = $this->deriveWarrantiesFromKeys($certaintyKeys);

        return new Vehicle(
            type: $this->extractValue($general['vehicleType'] ?? null) ?? '',
            brand: $this->extractFormattedValue($general['brand'] ?? ''),
            model: $this->extractFormattedValue($general['model'] ?? ''),
            mileage: $this->extractNumericValue($history['mileage'] ?? null) ?? 0,
            mileageUnit: MileageUnit::Kilometer,
            year: $this->extractNumericValue($history['productionYear'] ?? null) ?? 0,
            month: $this->extractNumericValue($history['productionMonth'] ?? null),
            fuelTypes: $fuelTypes,
            color: $this->extractColorFromSpecs($specs['specificationGroups'] ?? []),
            bodyType: $this->extractValue($general['bodyType'] ?? null),
            transmissionType: $this->extractFormattedValueOrNull($technical['transmission'] ?? null),
            engineCapacity: $this->extractNumericValue($technical['engineCapacity'] ?? null),
            enginePower: $this->extractNumericValue($performance['enginePower'] ?? null),
            warranties: $warranties,
            certaintyKeys: $certaintyKeys,
            fullyServiced: $this->extractBoolValue($certainties['isFullyMaintained'] ?? null) ?? false,
            hasBovagChecklist: in_array('BovagChecklist40Point', $certaintyKeys),
            bovagWarranty: $bovagWarranty,
            hasReturnWarranty: in_array('ReturnWarranty', $certaintyKeys),
            servicedOnDelivery: in_array('CarServicedOnDelivery', $certaintyKeys),
            edition: $this->extractFormattedValueOrNull($general['edition'] ?? null),
            condition: $this->extractValue($general['condition'] ?? null),
            modelYear: $this->extractNumericValue($general['modelYear'] ?? null),
            frameType: $this->extractFormattedValueOrNull($general['frameType'] ?? null),
            primaryFuelType: $this->extractValue($fuel['primaryFuelType'] ?? null),
            secondaryFuelType: $this->extractValue($fuel['secondaryFuelType'] ?? null),
            isHybridVehicle: $this->extractBoolValue($fuel['isHybridVehicle'] ?? null),
            energyLabel: $this->extractFormattedValueOrNull($fuel['energyLabel'] ?? null),
            fuelConsumptionCombined: $this->extractFormattedValueOrNull($fuel['fuelConsumptionCombined'] ?? null),
            gearCount: $this->extractNumericValue($technical['gearCount'] ?? null),
            isImported: $this->extractBoolValue($history['isImported'] ?? null),
            hasNapLabel: $this->extractBoolValue($certainties['hasNapLabel'] ?? null),
            wheelSize: $this->extractFormattedValueOrNull($dimensions['wheelSize'] ?? null),
            emptyWeight: $this->extractNumericValue($weight['emptyWeight'] ?? null),
            maxWeight: $this->extractNumericValue($weight['maxWeight'] ?? null),
            bedCount: $this->extractNumericValue($interior['bedCount'] ?? null),
            sanitary: $this->extractFormattedValueOrNull($interior['sanitary'] ?? null),
        );
    }

    /**
     * Derive the BOVAG warranty tier from certainty keys.
     *
     * @param  string[]  $certaintyKeys
     */
    private function deriveBovagWarrantyFromKeys(array $certaintyKeys): ?string
    {
        foreach ($certaintyKeys as $key) {
            if (str_starts_with($key, 'BovagWarranty')) {
                return match ($key) {
                    'BovagWarranty12Months' => 'TwaalfMaanden',
                    'BovagWarranty6Months' => 'ZesMaanden',
                    'BovagWarranty3Months' => 'DrieMaanden',
                    default => $key,
                };
            }
        }

        return null;
    }

    /**
     * Derive warranty slugs from certainty keys.
     *
     * @param  string[]  $certaintyKeys
     * @return string[]
     */
    private function deriveWarrantiesFromKeys(array $certaintyKeys): array
    {
        $warranties = [];
        foreach ($certaintyKeys as $key) {
            $warranty = match ($key) {
                'BovagWarranty12Months' => 'bovag12maanden',
                'BovagWarranty6Months' => 'bovag6maanden',
                'BovagWarranty3Months' => 'bovag3maanden',
                default => null,
            };
            if ($warranty !== null) {
                $warranties[] = $warranty;
            }
        }

        return $warranties;
    }

    /**
     * Map search facets from the server response.
     *
     * @param  array<string, mixed>|null  $facetData
     * @return SearchFacet[]
     */
    private function mapFacets(?array $facetData): array
    {
        if ($facetData === null) {
            return [];
        }

        $rawFacets = $facetData['facets'] ?? [];

        return array_map(
            function (array $facet): SearchFacet {
                $options = $this->mapFacetOptions($facet['options'] ?? []);

                $optionCategories = array_map(
                    fn (array $category): SearchFacetOptionCategory => new SearchFacetOptionCategory(
                        label: $category['label'] ?? '',
                        options: $this->mapFacetOptions($category['options'] ?? []),
                    ),
                    $facet['optionCategories'] ?? [],
                );

                return new SearchFacet(
                    name: $facet['name'] ?? '',
                    label: $facet['label'] ?? '',
                    disabled: (bool) ($facet['disabled'] ?? false),
                    selected: (bool) ($facet['selected'] ?? false),
                    hidden: (bool) ($facet['hidden'] ?? false),
                    options: $options,
                    optionCategories: $optionCategories,
                    selectedValues: $facet['selectedValues'] ?? [],
                    tooltip: $facet['tooltip'] ?? null,
                    hasIcons: (bool) ($facet['hasIcons'] ?? false),
                );
            },
            $rawFacets,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     * @return SearchFacetOption[]
     */
    private function mapFacetOptions(array $options): array
    {
        return array_map(
            $this->mapFacetOption(...),
            $options,
        );
    }

    /**
     * @param  array<string, mixed>  $option
     */
    private function mapFacetOption(array $option): SearchFacetOption
    {
        return new SearchFacetOption(
            name: $option['name'] ?? '',
            label: $option['label'] ?? '',
            count: isset($option['count']) ? (int) $option['count'] : null,
            selected: (bool) ($option['selected'] ?? false),
        );
    }

    /**
     * Extract color from specification groups by looking for the "Kleur" label.
     *
     * @param  array<int, array<string, mixed>>  $specGroups
     */
    private function extractColorFromSpecs(array $specGroups): ?string
    {
        foreach ($specGroups as $group) {
            foreach ($group['specifications'] ?? [] as $spec) {
                if (($spec['label'] ?? '') === 'Kleur' && ($spec['hasValue'] ?? false)) {
                    $value = $spec['formattedValue'] ?? '';

                    return $value !== '' ? $value : null;
                }
            }
        }

        return null;
    }

    /**
     * Extract formattedValue from a structured field, or return the string directly.
     *
     * @param  array<string, mixed>|string  $field
     */
    private function extractFormattedValue(array|string $field): string
    {
        if (is_string($field)) {
            return $field;
        }

        return $field['formattedValue'] ?? '';
    }

    /**
     * Extract formattedValue from a structured field, return null if empty or missing.
     *
     * @param  array<string, mixed>|null  $field
     */
    private function extractFormattedValueOrNull(?array $field): ?string
    {
        if ($field === null) {
            return null;
        }

        if (! ($field['hasValue'] ?? false)) {
            return null;
        }

        $value = $field['formattedValue'] ?? '';

        return $value !== '' ? $value : null;
    }

    /**
     * Extract the 'value' key from a structured field.
     *
     * @param  array<string, mixed>|null  $field
     */
    private function extractValue(?array $field): ?string
    {
        if ($field === null) {
            return null;
        }

        if (! ($field['hasValue'] ?? false)) {
            return null;
        }

        $value = $field['value'] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * Extract a numeric value from a structured field's formattedValue.
     *
     * Extracts the first number (including thousand separators) from the string.
     * This avoids concatenating multiple numbers, e.g. "83pk (61kW)" becomes 83 (not 8361).
     *
     * @param  array<string, mixed>|null  $field
     */
    private function extractNumericValue(?array $field): ?int
    {
        if ($field === null) {
            return null;
        }

        if (! ($field['hasValue'] ?? false)) {
            return null;
        }

        $value = $field['formattedValue'] ?? '';

        // Match the first number, including dots as thousand separators (e.g. "16.165 km" => "16.165")
        if (preg_match('/(\d[\d.]*)/', $value, $matches) !== 1) {
            return null;
        }

        // Strip thousand-separator dots and cast to int
        $numeric = str_replace('.', '', $matches[1]);

        return $numeric !== '' ? (int) $numeric : null;
    }

    /**
     * Extract a boolean value from a structured field.
     *
     * @param  array<string, mixed>|null  $field
     */
    private function extractBoolValue(?array $field): ?bool
    {
        if ($field === null) {
            return null;
        }

        if (! ($field['hasValue'] ?? false)) {
            return null;
        }

        $value = $field['value'] ?? null;

        return is_bool($value) ? $value : null;
    }

    /**
     * Extract price from detail response (price is a structured object).
     *
     * @param  array<string, mixed>|int  $price
     */
    private function extractPriceFromDetail(array|int $price): int
    {
        if (is_int($price)) {
            return $price;
        }

        return $this->parseDutchPrice($price);
    }

    /**
     * Extract a price from a structured field, returning null if no value.
     *
     * @param  array<string, mixed>|null  $field
     */
    private function extractPriceOrNull(?array $field): ?int
    {
        if ($field === null) {
            return null;
        }

        if (! ($field['hasValue'] ?? false)) {
            return null;
        }

        return $this->parseDutchPrice($field);
    }

    /**
     * Parse a Dutch price format from a structured field.
     *
     * Handles formats like "€ 19.850,-" or "19.850,50".
     * Dutch format uses dots as thousands separators and commas as decimal separators.
     *
     * @param  array<string, mixed>  $field
     */
    private function parseDutchPrice(array $field): int
    {
        $formatted = (string) ($field['formattedValueWithoutUnit'] ?? $field['formattedValue'] ?? '0');

        // Remove currency symbol and whitespace
        $cleaned = preg_replace('/[€\s]/', '', $formatted);

        // Replace comma with dot for decimal separator, remove thousands dots
        // "19.850,-" → "19850.-" → "19850"
        // "19.850,50" → "19850.50"
        $cleaned = str_replace('.', '', $cleaned ?? $formatted);
        $cleaned = str_replace(',', '.', $cleaned);

        // Remove trailing dash (e.g. "19850.-" → "19850.")
        $cleaned = rtrim($cleaned, '-');

        // Parse as float to handle cents, then round to int (whole euros)
        $value = (float) $cleaned;

        return (int) round($value);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $json): array
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new ViaBOVAGException('Failed to decode JSON response: '.$jsonException->getMessage(), 0, $jsonException);
        }

        if (! is_array($data)) {
            throw new ViaBOVAGException('Expected JSON object, got '.gettype($data));
        }

        return $data;
    }
}
