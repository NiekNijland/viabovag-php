<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Tests;

use NiekNijland\ViaBOVAG\Data\DriversLicense;
use NiekNijland\ViaBOVAG\Data\MobilityType;
use NiekNijland\ViaBOVAG\Exception\ViaBOVAGException;
use NiekNijland\ViaBOVAG\Parser\JsonParser;
use PHPUnit\Framework\TestCase;

class JsonParserTest extends TestCase
{
    private JsonParser $parser;

    protected function setUp(): void
    {
        $this->parser = new JsonParser;
    }

    private function fixture(string $name): string
    {
        return file_get_contents(__DIR__.'/Fixtures/'.$name);
    }

    // --- Build ID Extraction ---

    public function test_extracts_build_id_from_valid_html(): void
    {
        $html = '<script id="__NEXT_DATA__">{"buildId":"abc123def"}</script>';

        $this->assertSame('abc123def', $this->parser->extractBuildId($html));
    }

    public function test_extracts_build_id_from_complex_html(): void
    {
        $html = $this->fixture('homepage.html');

        $this->assertSame('PQS_ur-FpJe0R6JUyWiD2', $this->parser->extractBuildId($html));
    }

    public function test_throws_on_missing_build_id(): void
    {
        $this->expectException(ViaBOVAGException::class);
        $this->expectExceptionMessage('Could not extract build ID from HTML.');

        $this->parser->extractBuildId('<html><body>No build ID</body></html>');
    }

    public function test_throws_on_empty_html(): void
    {
        $this->expectException(ViaBOVAGException::class);
        $this->expectExceptionMessage('Could not extract build ID from HTML.');

        $this->parser->extractBuildId('');
    }

    // --- JSON Decoding ---

    public function test_throws_on_invalid_json(): void
    {
        $this->expectException(ViaBOVAGException::class);
        $this->expectExceptionMessage('Failed to decode JSON response');

        $this->parser->parseSearchResults('not json', 1);
    }

    public function test_throws_on_json_string(): void
    {
        $this->expectException(ViaBOVAGException::class);
        $this->expectExceptionMessage('Expected JSON object, got string');

        $this->parser->parseSearchResults('"just a string"', 1);
    }

    public function test_throws_on_json_number(): void
    {
        $this->expectException(ViaBOVAGException::class);
        $this->expectExceptionMessage('Expected JSON object, got integer');

        $this->parser->parseSearchResults('42', 1);
    }

    // --- Search Results Parsing ---

    public function test_parses_empty_search_results(): void
    {
        $json = json_encode([
            'results' => [],
            'count' => 0,
        ]);

        $result = $this->parser->parseSearchResults($json, 1);

        $this->assertSame([], $result->listings);
        $this->assertSame(0, $result->totalCount);
        $this->assertSame(1, $result->currentPage);
        $this->assertSame(0, $result->totalPages());
        $this->assertFalse($result->hasNextPage());
        $this->assertFalse($result->hasPreviousPage());
    }

    public function test_parses_search_results_with_current_page(): void
    {
        $json = json_encode([
            'results' => [],
            'count' => 100,
        ]);

        $result = $this->parser->parseSearchResults($json, 3);

        $this->assertSame(3, $result->currentPage);
        $this->assertTrue($result->hasPreviousPage());
        $this->assertTrue($result->hasNextPage());
    }

    public function test_parses_search_results_on_last_page(): void
    {
        $json = json_encode([
            'results' => [],
            'count' => 48,
        ]);

        // 48 results / 24 per page = 2 pages, so page 2 is the last
        $result = $this->parser->parseSearchResults($json, 2);

        $this->assertSame(2, $result->totalPages());
        $this->assertFalse($result->hasNextPage());
        $this->assertTrue($result->hasPreviousPage());
    }

    public function test_parses_listing_with_unknown_mobility_type_throws(): void
    {
        $json = json_encode([
            'results' => [
                [
                    'id' => 'test-id',
                    'mobilityType' => 'spaceship',
                    'url' => '/test',
                    'friendlyUriPart' => 'test',
                    'title' => 'Test',
                    'price' => 1000,
                    'vehicle' => ['brand' => 'Test', 'model' => 'X'],
                    'company' => ['name' => 'Dealer'],
                ],
            ],
            'count' => 1,
        ]);

        $this->expectException(ViaBOVAGException::class);
        $this->expectExceptionMessage('Unknown mobility type: spaceship');

        $this->parser->parseSearchResults($json, 1);
    }

