<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Testing;

use NiekNijland\ViaBOVAG\Data\Listing;
use NiekNijland\ViaBOVAG\Data\ListingDetail;
use NiekNijland\ViaBOVAG\Data\MobilityType;
use NiekNijland\ViaBOVAG\Data\SearchQuery;
use NiekNijland\ViaBOVAG\Data\SearchResult;
use NiekNijland\ViaBOVAG\ViaBOVAGInterface;
use PHPUnit\Framework\Assert;
use Throwable;

class FakeViaBOVAG implements ViaBOVAGInterface
{
    private ?SearchResult $searchResult = null;

    private ?ListingDetail $listingDetail = null;

    private ?Throwable $exception = null;

    /** @var RecordedCall[] */
    private array $calls = [];

    private bool $sessionReset = false;

    public function withSearchResult(SearchResult $result): self
    {
        $this->searchResult = $result;

        return $this;
    }

    public function withListingDetail(ListingDetail $detail): self
    {
        $this->listingDetail = $detail;

        return $this;
    }

    public function shouldThrow(Throwable $exception): self
    {
        $this->exception = $exception;

        return $this;
    }

    public function search(SearchQuery $query): SearchResult
    {
        $this->calls[] = new RecordedCall('search', [$query]);
        $this->throwIfConfigured();

        return $this->searchResult ?? SearchResultFactory::make();
    }

    public function getDetail(Listing $listing): ListingDetail
    {
        $this->calls[] = new RecordedCall('getDetail', [$listing]);
        $this->throwIfConfigured();

        return $this->listingDetail ?? ListingDetailFactory::make();
    }

    public function getDetailBySlug(string $slug, MobilityType $mobilityType = MobilityType::Motor): ListingDetail
    {
        $this->calls[] = new RecordedCall('getDetailBySlug', [$slug, $mobilityType]);
        $this->throwIfConfigured();

        return $this->listingDetail ?? ListingDetailFactory::make();
    }

    public function resetSession(): void
    {
        $this->calls[] = new RecordedCall('resetSession', []);
        $this->sessionReset = true;
    }

    /**
     * @return RecordedCall[]
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    /**
     * @return RecordedCall[]
     */
    public function getCallsTo(string $method): array
    {
        return array_values(array_filter(
            $this->calls,
            fn (RecordedCall $call): bool => $call->method === $method,
        ));
    }

    public function assertCalled(string $method, ?int $times = null): void
    {
        $calls = $this->getCallsTo($method);

        if ($times !== null) {
            Assert::assertCount($times, $calls, sprintf('Expected %s to be called %d times, got ', $method, $times).count($calls));
        } else {
            Assert::assertNotEmpty($calls, sprintf('Expected %s to be called at least once.', $method));
        }
    }

    public function assertNotCalled(string $method): void
    {
        Assert::assertEmpty(
            $this->getCallsTo($method),
            sprintf('Expected %s not to be called.', $method),
        );
    }

    public function assertSessionReset(): void
    {
        Assert::assertTrue($this->sessionReset, 'Expected resetSession() to be called.');
    }

    public function assertSessionNotReset(): void
    {
        Assert::assertFalse($this->sessionReset, 'Expected resetSession() not to be called.');
    }

    /**
     * @throws Throwable
     */
    private function throwIfConfigured(): void
    {
        if ($this->exception instanceof Throwable) {
            $e = $this->exception;
            $this->exception = null;

            throw $e;
        }
    }
}
