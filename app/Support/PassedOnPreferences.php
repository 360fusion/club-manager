<?php

namespace App\Support;

/**
 * What a member wants passed on to them from other lodges, Province and Grand Lodge: for each kind, by app and/or
 * email (or not at all), any lodge they have muted, and whether passed-on meetings go in their calendar. Someone who
 * has chosen nothing gets everything; someone with no account (a roster member) can only be emailed.
 */
final class PassedOnPreferences
{
    public const KINDS = ['sister', 'reciprocal', 'provincial', 'grand'];

    public const LABELS = [
        'sister' => 'Sister lodges in our hall',
        'reciprocal' => 'Reciprocal visit lodges',
        'provincial' => 'Provincial communications',
        'grand' => 'Grand Lodge communications',
    ];

    public const CHANNELS = ['app', 'email'];

    /**
     * @param  array<string, list<string>>  $kinds
     * @param  list<int>  $mutedLodges
     */
    private function __construct(private array $kinds, private array $mutedLodges, private bool $calendar, private bool $hasAccount) {}

    /**
     * @param  array<string, mixed>|null  $prefs  as stored in users.passed_on_prefs or club_acc_members.passed_on_prefs
     */
    public static function fromArray(?array $prefs, bool $hasAccount = true): self
    {
        $stored = is_array($prefs['kinds'] ?? null) ? $prefs['kinds'] : [];
        $kinds = [];
        foreach (self::KINDS as $kind) {
            $kinds[$kind] = is_array($stored[$kind] ?? null)
                ? array_values(array_intersect(self::CHANNELS, $stored[$kind]))
                : self::CHANNELS;
        }

        $muted = array_values(array_unique(array_map('intval', array_filter((array) ($prefs['muted_lodges'] ?? []), 'is_numeric'))));

        return new self($kinds, $muted, (bool) ($prefs['calendar'] ?? true), $hasAccount);
    }

    /**
     * @return list<string> some of 'app' and 'email'
     */
    public function channels(string $kind): array
    {
        $channels = $this->kinds[$kind] ?? [];

        return $this->hasAccount ? $channels : array_values(array_diff($channels, ['app']));
    }

    public function mutes(?int $lodgeId): bool
    {
        return $lodgeId !== null && in_array($lodgeId, $this->mutedLodges, true);
    }

    public function calendar(): bool
    {
        return $this->calendar;
    }

    /**
     * @return list<int>
     */
    public function mutedLodges(): array
    {
        return $this->mutedLodges;
    }

    public function withMuted(int $lodgeId, bool $muted = true): self
    {
        $lodges = array_values(array_diff($this->mutedLodges, [$lodgeId]));

        return new self($this->kinds, $muted ? [...$lodges, $lodgeId] : $lodges, $this->calendar, $this->hasAccount);
    }

    /**
     * @param  list<string>  $channels
     */
    public function withChannels(string $kind, array $channels): self
    {
        $kinds = $this->kinds;
        if (in_array($kind, self::KINDS, true)) {
            $kinds[$kind] = array_values(array_intersect(self::CHANNELS, $channels));
        }

        return new self($kinds, $this->mutedLodges, $this->calendar, $this->hasAccount);
    }

    public function withCalendar(bool $calendar): self
    {
        return new self($this->kinds, $this->mutedLodges, $calendar, $this->hasAccount);
    }

    /**
     * @return array{kinds: array<string, list<string>>, muted_lodges: list<int>, calendar: bool}
     */
    public function toArray(): array
    {
        return ['kinds' => $this->kinds, 'muted_lodges' => $this->mutedLodges, 'calendar' => $this->calendar];
    }
}
