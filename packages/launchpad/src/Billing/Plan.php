<?php

namespace Launchpad\Billing;

/** One plan from config/launchpad.php: what it is called, what it costs per period, and what it lets a workspace do. */
final readonly class Plan
{
    public const MONTHLY = 'monthly';

    public const ANNUAL = 'annual';

    /**
     * @param  array<string, array{id: ?string, amount: int}>  $prices  by period; amount in the smallest currency unit
     * @param  list<string>  $features
     * @param  array<string, int>  $limits
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $description,
        public int $rank,
        public array $prices,
        public array $features,
        public array $limits,
        public bool $perSeat = false,
        public int $minSeats = 1,
        public int $trialDays = 0,
    ) {}

    public function isFree(): bool
    {
        return $this->prices === [];
    }

    public function priceId(string $period): ?string
    {
        $id = $this->prices[$period]['id'] ?? null;

        return filled($id) ? $id : null;
    }

    public function amount(string $period): ?int
    {
        return $this->prices[$period]['amount'] ?? null;
    }

    /** @return list<string> the periods this plan can be bought for (a price id is set) */
    public function periods(): array
    {
        return array_values(array_filter([self::MONTHLY, self::ANNUAL], fn ($period) => $this->priceId($period) !== null));
    }
}
