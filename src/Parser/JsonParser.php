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
use NiekNijland\ViaBOVAG\Data\OptionGroup;
use NiekNijland\ViaBOVAG\Data\SearchResult;
use NiekNijland\ViaBOVAG\Data\Specification;
use NiekNijland\ViaBOVAG\Data\SpecificationGroup;
use NiekNijland\ViaBOVAG\Data\Vehicle;
use NiekNijland\ViaBOVAG\Exception\ViaBOVAGException;

class JsonParser
{
    /**
     * Extract the Next.js build ID from an HTML page.
     */
    public function extractBuildId(string $html): string
    {
        if (preg_match('/"buildId":"([^"]+)"/', $html, $matches) !== 1) {
            throw new ViaBOVAGException('Could not extract build ID from HTML.');
        }

        return $matches[1];
    }

    /**
     * Parse a search results JSON response into a SearchResult DTO.
     */
    public function parseSearchResults(string $json, int $currentPage): SearchResult
    {
        $data = $this->decodeJson($json);

        $serverResults = $data['pageProps']['serverSearchResults'] ?? null;

        if ($serverResults === null) {
            throw new ViaBOVAGException('Invalid search response: missing serverSearchResults.');
        }

        $listings = array_map(
            $this->mapListing(...),
            $serverResults['results'] ?? [],
        );

        return new SearchResult(
            listings: $listings,
            totalCount: (int) ($serverResults['count'] ?? 0),
            currentPage: $currentPage,
        );
    }

    /**
     * Parse a vehicle detail JSON response into a ListingDetail DTO.
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

        $media = array_map(
            fn (array $item): Media => new Media(
                type: MediaType::tryFrom($item['type'] ?? 'image') ?? MediaType::Image,
                url: $item['url'] ?? '',
            ),
            $advertisement['media'] ?? [],
        );

        $specGroups = array_map(
            fn (array $group): SpecificationGroup => new SpecificationGroup(
                name: $group['name'] ?? '',
                specifications: array_map(
                    fn (array $spec): Specification => new Specification(
                        label: $spec['label'] ?? '',
                        value: $spec['value'] ?? null,
                        formattedValue: $spec['formattedValue'] ?? null,
                    ),
                    $group['specifications'] ?? [],
                ),
            ),
            $vehicleSpecs['specificationGroups'] ?? [],
        );

        $accessories = array_map(
            fn (array|string $item): Accessory => new Accessory(
                name: is_array($item) ? ($item['name'] ?? '') : $item,
            ),
            $vehicleSpecs['accessories'] ?? [],
        );

        $optionGroups = array_map(
            fn (array $group): OptionGroup => new OptionGroup(
                name: $group['name'] ?? '',
                options: $group['options'] ?? [],
            ),
            $vehicleSpecs['optionGroups'] ?? [],
        );

        $company = $this->mapDetailCompany($advertisement['company'] ?? []);

        $vehicle = $this->mapDetailVehicle($vehicleSpecs);

        // Title and price in the detail response are structured objects
        $title = $this->extractFormattedValue($advertisement['title'] ?? '');
        $price = $this->extractPriceFromDetail($advertisement['price'] ?? 0);

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
        );
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
            mobilityType: $item['mobilityType'] ?? '',
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
                mileageUnit: $vehicleData['mileageUnit'] ?? 'kilometer',
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

        return new Vehicle(
            type: $this->extractValue($general['vehicleType'] ?? null) ?? '',
            brand: $this->extractFormattedValue($general['brand'] ?? ''),
            model: $this->extractFormattedValue($general['model'] ?? ''),
            mileage: $this->extractNumericValue($history['mileage'] ?? null) ?? 0,
            mileageUnit: 'kilometer',
            year: $this->extractNumericValue($history['productionYear'] ?? null) ?? 0,
            month: $this->extractNumericValue($history['productionMonth'] ?? null),
            fuelTypes: [],
            color: $this->extractColorFromSpecs($specs['specificationGroups'] ?? []),
            bodyType: $this->extractValue($general['bodyType'] ?? null),
            transmissionType: $this->extractFormattedValueOrNull($technical['transmission'] ?? null),
            engineCapacity: $this->extractNumericValue($technical['engineCapacity'] ?? null),
            enginePower: $this->extractNumericValue($performance['enginePower'] ?? null),
            warranties: [],
            certaintyKeys: $specs['certaintyKeys'] ?? [],
            fullyServiced: $this->extractBoolValue($specs['certainties']['isFullyMaintained'] ?? null) ?? false,
            hasBovagChecklist: in_array('BovagChecklist40Point', $specs['certaintyKeys'] ?? []),
            bovagWarranty: null,
            hasReturnWarranty: in_array('ReturnWarranty', $specs['certaintyKeys'] ?? []),
            servicedOnDelivery: in_array('CarServicedOnDelivery', $specs['certaintyKeys'] ?? []),
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
        // Strip non-numeric characters (e.g., "49.874 km" → "49874", "2015" → "2015")
        $numeric = preg_replace('/[^0-9]/', '', $value);

        return $numeric !== '' && $numeric !== null ? (int) $numeric : null;
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

        // Parse Dutch price format: "€ 19.850,-" or "19.850,50"
        // Dutch format uses dots as thousands separators and commas as decimal separators.
        // Strip the currency symbol/whitespace, replace dots (thousands sep), parse as numeric.
        $formatted = (string) ($price['formattedValueWithoutUnit'] ?? $price['formattedValue'] ?? '0');

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
