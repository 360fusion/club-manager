<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Domains\ClubAccounting\Livewire\Members\MemberImportPage;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Models\MemberImport;
use App\Domains\ClubAccounting\Models\MemberImportMapping;
use App\Domains\ClubAccounting\Services\MemberImport\MemberImportFields;
use App\Mail\MemberInvitationMail;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use App\Support\Csv;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MemberImportPageTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'lodge', 'available_modules' => ['members']]);
        $this->club = Club::create(['name' => 'Lodge of Fraternity', 'slug' => 'fraternity', 'club_type_id' => $type->id, 'is_active' => true]);
        $this->admin = User::factory()->create();
        $this->admin->clubs()->attach($this->club, ['role' => 'admin', 'status' => 'active']);
    }

    private function page(?User $user = null)
    {
        return Livewire::actingAs($user ?? $this->admin)->test(MemberImportPage::class, ['clubSlug' => $this->club->slug]);
    }

    private function csv(string $content, string $name = 'members.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function member(array $overrides = []): Member
    {
        static $n = 0;
        $n++;

        return Member::create($overrides + [
            'club_id' => $this->club->id,
            'first_name' => 'Existing'.$n,
            'last_name' => 'Person',
            'email' => "existing{$n}@example.com",
            'masonic_rank' => 'Bro',
            'membership_status' => MembershipStatus::Active,
            'current_office' => LodgeOffice::Member,
        ]);
    }

    public function test_the_page_renders_for_an_admin_and_is_closed_to_everyone_else(): void
    {
        $this->actingAs($this->admin)->get(route('admin.club_acc.members.import', ['clubSlug' => $this->club->slug]))->assertOk()->assertSee('Import Members')->assertSee('Download template');

        $regular = User::factory()->create();
        $this->club->users()->attach($regular, ['role' => 'member', 'status' => 'active']);
        $this->actingAs($regular)->get(route('admin.club_acc.members.import', ['clubSlug' => $this->club->slug]))->assertForbidden();

        $other = Club::create(['name' => 'Other', 'slug' => 'other', 'club_type_id' => $this->club->club_type_id, 'is_active' => true]);
        $stranger = User::factory()->create();
        $other->users()->attach($stranger, ['role' => 'admin', 'status' => 'active']);
        $this->actingAs($stranger)->get(route('admin.club_acc.members.import', ['clubSlug' => $this->club->slug]))->assertForbidden();
    }

    public function test_a_plain_member_cannot_upload_through_the_component(): void
    {
        $regular = User::factory()->create();
        $this->club->users()->attach($regular, ['role' => 'member', 'status' => 'active']);

        $this->page($regular)->set('file', $this->csv("First Name,Last Name\nAnn,Baker\n"))->assertForbidden();

        $this->assertSame(0, MemberImport::count());
    }

    public function test_the_template_download_matches_the_export_columns(): void
    {
        $expected = "\xEF\xBB\xBF".Csv::line(array_keys(MemberImportFields::EXPORT_COLUMNS)).Csv::line(MemberImportFields::exampleRow());

        $this->page()->call('downloadTemplate')->assertFileDownloaded('members-import-template.csv', $expected);
        $this->assertStringStartsWith("\xEF\xBB\xBF\"Hermes/GL ID\",\"First Name\",\"Last Name\",\"Masonic Rank\"", $expected);
    }

    public function test_uploading_a_file_matches_its_columns_and_moves_to_the_matching_step(): void
    {
        $this->page()
            ->set('file', $this->csv("Surname,Forename,E-mail,Mystery\nBaker,Ann,ann@example.com,x\n"))
            ->assertSet('step', 'match')
            ->assertSet('mapping', [0 => 'last_name', 1 => 'first_name', 2 => 'email', 3 => '']);

        $import = MemberImport::firstOrFail();
        $this->assertSame($this->club->id, $import->club_id);
        $this->assertSame($this->admin->id, $import->user_id);
        $this->assertNotNull($import->stored_path);
        Storage::disk('local')->assertExists($import->stored_path);
    }

    public function test_a_bad_file_is_refused_with_a_reason(): void
    {
        $this->page()->set('file', UploadedFile::fake()->create('members.pdf', 10, 'application/pdf'))->assertHasErrors('file');
        $this->page()->set('file', $this->csv("   \n"))->assertHasErrors('file');

        $this->assertSame(0, MemberImport::count());
    }

    public function test_the_whole_flow_from_upload_to_undo(): void
    {
        $existing = $this->member(['email' => 'dup@example.com', 'phone' => '111']);
        $csv = "First Name,Last Name,Email,Phone\nAnn,Baker,ann@example.com,\nDup,Licate,dup@example.com,222\n,Broken,bad@example.com,\n";

        $component = $this->page()
            ->set('file', $this->csv($csv))
            ->call('reviewRows')
            ->assertSet('step', 'review')
            ->assertSee('Ann Baker')
            ->assertSee('Same email as '.$existing->full_name)
            ->assertSee('Missing first name');

        $import = MemberImport::firstOrFail();
        $this->assertSame([1, 1, 0, 1], [$import->new_count, $import->duplicate_count, $import->possible_count, $import->error_count]);

        $duplicateRow = $import->rows()->where('row_number', 2)->firstOrFail();
        $component->call('setRowAction', $duplicateRow->id, 'overwrite');
        $this->assertSame('overwrite', $duplicateRow->fresh()->action);

        $component->call('runImport')->assertSet('step', 'done')->assertSee('Import complete');

        $this->assertNotNull(Member::where('club_id', $this->club->id)->where('email', 'ann@example.com')->first());
        $this->assertSame('222', $existing->fresh()->phone);

        $component->call('undoImport', $import->id)->assertSee('Undone: 1 removed, 1 restored');
        $this->assertNull(Member::where('club_id', $this->club->id)->where('email', 'ann@example.com')->first());
        $this->assertSame('111', $existing->fresh()->phone);
    }

    public function test_a_row_can_only_be_given_a_choice_it_allows(): void
    {
        $this->member(['email' => 'dup@example.com']);
        $component = $this->page()->set('file', $this->csv("First Name,Last Name,Email\nAnn,Baker,ann@example.com\n,Broken,\n"))->call('reviewRows');
        $import = MemberImport::firstOrFail();
        $new = $import->rows()->where('row_number', 1)->firstOrFail();
        $error = $import->rows()->where('row_number', 2)->firstOrFail();

        $component->call('setRowAction', $new->id, 'overwrite')->call('setRowAction', $error->id, 'create');

        $this->assertSame('create', $new->fresh()->action);
        $this->assertSame('skip', $error->fresh()->action);
    }

    public function test_applying_a_choice_to_the_whole_duplicates_list(): void
    {
        $this->member(['email' => 'one@example.com']);
        $this->member(['email' => 'two@example.com']);
        $component = $this->page()->set('file', $this->csv("First Name,Last Name,Email\nA,One,one@example.com\nB,Two,two@example.com\nC,New,new@example.com\n"))->call('reviewRows');

        $component->set('tab', 'duplicate')->call('applyToTab', 'fill');

        $import = MemberImport::firstOrFail();
        $this->assertSame(['fill', 'fill', 'create'], $import->rows()->orderBy('row_number')->pluck('action')->all());
    }

    public function test_nothing_is_imported_when_every_row_is_skipped_or_wrong(): void
    {
        $this->member(['email' => 'dup@example.com']);
        $component = $this->page()->set('file', $this->csv("First Name,Last Name,Email\nDup,Licate,dup@example.com\n"))->call('reviewRows');

        $component->call('runImport')->assertSet('step', 'review')->assertSee('Nothing to import');

        $this->assertSame(MemberImport::STAGED, MemberImport::firstOrFail()->status);
    }

    public function test_unmatched_columns_and_a_missing_name_stop_the_review(): void
    {
        $this->page()
            ->set('file', $this->csv("Email,Phone\nann@example.com,1\n"))
            ->call('reviewRows')
            ->assertSet('step', 'match')
            ->assertSee('Match a column to First name and Last name');
    }

    public function test_choosing_a_different_matching_is_used_and_can_be_saved_for_next_time(): void
    {
        $csv = "Person,Contact\nAnn Baker,ann@example.com\n";
        $this->page()
            ->set('file', $this->csv($csv))
            ->set('mapping.0', 'full_name')
            ->set('mapping.1', 'email')
            ->set('mappingName', 'Hermes export')
            ->call('reviewRows')
            ->assertSet('step', 'review');

        $this->assertSame(1, MemberImportMapping::where('club_id', $this->club->id)->count());

        $again = $this->page()->call('startOver')->set('file', $this->csv($csv))->assertSet('mapping', [0 => 'full_name', 1 => 'email']);
        $again->assertSet('step', 'match');
    }

    public function test_saved_matchings_belong_to_their_own_club(): void
    {
        $other = Club::create(['name' => 'Other', 'slug' => 'other', 'club_type_id' => $this->club->club_type_id, 'is_active' => true]);
        $foreign = MemberImportMapping::create(['club_id' => $other->id, 'name' => 'Theirs', 'header_signature' => 'x', 'mapping' => ['person' => 'full_name']]);

        $this->expectException(ModelNotFoundException::class);
        $this->page()->set('file', $this->csv("Person\nAnn\n"))->call('loadSavedMapping', $foreign->id);
    }

    public function test_another_clubs_import_cannot_be_undone(): void
    {
        $other = Club::create(['name' => 'Other', 'slug' => 'other', 'club_type_id' => $this->club->club_type_id, 'is_active' => true]);
        $theirs = MemberImport::create(['club_id' => $other->id, 'filename' => 'x.csv', 'file_hash' => 'h', 'status' => MemberImport::IMPORTED]);

        $this->expectException(ModelNotFoundException::class);
        $this->page()->call('undoImport', $theirs->id);
    }

    public function test_the_error_rows_download_has_the_original_cells_and_the_problem(): void
    {
        $component = $this->page()->set('file', $this->csv("First Name,Last Name,Email\nAnn,Baker,not-an-email\nBob,Cook,bob@example.com\n"))->call('reviewRows');

        $component->call('downloadErrors')->assertFileDownloaded('members-import-rows-to-fix.csv', "\xEF\xBB\xBF\"First Name\",\"Last Name\",Email,Problem\nAnn,Baker,not-an-email,\"Email 'not-an-email' is not a valid email address\"\n");
    }

    public function test_invitations_can_be_sent_as_part_of_the_import(): void
    {
        Mail::fake();

        $this->page()
            ->set('file', $this->csv("First Name,Last Name,Email\nAnn,Baker,ann@example.com\n"))
            ->call('reviewRows')
            ->set('invite', true)
            ->call('runImport')
            ->assertSee('1 invitation emailed');

        Mail::assertQueued(MemberInvitationMail::class, fn ($mail) => $mail->hasTo('ann@example.com'));
    }

    public function test_reloading_the_page_resumes_an_unfinished_import(): void
    {
        $this->page()->set('file', $this->csv("First Name,Last Name\nAnn,Baker\n"));

        $this->page()->assertSet('step', 'match');
    }

    public function test_the_same_file_is_flagged_when_it_was_imported_before(): void
    {
        $csv = "First Name,Last Name,Email\nAnn,Baker,ann@example.com\n";
        $this->page()->set('file', $this->csv($csv))->call('reviewRows')->call('runImport');

        $this->page()->call('startOver')->set('file', $this->csv($csv))->assertSee('This exact file was already imported');
    }

    public function test_starting_over_removes_the_stored_file(): void
    {
        $component = $this->page()->set('file', $this->csv("First Name,Last Name\nAnn,Baker\n"));
        $path = MemberImport::firstOrFail()->stored_path;

        $component->call('startOver')->assertSet('step', 'upload');

        Storage::disk('local')->assertMissing($path);
        $this->assertSame(MemberImport::CANCELLED, MemberImport::firstOrFail()->status);
    }
}
