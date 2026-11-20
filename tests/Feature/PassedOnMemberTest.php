<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Models\Member;
use App\Mail\PassedOnMail;
use App\Models\Club;
use App\Models\Lodge;
use App\Models\MasonicHall;
use App\Models\PassedOnDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PassedOnMemberTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private Lodge $sister;

    private User $secretary;

    private User $tom;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Notification::fake();
        Storage::fake('local');
        $hall = MasonicHall::factory()->create();
        $this->club = Club::factory()->create();
        Lodge::factory()->create(['club_id' => $this->club->id, 'masonic_hall_id' => $hall->id]);
        $this->sister = Lodge::factory()->create(['masonic_hall_id' => $hall->id]);
        $this->secretary = User::factory()->create();
        $this->club->users()->attach($this->secretary->id, ['role' => 'secretary', 'status' => 'active']);
        $this->tom = User::factory()->create();
        $this->club->users()->attach($this->tom->id, ['role' => 'member', 'status' => 'active']);
    }

    /** @param  array<string, mixed>  $overrides */
    private function passOn(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->secretary)->post(route('member.pass_on.store', $this->club->slug), $overrides + [
            'kind' => 'sister', 'type' => 'summons', 'lodge_slug' => $this->sister->slug, 'title' => 'Regular meeting',
            'body' => '<p>All welcome.</p>', 'meeting_date' => now()->addDays(10)->toDateString(),
            'attachment' => UploadedFile::fake()->createWithContent('summons.pdf', '%PDF-1.4 summons '.uniqid()),
        ]);
    }

    private function copyFor(User|Member $who): PassedOnDelivery
    {
        return PassedOnDelivery::where($who instanceof User ? 'user_id' : 'member_id', $who->id)->latest('id')->firstOrFail();
    }

    public function test_a_member_opens_what_was_passed_on_and_downloads_the_pdf(): void
    {
        $this->passOn()->assertSessionHasNoErrors();
        $copy = $this->copyFor($this->tom);

        $this->actingAs($this->tom)->get(route('members.passed_on.show', $copy->id))
            ->assertInertia(fn ($page) => $page->component('Members/PassedOn/Show')
                ->where('item.title', 'Regular meeting')
                ->where('item.passed_on_by', 'Passed on by '.$this->secretary->name.' of '.$this->club->name)
                ->where('item.lodge.muted', false));
        $this->assertNotNull($copy->fresh()->read_at);

        $this->actingAs($this->tom)->get(route('members.passed_on.attachment', $copy->id))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('passed_on.public', $copy->token))->assertOk()->assertSee('Regular meeting');
        $this->get(route('passed_on.public.attachment', $copy->token))->assertOk();
    }

    public function test_someone_without_a_delivery_cannot_open_it_or_its_pdf(): void
    {
        $this->passOn()->assertSessionHasNoErrors();
        $copy = $this->copyFor($this->tom);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('members.passed_on.show', $copy->id))->assertNotFound();
        $this->actingAs($stranger)->get(route('members.passed_on.attachment', $copy->id))->assertNotFound();
        $this->actingAs($stranger)->get(route('member.pass_on.attachment', [$this->club->slug, $copy->item_id]))->assertForbidden();
        $this->get(route('passed_on.public', str_repeat('a', 64)))->assertNotFound();
        $this->get(route('passed_on.public.attachment', 'short'))->assertNotFound();
    }

    public function test_muting_a_lodge_from_the_item_stops_the_next_one(): void
    {
        $this->passOn()->assertSessionHasNoErrors();
        $this->actingAs($this->tom)->post(route('members.passed_on.mute', $this->copyFor($this->tom)->id))->assertSessionHasNoErrors();

        $this->passOn(['meeting_date' => now()->addDays(40)->toDateString()])->assertSessionHasNoErrors();

        $this->assertSame(1, PassedOnDelivery::where('user_id', $this->tom->id)->count());
    }

    public function test_a_member_changes_their_choices(): void
    {
        $this->actingAs($this->tom)->post(route('members.passed_on.choices.update'), ['kinds' => ['sister' => [], 'grand' => ['email']], 'calendar' => false])->assertSessionHasNoErrors();

        $prefs = $this->tom->fresh()->passed_on_prefs;
        $this->assertSame([], $prefs['kinds']['sister']);
        $this->assertSame(['email'], $prefs['kinds']['grand']);
        $this->assertFalse($prefs['calendar']);

        $this->passOn()->assertSessionHasNoErrors();
        $this->assertFalse(PassedOnDelivery::where('user_id', $this->tom->id)->exists());

        $this->actingAs($this->tom)->get(route('members.passed_on.choices'))
            ->assertInertia(fn ($page) => $page->component('Members/PassedOnChoices')->where('calendar', false)->where('kinds.3.channels', ['email']));
    }

    public function test_one_click_unsubscribe_turns_off_email_for_that_kind_only(): void
    {
        $ron = Member::create(['club_id' => $this->club->id, 'first_name' => 'Ron', 'last_name' => 'Hill', 'email' => 'ron@example.test', 'membership_status' => 'active']);
        $this->passOn(['kind' => 'provincial', 'title' => 'Provincial news'])->assertSessionHasNoErrors();

        $this->post(route('passed_on.unsubscribe', $this->copyFor($ron)->token))->assertOk()->assertSee('by email any more');

        Mail::fake();
        $this->passOn(['kind' => 'provincial', 'title' => 'More Provincial news'])->assertSessionHasNoErrors();
        $this->passOn(['kind' => 'grand', 'title' => 'Grand Lodge news'])->assertSessionHasNoErrors();
        Mail::assertQueued(PassedOnMail::class, fn (PassedOnMail $mail) => $mail->hasTo('ron@example.test') && $mail->delivery->kind === 'grand');
        Mail::assertNotQueued(PassedOnMail::class, fn (PassedOnMail $mail) => $mail->hasTo('ron@example.test') && $mail->delivery->kind === 'provincial');
    }

    public function test_an_unsubscribe_link_opened_in_a_browser_shows_the_choices(): void
    {
        $this->passOn()->assertSessionHasNoErrors();

        $this->get(route('passed_on.unsubscribe', $this->copyFor($this->tom)->token))->assertOk()->assertSee('What you get by email');
    }

    public function test_choices_made_from_the_email_page_are_kept_for_that_person(): void
    {
        $this->passOn()->assertSessionHasNoErrors();
        $copy = $this->copyFor($this->tom);

        $this->post(route('passed_on.choices', $copy->token), ['email' => ['grand'], 'mute' => '1'])->assertOk()->assertSee('Your choices are saved');

        $prefs = $this->tom->fresh()->passed_on_prefs;
        $this->assertSame(['app'], $prefs['kinds']['sister']);
        $this->assertSame(['app', 'email'], $prefs['kinds']['grand']);
        $this->assertSame([$this->sister->id], $prefs['muted_lodges']);
    }

    public function test_the_lodges_sender_can_download_the_pdf_they_passed_on(): void
    {
        $this->passOn()->assertSessionHasNoErrors();

        $this->actingAs($this->secretary)->get(route('member.pass_on.attachment', [$this->club->slug, $this->copyFor($this->tom)->item_id]))->assertOk();
    }
}