    public function test_parses_car_listing_mobility_type_from_api_value(): void
    {
        $json = json_encode([
            'results' => [
                [
                    'id' => 'test-id',
                    'mobilityType' => 'auto',
                    'url' => '/test',
                    'friendlyUriPart' => 'test',
                    'title' => 'Test',
                    'price' => 1000,
                    'vehicle' => ['brand' => 'Test', 'model' => 'X'],
                    'company' => ['name' => 'Dealer'],
                ],
            ],
            'count' => 1,
        ]);

        $result = $this->parser->parseSearchResults($json, 1);

        $this->assertSame(MobilityType::Car, $result->listings[0]->mobilityType);
    }

    public function test_parses_bicycle_listing_mobility_type_from_api_value(): void
    {
        $json = json_encode([
            'results' => [
                [
                    'id' => 'test-id',
                    'mobilityType' => 'fiets',
                    'url' => '/test',
                    'friendlyUriPart' => 'test',
                    'title' => 'Test',
                    'price' => 1000,
                    'vehicle' => ['brand' => 'Test', 'model' => 'X'],
                    'company' => ['name' => 'Dealer'],
                ],
            ],
            'count' => 1,
        ]);

        $result = $this->parser->parseSearchResults($json, 1);

        $this->assertSame(MobilityType::Bicycle, $result->listings[0]->mobilityType);
    }

