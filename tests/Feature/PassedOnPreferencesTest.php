<?php

namespace Tests\Feature;

use App\Support\PassedOnPreferences;
use Tests\TestCase;

class PassedOnPreferencesTest extends TestCase
{
    public function test_a_member_who_has_chosen_nothing_gets_every_kind_by_app_and_email_with_the_calendar_on(): void
    {
        $prefs = PassedOnPreferences::fromArray(null);

        foreach (PassedOnPreferences::KINDS as $kind) {
            $this->assertSame(['app', 'email'], $prefs->channels($kind));
        }
        $this->assertTrue($prefs->calendar());
        $this->assertFalse($prefs->mutes(5));
    }

    public function test_a_member_without_an_account_only_ever_gets_email(): void
    {
        $prefs = PassedOnPreferences::fromArray(['kinds' => ['grand' => ['app', 'email']]], hasAccount: false);

        $this->assertSame(['email'], $prefs->channels('grand'));
        $this->assertSame(['email'], $prefs->channels('sister'));
    }

    public function test_choices_round_trip_and_unknown_kinds_or_channels_are_dropped(): void
    {
        $prefs = PassedOnPreferences::fromArray(['kinds' => ['sister' => ['email', 'sms'], 'bogus' => ['app']], 'muted_lodges' => [7], 'calendar' => false]);

        $this->assertSame(['email'], $prefs->channels('sister'));
        $this->assertTrue($prefs->mutes(7));
        $this->assertFalse($prefs->calendar());
        $this->assertSame($prefs->toArray(), PassedOnPreferences::fromArray($prefs->toArray())->toArray());
        $this->assertArrayNotHasKey('bogus', $prefs->toArray()['kinds']);
    }

    public function test_muting_and_changing_channels_give_new_choices(): void
    {
        $prefs = PassedOnPreferences::fromArray(null)->withMuted(3)->withChannels('grand', []);

        $this->assertTrue($prefs->mutes(3));
        $this->assertSame([], $prefs->channels('grand'));
        $this->assertSame(['app', 'email'], $prefs->channels('provincial'));
    }
}