    public function test_parses_search_facets_with_options_and_categories(): void
    {
        $json = json_encode([
            'count' => 42,
            'facets' => [
                [
                    'name' => 'brand',
                    'label' => 'Merk',
                    'disabled' => false,
                    'selected' => true,
                    'hidden' => false,
                    'hasIcons' => true,
                    'tooltip' => 'Select a brand',
                    'selectedValues' => ['yamaha'],
                    'options' => [
                        ['name' => 'yamaha', 'label' => 'Yamaha', 'count' => 42, 'selected' => true],
                        ['name' => 'honda', 'label' => 'Honda', 'count' => 30, 'selected' => false],
                    ],
                    'optionCategories' => [
                        [
                            'label' => 'Popular',
                            'options' => [
                                ['name' => 'yamaha', 'label' => 'Yamaha', 'count' => 42, 'selected' => true],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $facets = $this->parser->parseSearchFacets($json);

        $this->assertCount(1, $facets);

        $facet = $facets[0];
        $this->assertSame('brand', $facet->name);
        $this->assertSame('Merk', $facet->label);
        $this->assertTrue($facet->selected);
        $this->assertFalse($facet->disabled);
        $this->assertFalse($facet->hidden);
        $this->assertTrue($facet->hasIcons);
        $this->assertSame('Select a brand', $facet->tooltip);
        $this->assertSame(['yamaha'], $facet->selectedValues);

        $this->assertCount(2, $facet->options);
        $this->assertSame('yamaha', $facet->options[0]->name);
        $this->assertSame(42, $facet->options[0]->count);
        $this->assertTrue($facet->options[0]->selected);

        $this->assertCount(1, $facet->optionCategories);
        $this->assertSame('Popular', $facet->optionCategories[0]->label);
        $this->assertCount(1, $facet->optionCategories[0]->options);
    }

    public function test_parses_empty_facets(): void
    {
        $json = json_encode([
            'count' => 0,
            'facets' => [],
        ]);

        $facets = $this->parser->parseSearchFacets($json);

        $this->assertSame([], $facets);
    }

    // --- Detail Parsing ---

    public function test_throws_on_missing_vehicle_data(): void
    {
        $this->expectException(ViaBOVAGException::class);
        $this->expectExceptionMessage('missing vehicle data');

        $this->parser->parseDetail('{"pageProps":{}}');
    }

    public function test_parses_detail_from_fixture(): void
    {
        $json = $this->fixture('listing-detail.json');
        $detail = $this->parser->parseDetail($json);

        $this->assertNotEmpty($detail->id);
        $this->assertNotEmpty($detail->title);
        $this->assertGreaterThan(0, $detail->price);
        $this->assertNotEmpty($detail->media);
        $this->assertNotEmpty($detail->vehicle->brand);
        $this->assertNotEmpty($detail->company->name);
    }

    public function test_parses_detail_accessories_as_array(): void
    {
        $json = $this->fixture('listing-detail.json');
        $detail = $this->parser->parseDetail($json);

        // Fixture has empty accessories — verify parsed as array
        $this->assertIsArray($detail->accessories);
    }

    public function test_parses_detail_option_groups(): void
    {
        $json = $this->fixture('listing-detail.json');
        $detail = $this->parser->parseDetail($json);

        // Option groups may be empty in some fixtures, but should be an array
        $this->assertIsArray($detail->optionGroups);
    }

    public function test_parses_engine_power_in_kw_when_both_hp_and_kw_are_present(): void
    {
        $json = $this->buildDetailJson([
            'performance' => [
                'enginePower' => ['formattedValue' => '83pk (61kW)', 'hasValue' => true],
            ],
        ]);

        $detail = $this->parser->parseDetail($json);

        $this->assertSame(61, $detail->vehicle->enginePower);
    }

    public function test_parses_drivers_license_from_general_section(): void
    {
        $json = $this->buildDetailJson([
            'general' => [
                'vehicleType' => ['value' => 'motor', 'formattedValue' => 'Motor', 'hasValue' => true],
                'brand' => ['value' => 'Test', 'formattedValue' => 'Test', 'hasValue' => true],
                'model' => ['value' => 'Model', 'formattedValue' => 'Model', 'hasValue' => true],
                'driversLicense' => ['value' => 'A2', 'formattedValue' => 'A2', 'hasValue' => true],
            ],
        ]);

        $detail = $this->parser->parseDetail($json);

        $this->assertSame(DriversLicense::A2, $detail->driversLicense);
    }

    public function test_parses_drivers_license_from_specification_groups_when_general_field_is_missing(): void
    {
        $json = $this->buildDetailJson([
            'specificationGroups' => [[
                'name' => 'Algemeen',
                'specifications' => [[
                    'label' => 'Rijbewijs',
                    'formattedValue' => 'A1',
                    'hasValue' => true,
                ]],
            ]],
        ]);

        $detail = $this->parser->parseDetail($json);

        $this->assertSame(DriversLicense::A1, $detail->driversLicense);
    }

    public function test_returns_plain_text_description_accessor(): void
    {
        $json = $this->buildDetailJsonWithDescription('<p>Line 1<br />Line 2 &amp; more</p>');

        $detail = $this->parser->parseDetail($json);

        $this->assertSame("Line 1\nLine 2 & more", $detail->descriptionText());
    }

    // --- BOVAG Warranty Derivation ---

    public function test_parses_detail_bovag_warranty_12_months(): void
    {
        $json = $this->fixture('listing-detail.json');
        $detail = $this->parser->parseDetail($json);

        // Fixture has BovagWarranty12Months in certaintyKeys
        $this->assertSame('TwaalfMaanden', $detail->vehicle->bovagWarranty);
        $this->assertContains('bovag12maanden', $detail->vehicle->warranties);
    }

    public function test_parses_bovag_warranty_6_months_from_certainty_keys(): void
    {
        $json = $this->buildDetailJson(['certaintyKeys' => ['BovagWarranty6Months']]);
        $detail = $this->parser->parseDetail($json);

        $this->assertSame('ZesMaanden', $detail->vehicle->bovagWarranty);
        $this->assertContains('bovag6maanden', $detail->vehicle->warranties);
    }

    public function test_parses_bovag_warranty_3_months_from_certainty_keys(): void
    {
        $json = $this->buildDetailJson(['certaintyKeys' => ['BovagWarranty3Months']]);
        $detail = $this->parser->parseDetail($json);

        $this->assertSame('DrieMaanden', $detail->vehicle->bovagWarranty);
        $this->assertContains('bovag3maanden', $detail->vehicle->warranties);
    }

    public function test_parses_no_bovag_warranty_when_absent(): void
    {
        $json = $this->buildDetailJson(['certaintyKeys' => ['ReturnWarranty']]);
        $detail = $this->parser->parseDetail($json);

        $this->assertNull($detail->vehicle->bovagWarranty);
        $this->assertNotContains('bovag12maanden', $detail->vehicle->warranties);
    }

    // --- Dutch Price Parsing ---

    public function test_parses_detail_with_integer_price(): void
    {
        $json = $this->buildDetailJsonWithPrice(15000);
        $detail = $this->parser->parseDetail($json);

        $this->assertSame(15000, $detail->price);
    }

    public function test_parses_dutch_price_with_thousands_separator(): void
    {
        // "€ 19.850,-" → 19850
        $json = $this->buildDetailJsonWithPrice(['formattedValue' => '€ 19.850,-', 'hasValue' => true]);
        $detail = $this->parser->parseDetail($json);

        $this->assertSame(19850, $detail->price);
    }

    public function test_parses_dutch_price_with_cents(): void
    {
        // "€ 19.850,50" → 19851 (rounded)
        $json = $this->buildDetailJsonWithPrice([
            'formattedValue' => '€ 19.850,50',
            'formattedValueWithoutUnit' => '19.850,50',
            'hasValue' => true,
        ]);
        $detail = $this->parser->parseDetail($json);

        $this->assertSame(19851, $detail->price);
    }

    public function test_parses_dutch_price_zero(): void
    {
        $json = $this->buildDetailJsonWithPrice(['formattedValue' => '€ 0,-', 'hasValue' => true]);
        $detail = $this->parser->parseDetail($json);

        $this->assertSame(0, $detail->price);
    }

    public function test_parses_dutch_price_simple_number(): void
    {
        // "5.000,-" → 5000
        $json = $this->buildDetailJsonWithPrice([
            'formattedValue' => '5.000,-',
            'formattedValueWithoutUnit' => '5.000,-',
            'hasValue' => true,
        ]);
        $detail = $this->parser->parseDetail($json);

        $this->assertSame(5000, $detail->price);
    }

    public function test_parses_dutch_price_without_thousands(): void
    {
        // "€ 950,-" → 950
        $json = $this->buildDetailJsonWithPrice(['formattedValue' => '€ 950,-', 'hasValue' => true]);
        $detail = $this->parser->parseDetail($json);

        $this->assertSame(950, $detail->price);
    }

    public function test_parses_road_tax_price(): void
    {
        // The fixture has roadTax "€ 13,-" → 13
        $json = $this->fixture('listing-detail.json');
        $detail = $this->parser->parseDetail($json);

        $this->assertSame(13, $detail->roadTax);
    }

    // --- Accessory Parsing ---

    public function test_parses_accessories_as_string_items(): void
    {
        $json = $this->buildDetailJson([
            'accessories' => ['ABS', 'Traction Control', 'LED Lighting'],
        ]);
        $detail = $this->parser->parseDetail($json);

        $this->assertCount(3, $detail->accessories);
        $this->assertSame('ABS', $detail->accessories[0]->name);
        $this->assertSame('Traction Control', $detail->accessories[1]->name);
        $this->assertSame('LED Lighting', $detail->accessories[2]->name);
    }

    public function test_parses_accessories_as_array_items(): void
    {
        $json = $this->buildDetailJson([
            'accessories' => [
                ['name' => 'ABS'],
                ['name' => 'Cruise Control'],
            ],
        ]);
        $detail = $this->parser->parseDetail($json);

        $this->assertCount(2, $detail->accessories);
        $this->assertSame('ABS', $detail->accessories[0]->name);
        $this->assertSame('Cruise Control', $detail->accessories[1]->name);
    }

    public function test_parses_accessories_array_item_without_name_key(): void
    {
        $json = $this->buildDetailJson([
            'accessories' => [
                ['name' => 'ABS'],
                ['other_key' => 'value'],
            ],
        ]);
        $detail = $this->parser->parseDetail($json);

        $this->assertCount(2, $detail->accessories);
        $this->assertSame('ABS', $detail->accessories[0]->name);
        $this->assertSame('', $detail->accessories[1]->name);
    }

    public function test_parses_empty_accessories(): void
    {
        $json = $this->buildDetailJson([
            'accessories' => [],
        ]);
        $detail = $this->parser->parseDetail($json);

        $this->assertCount(0, $detail->accessories);
    }

    // --- Exception Code ---

    public function test_exception_contains_status_code_context(): void
    {
        // Verify that our custom exceptions can carry a status code
        $exception = new ViaBOVAGException('HTTP 429: Too Many Requests', 429);
        $this->assertSame(429, $exception->getCode());
        $this->assertSame('HTTP 429: Too Many Requests', $exception->getMessage());
    }

    // --- Helper ---

    /**
     * Build a minimal detail JSON with a specific price value.
     *
     * @param  array<string, mixed>|int  $price
     */
    private function buildDetailJsonWithPrice(array|int $price): string
    {
        return json_encode([
            'pageProps' => [
                'vehicle' => [
                    'id' => 'test',
                    'advertisement' => [
                        'title' => 'Test Vehicle',
                        'price' => $price,
                        'media' => [],
                        'company' => ['name' => 'Test Dealer'],
                    ],
                    'vehicle' => [
                        'general' => [
                            'brand' => ['formattedValue' => 'Test', 'hasValue' => true],
                            'model' => ['formattedValue' => 'Model', 'hasValue' => true],
                        ],
                        'history' => [
                            'mileage' => ['formattedValue' => '0 km', 'hasValue' => true],
                            'productionYear' => ['formattedValue' => '2020', 'hasValue' => true],
                        ],
                        'certaintyKeys' => [],
                    ],
                ],
            ],
        ]);
    }

    private function buildDetailJsonWithDescription(?string $description): string
    {
        return json_encode([
            'pageProps' => [
                'vehicle' => [
                    'id' => 'test',
                    'advertisement' => [
                        'title' => 'Test Vehicle',
                        'price' => ['formattedValue' => '€ 10.000,-', 'hasValue' => true],
                        'comments' => [
                            'formattedValue' => $description,
                            'hasValue' => $description !== null,
                        ],
                        'media' => [],
                        'company' => ['name' => 'Test Dealer'],
                    ],
                    'vehicle' => [
                        'general' => [
                            'brand' => ['formattedValue' => 'Test', 'hasValue' => true],
                            'model' => ['formattedValue' => 'Model', 'hasValue' => true],
                        ],
                        'history' => [
                            'mileage' => ['formattedValue' => '0 km', 'hasValue' => true],
                            'productionYear' => ['formattedValue' => '2020', 'hasValue' => true],
                        ],
                        'certaintyKeys' => [],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Build a minimal detail JSON string with overrides for vehicle specs.
     *
     * @param  array<string, mixed>  $vehicleOverrides
     */
    private function buildDetailJson(array $vehicleOverrides = []): string
    {
        $vehicleSpecs = array_merge([
            'general' => [
                'vehicleType' => ['value' => 'motor', 'formattedValue' => 'Motor', 'hasValue' => true],
                'brand' => ['value' => 'Test', 'formattedValue' => 'Test', 'hasValue' => true],
                'model' => ['value' => 'Model', 'formattedValue' => 'Model', 'hasValue' => true],
            ],
            'history' => [
                'mileage' => ['value' => '10000', 'formattedValue' => '10.000 km', 'hasValue' => true],
                'productionYear' => ['value' => '2020', 'formattedValue' => '2020', 'hasValue' => true],
            ],
            'certaintyKeys' => [],
        ], $vehicleOverrides);

        return json_encode([
            'pageProps' => [
                'vehicle' => [
                    'id' => 'test-id',
                    'mobilityType' => 'motor',
                    'url' => '/test',
                    'advertisement' => [
                        'title' => 'Test Vehicle',
                        'price' => ['formattedValue' => '€ 10.000,-', 'hasValue' => true],
                        'media' => [],
                        'company' => ['name' => 'Test Dealer'],
                    ],
                    'vehicle' => $vehicleSpecs,
                ],
            ],
        ]);
    }
}
