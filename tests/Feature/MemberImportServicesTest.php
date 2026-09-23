<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Models\MemberImport;
use App\Domains\ClubAccounting\Models\MemberImportRow;
use App\Domains\ClubAccounting\Services\MemberImport\MemberImportAnalyzer;
use App\Domains\ClubAccounting\Services\MemberImport\MemberImportFields;
use App\Domains\ClubAccounting\Services\MemberImport\MemberImportRunner;
use App\Domains\ClubAccounting\Services\MemberImport\MemberImportUndo;
use App\Domains\ClubAccounting\Services\MemberImport\MemberImportValues;
use App\Mail\MemberInvitationMail;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use App\Support\CsvReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class MemberImportServicesTest extends TestCase
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

    private function stage(string $csv, ?array $mapping = null, array $options = []): MemberImport
    {
        $path = 'member-imports/'.$this->club->id.'/'.Str::uuid().'.csv';
        Storage::disk('local')->put($path, $csv);
        $document = CsvReader::read(Storage::disk('local')->path($path));

        return MemberImport::create([
            'club_id' => $this->club->id,
            'user_id' => $this->admin->id,
            'filename' => 'members.csv',
            'file_hash' => hash('sha256', $csv),
            'stored_path' => $path,
            'headers' => $document['headers'],
            'mapping' => $mapping ?? MemberImportFields::autoMap($document['headers']),
            'options' => $options + ['date_order' => 'dmy'],
        ]);
    }

    private function analysed(string $csv, ?array $mapping = null, array $options = []): MemberImport
    {
        return app(MemberImportAnalyzer::class)->analyse($this->stage($csv, $mapping, $options));
    }

    private function row(MemberImport $import, int $number): MemberImportRow
    {
        return $import->rows()->where('row_number', $number)->firstOrFail();
    }

    // ---- reader ----

    public function test_reader_handles_bom_semicolons_windows_1252_and_quoted_line_breaks(): void
    {
        $bom = "\xEF\xBB\xBFFirst Name;Last Name;Notes\nJoão;Smith;\"line one\nline two\"\n\n";
        Storage::disk('local')->put('a.csv', $bom);
        $doc = CsvReader::read(Storage::disk('local')->path('a.csv'));

        $this->assertSame(';', $doc['delimiter']);
        $this->assertSame(['First Name', 'Last Name', 'Notes'], $doc['headers']);
        $this->assertSame([['João', 'Smith', "line one\nline two"]], $doc['rows']);

        Storage::disk('local')->put('b.csv', mb_convert_encoding("Name\tCity\nRené\tZürich\n", 'Windows-1252', 'UTF-8'));
        $doc = CsvReader::read(Storage::disk('local')->path('b.csv'));
        $this->assertSame("\t", $doc['delimiter']);
        $this->assertSame([['René', 'Zürich']], $doc['rows']);
    }

    public function test_reader_pads_short_rows_names_blank_headers_and_refuses_bad_files(): void
    {
        Storage::disk('local')->put('c.csv', "A,,C\n1,2\n");
        $doc = CsvReader::read(Storage::disk('local')->path('c.csv'));
        $this->assertSame(['A', 'Column 2', 'C'], $doc['headers']);
        $this->assertSame([['1', '2', '']], $doc['rows']);

        Storage::disk('local')->put('d.csv', "H\n1\n2\n3\n");
        $this->expectException(InvalidArgumentException::class);
        CsvReader::read(Storage::disk('local')->path('d.csv'), 2);
    }

    public function test_reader_refuses_an_empty_file(): void
    {
        Storage::disk('local')->put('e.csv', "  \n");
        $this->expectException(InvalidArgumentException::class);
        CsvReader::read(Storage::disk('local')->path('e.csv'));
    }

    // ---- fields and values ----

    public function test_columns_are_matched_by_their_header_names(): void
    {
        $map = MemberImportFields::autoMap(['Surname', 'Forename', 'E-mail', 'Hermes/GL ID', 'Mobile', 'Joined', 'Mystery']);

        $this->assertSame(['last_name', 'first_name', 'email', 'grand_lodge_number', 'phone', 'date_of_joining', null], $map);
    }

    public function test_a_field_is_given_to_only_one_column_and_split_names_win_over_full_name(): void
    {
        $this->assertSame(['email', null], MemberImportFields::autoMap(['Email', 'Email Address']));
        $this->assertSame(['first_name', 'last_name', null], MemberImportFields::autoMap(['First Name', 'Last Name', 'Name']));
        $this->assertSame([MemberImportFields::FULL_NAME], MemberImportFields::autoMap(['Name']));
    }

    public function test_the_template_columns_are_the_export_columns(): void
    {
        $this->assertSame(
            ['Hermes/GL ID', 'First Name', 'Last Name', 'Masonic Rank', 'Grand Rank', 'Provincial Rank', 'Current Office', 'Status', 'Email', 'Phone', 'Address Line 1', 'Address Line 2', 'City', 'Postcode', 'Date of Joining', 'Date of Initiation'],
            array_keys(MemberImportFields::EXPORT_COLUMNS),
        );
        $this->assertSame(array_values(MemberImportFields::EXPORT_COLUMNS), MemberImportFields::autoMap(array_keys(MemberImportFields::EXPORT_COLUMNS)));
    }

    public function test_dates_are_read_in_the_chosen_order(): void
    {
        $this->assertSame('2020-03-04', MemberImportValues::date('04/03/2020', 'dmy'));
        $this->assertSame('2020-04-03', MemberImportValues::date('04/03/2020', 'mdy'));
        $this->assertSame('2020-03-04', MemberImportValues::date('2020-03-04'));
        $this->assertSame('2020-03-04', MemberImportValues::date('4 Mar 2020'));
        $this->assertSame('2021-01-01', MemberImportValues::date('44197'));
        $this->assertNull(MemberImportValues::date('31/02/2020'));
        $this->assertNull(MemberImportValues::date('nonsense'));
        $this->assertSame('mdy', MemberImportValues::detectDateOrder(['03/25/2020', '01/02/2020']));
        $this->assertSame('dmy', MemberImportValues::detectDateOrder(['25/03/2020']));
        $this->assertSame('dmy', MemberImportValues::detectDateOrder(['01/02/2020']));
    }

    public function test_offices_statuses_and_names_are_understood(): void
    {
        $this->assertSame(LodgeOffice::WorshipfulMaster, MemberImportValues::office('W.M.'));
        $this->assertSame(LodgeOffice::WorshipfulMaster, MemberImportValues::office('Worshipful Master'));
        $this->assertSame(LodgeOffice::Member, MemberImportValues::office('Member / Brethren'));
        $this->assertSame(LodgeOffice::IPM, MemberImportValues::office('immediate past master'));
        $this->assertNull(MemberImportValues::office('Grand Poobah'));
        $this->assertSame(MembershipStatus::Active, MemberImportValues::status('Active Member'));
        $this->assertSame(MembershipStatus::ExcludedRule181, MemberImportValues::status('Excluded (Rule 181)'));
        $this->assertSame(MembershipStatus::Resigned, MemberImportValues::status('resigned'));
        $this->assertNull(MemberImportValues::status('mystery'));
        $this->assertSame(['masonic_rank' => 'WBro', 'first_name' => 'John', 'middle_names' => 'Arthur', 'last_name' => 'Smith'], MemberImportValues::splitName('WBro John Arthur Smith'));
        $this->assertSame(['first_name' => 'John', 'middle_names' => null, 'last_name' => 'Smith'], MemberImportValues::splitName('Dr John Smith'));
        $this->assertSame('WBro', MemberImportValues::masonicRank('W.Bro'));
        $this->assertSame('VWBro', MemberImportValues::masonicRank('Very Worshipful'));
        $this->assertNull(MemberImportValues::masonicRank('Mr'));
        $this->assertSame('=SUM(A1)', MemberImportValues::clean("'=SUM(A1)"));
        $this->assertSame("'quoted", MemberImportValues::clean("'quoted"));
    }

    // ---- analysis ----

    public function test_new_rows_and_errors_are_told_apart(): void
    {
        $import = $this->analysed("First Name,Last Name,Email,Date of Joining,Status,Current Office\nAnn,Baker,ann@example.com,01/02/2020,Active,Secretary\n,NoFirst,x@example.com,,,\nBob,Cook,not-an-email,,,\nCy,Dale,cy@example.com,31/02/2020,,\nDee,Eve,dee@example.com,,Mystery,\nFay,Gray,fay@example.com,,,Grand Poobah\n");

        $this->assertSame(6, $import->total_rows);
        $this->assertSame(2, $import->new_count);
        $this->assertSame(4, $import->error_count);

        $ann = $this->row($import, 1);
        $this->assertSame(MemberImportRow::NEW, $ann->status);
        $this->assertSame(['first_name' => 'Ann', 'last_name' => 'Baker', 'email' => 'ann@example.com', 'date_of_joining' => '2020-02-01', 'membership_status' => 'active', 'current_office' => 'secretary'], $ann->data);
        $this->assertSame(['Missing first name'], $this->row($import, 2)->errors);
        $this->assertStringContainsString('not a valid email', $this->row($import, 3)->errors[0]);
        $this->assertStringContainsString('not a date', $this->row($import, 4)->errors[0]);
        $this->assertStringContainsString('Status', $this->row($import, 5)->errors[0]);

        $fay = $this->row($import, 6);
        $this->assertSame(MemberImportRow::NEW, $fay->status);
        $this->assertArrayNotHasKey('current_office', $fay->data);
        $this->assertStringContainsString('not recognised', $fay->warnings[0]);
        $this->assertSame(0, Member::where('club_id', $this->club->id)->count(), 'Analysing must not write to the roster.');
    }

    public function test_existing_members_are_found_by_email_then_id_then_name(): void
    {
        $byEmail = $this->member(['email' => 'match@example.com']);
        $byId = $this->member(['grand_lodge_number' => 'GL123', 'email' => 'other@example.com']);
        $byName = $this->member(['first_name' => 'Nora', 'last_name' => 'Fields', 'email' => 'nora@example.com']);

        $import = $this->analysed("First Name,Last Name,Email,Hermes/GL ID\nA,One,MATCH@example.com,\nB,Two,,gl123\nNora,Fields,different@example.com,\nC,Fresh,fresh@example.com,\n");

        $one = $this->row($import, 1);
        $this->assertSame([MemberImportRow::DUPLICATE, 'email', $byEmail->id, 'skip'], [$one->status, $one->match_type, $one->match_member_id, $one->action]);
        $two = $this->row($import, 2);
        $this->assertSame([MemberImportRow::DUPLICATE, 'grand_lodge_number', $byId->id], [$two->status, $two->match_type, $two->match_member_id]);
        $three = $this->row($import, 3);
        $this->assertSame([MemberImportRow::POSSIBLE, 'name', $byName->id, 'skip'], [$three->status, $three->match_type, $three->match_member_id, $three->action]);
        $this->assertSame([MemberImportRow::NEW, 'create'], [$this->row($import, 4)->status, $this->row($import, 4)->action]);
        $this->assertSame([2, 1, 1], [$import->duplicate_count, $import->possible_count, $import->new_count]);
    }

    public function test_ambiguous_and_conflicting_matches_never_pick_a_member(): void
    {
        $this->member(['email' => 'shared@example.com']);
        $this->member(['email' => 'shared@example.com']);
        $a = $this->member(['email' => 'a@example.com']);
        $b = $this->member(['email' => 'b@example.com', 'grand_lodge_number' => 'GL9']);

        $import = $this->analysed("First Name,Last Name,Email,Hermes/GL ID\nX,One,shared@example.com,\nY,Two,a@example.com,GL9\n");

        $ambiguous = $this->row($import, 1);
        $this->assertSame([MemberImportRow::POSSIBLE, 'email_ambiguous', null], [$ambiguous->status, $ambiguous->match_type, $ambiguous->match_member_id]);
        $conflict = $this->row($import, 2);
        $this->assertSame([MemberImportRow::POSSIBLE, 'conflict', null], [$conflict->status, $conflict->match_type, $conflict->match_member_id]);
    }

    public function test_rows_that_repeat_each_other_in_the_file_are_linked(): void
    {
        $import = $this->analysed("First Name,Last Name,Email\nAnn,Baker,ann@example.com\nAnn,Baker,ann@example.com\nAnna,Baker,ann@example.com\nAnn,Baker,other@example.com\n");

        $this->assertSame(MemberImportRow::NEW, $this->row($import, 1)->status);
        $second = $this->row($import, 2);
        $this->assertSame([MemberImportRow::DUPLICATE, 'file', 1, 'create'], [$second->status, $second->match_type, $second->duplicate_of_row, $second->action]);
        $this->assertSame(1, $this->row($import, 3)->duplicate_of_row);
        $weak = $this->row($import, 4);
        $this->assertSame([MemberImportRow::POSSIBLE, 'file', 1, 'skip'], [$weak->status, $weak->match_type, $weak->duplicate_of_row, $weak->action]);
    }

    public function test_a_mapping_needs_names_and_no_column_used_twice(): void
    {
        $this->assertNotNull(MemberImportAnalyzer::mappingProblem(['email', 'phone']));
        $this->assertNotNull(MemberImportAnalyzer::mappingProblem(['first_name', 'last_name', 'email', 'email']));
        $this->assertNotNull(MemberImportAnalyzer::mappingProblem(['full_name', 'last_name']));
        $this->assertNull(MemberImportAnalyzer::mappingProblem(['first_name', 'last_name', null]));
        $this->assertNull(MemberImportAnalyzer::mappingProblem(['full_name', 'email']));
    }

    public function test_masonic_ranks_are_normalised_and_unknown_ones_are_left_out(): void
    {
        $import = $this->analysed("First Name,Last Name,Masonic Rank\nAnn,Baker,W.Bro\nBob,Cook,Grand Poobah\n");

        $this->assertSame('WBro', $this->row($import, 1)->data['masonic_rank']);
        $bob = $this->row($import, 2);
        $this->assertArrayNotHasKey('masonic_rank', $bob->data);
        $this->assertStringContainsString('not recognised', $bob->warnings[0]);
    }

    public function test_a_single_name_column_is_split(): void
    {
        $import = $this->analysed("Name,Email\nBro John Arthur Smith,js@example.com\n");

        $this->assertSame(['masonic_rank' => 'Bro', 'first_name' => 'John', 'middle_names' => 'Arthur', 'last_name' => 'Smith', 'email' => 'js@example.com'], $this->row($import, 1)->data);
    }

    public function test_an_exported_roster_reads_back_as_all_duplicates(): void
    {
        $member = $this->member(['grand_lodge_number' => 'GL1', 'current_office' => LodgeOffice::Secretary, 'date_of_joining' => '2015-06-01', 'email' => 'me@example.com']);
        $header = implode(',', array_keys(MemberImportFields::EXPORT_COLUMNS));
        $row = implode(',', array_map(fn ($v) => '"'.$v.'"', MemberImportFields::exportRow($member->fresh())));

        $import = $this->analysed($header."\n".$row."\n");

        $this->assertSame(1, $import->duplicate_count);
        $this->assertSame([], $this->row($import, 1)->errors ?? []);
        $this->assertSame('secretary', $this->row($import, 1)->data['current_office']);
        $this->assertSame('2015-06-01', $this->row($import, 1)->data['date_of_joining']);
    }

    // ---- running ----

    public function test_running_creates_new_members_with_defaults_and_records_each_row(): void
    {
        $import = $this->analysed("First Name,Last Name,Email\nAnn,Baker,ann@example.com\n,Broken,\n");
        $result = app(MemberImportRunner::class)->run($import, $this->admin);

        $this->assertSame(['created' => 1, 'updated' => 0, 'skipped' => 0, 'errors' => 1], array_intersect_key($result, array_flip(['created', 'updated', 'skipped', 'errors'])));
        $member = Member::where('club_id', $this->club->id)->where('email', 'ann@example.com')->firstOrFail();
        $this->assertSame('Bro', $member->title);
        $this->assertSame(MembershipStatus::Active, $member->membership_status);
        $this->assertSame(LodgeOffice::Member, $member->current_office);
        $this->assertSame('created', $this->row($import, 1)->outcome);
        $this->assertSame($member->id, $this->row($import, 1)->created_member_id);
        $this->assertSame('failed', $this->row($import, 2)->outcome);
        $this->assertSame(MemberImport::IMPORTED, $import->fresh()->status);
        $this->assertNull($import->fresh()->stored_path);
    }

    public function test_an_import_runs_only_once(): void
    {
        $import = $this->analysed("First Name,Last Name\nAnn,Baker\n");
        app(MemberImportRunner::class)->run($import);

        $this->expectException(RuntimeException::class);
        app(MemberImportRunner::class)->run($import);
    }

    public function test_duplicates_are_skipped_unless_told_otherwise(): void
    {
        $existing = $this->member(['email' => 'dup@example.com', 'phone' => '111', 'city' => null]);
        $import = $this->analysed("First Name,Last Name,Email,Phone,City\nNew,Name,dup@example.com,222,Leeds\n");

        app(MemberImportRunner::class)->run($import);

        $this->assertSame('111', $existing->fresh()->phone);
        $this->assertSame('skipped', $this->row($import, 1)->outcome);
        $this->assertSame(1, Member::where('club_id', $this->club->id)->count());
    }

    public function test_fill_only_changes_blank_fields_and_overwrite_never_blanks_a_field(): void
    {
        $fill = $this->member(['email' => 'fill@example.com', 'phone' => '111', 'city' => null, 'county' => 'Kent']);
        $over = $this->member(['email' => 'over@example.com', 'phone' => '111', 'city' => 'Leeds']);
        $import = $this->analysed("First Name,Last Name,Email,Phone,City\nA,Fill,fill@example.com,222,York\nB,Over,over@example.com,222,\n");
        $this->row($import, 1)->update(['action' => 'fill']);
        $this->row($import, 2)->update(['action' => 'overwrite']);

        app(MemberImportRunner::class)->run($import);

        $this->assertSame(['111', 'York', 'Kent'], [$fill->fresh()->phone, $fill->fresh()->city, $fill->fresh()->county]);
        $this->assertSame(['222', 'Leeds'], [$over->fresh()->phone, $over->fresh()->city]);
        $this->assertSame('updated', $this->row($import, 2)->outcome);
    }

    public function test_import_as_new_creates_a_second_member_and_a_missing_match_is_skipped(): void
    {
        $this->member(['email' => 'twin@example.com']);
        $gone = $this->member(['email' => 'gone@example.com']);
        $import = $this->analysed("First Name,Last Name,Email\nT,Win,twin@example.com\nG,One,gone@example.com\n");
        $this->row($import, 1)->update(['action' => 'create']);
        $this->row($import, 2)->update(['action' => 'overwrite']);
        $gone->delete();

        app(MemberImportRunner::class)->run($import);

        $this->assertSame(2, Member::where('club_id', $this->club->id)->where('email', 'twin@example.com')->count());
        $this->assertSame('skipped', $this->row($import, 2)->outcome);
        $this->assertSame('The matching member no longer exists', $this->row($import, 2)->outcome_note);
    }

    public function test_file_duplicate_policies(): void
    {
        $csv = "First Name,Last Name,Email,City\nAnn,Baker,ann@example.com,First\nAnn,Baker,ann@example.com,Second\nAnn,Baker,ann@example.com,Third\n";

        $first = $this->analysed($csv, null, ['file_duplicates' => 'keep_first']);
        app(MemberImportRunner::class)->run($first);
        $this->assertSame(['First'], Member::where('club_id', $this->club->id)->pluck('city')->all());

        Member::query()->delete();
        $last = $this->analysed($csv, null, ['file_duplicates' => 'keep_last']);
        app(MemberImportRunner::class)->run($last);
        $this->assertSame(['Third'], Member::where('club_id', $this->club->id)->pluck('city')->all());

        Member::query()->delete();
        $both = $this->analysed($csv, null, ['file_duplicates' => 'keep_both']);
        app(MemberImportRunner::class)->run($both);
        $this->assertSame(3, Member::where('club_id', $this->club->id)->count());
    }

    public function test_invitations_go_only_to_new_members_with_an_email(): void
    {
        Mail::fake();
        $this->member(['email' => 'old@example.com']);
        $import = $this->analysed("First Name,Last Name,Email\nNew,Person,new@example.com\nNo,Email,\nOld,Person,old@example.com\n");
        $this->row($import, 3)->update(['action' => 'overwrite']);

        $result = app(MemberImportRunner::class)->run($import, $this->admin, invite: true);

        $this->assertSame(1, $result['invited']);
        Mail::assertQueued(MemberInvitationMail::class, 1);
        Mail::assertQueued(MemberInvitationMail::class, fn ($mail) => $mail->hasTo('new@example.com'));
    }

    // ---- undo ----

    public function test_undo_removes_created_members_and_restores_updated_fields(): void
    {
        $existing = $this->member(['email' => 'up@example.com', 'phone' => '111']);
        $import = $this->analysed("First Name,Last Name,Email,Phone\nAnn,Baker,ann@example.com,\nUp,Dated,up@example.com,222\n");
        $this->row($import, 2)->update(['action' => 'overwrite']);
        app(MemberImportRunner::class)->run($import);

        $summary = app(MemberImportUndo::class)->undo($import->fresh());

        $this->assertSame(1, $summary['removed']);
        $this->assertSame(1, $summary['restored']);
        $this->assertSame([], $summary['blocked']);
        $this->assertNull(Member::where('club_id', $this->club->id)->where('email', 'ann@example.com')->first());
        $this->assertSame('111', $existing->fresh()->phone);
        $this->assertSame(MemberImport::UNDONE, $import->fresh()->status);
    }

    public function test_undo_leaves_alone_anyone_who_has_moved_on(): void
    {
        $existing = $this->member(['email' => 'up@example.com', 'phone' => '111']);
        $import = $this->analysed("First Name,Last Name,Email,Phone\nAnn,Baker,ann@example.com,\nUp,Dated,up@example.com,222\n");
        $this->row($import, 2)->update(['action' => 'overwrite']);
        app(MemberImportRunner::class)->run($import);

        $created = Member::where('club_id', $this->club->id)->where('email', 'ann@example.com')->firstOrFail();
        $user = User::factory()->create();
        $this->club->users()->attach($user, ['role' => 'member', 'status' => 'active', 'invitation_accepted_at' => now()]);
        $created->update(['user_id' => $user->id]);
        $existing->fresh()->update(['phone' => '333']);

        $summary = app(MemberImportUndo::class)->undo($import->fresh());

        $this->assertSame(0, $summary['removed']);
        $this->assertCount(2, $summary['blocked']);
        $this->assertNotNull($created->fresh());
        $this->assertSame('333', $existing->fresh()->phone);
    }

    public function test_only_a_completed_import_can_be_undone(): void
    {
        $import = $this->analysed("First Name,Last Name\nAnn,Baker\n");

        $this->expectException(RuntimeException::class);
        app(MemberImportUndo::class)->undo($import);
    }

    // ---- housekeeping ----

    public function test_pruning_discards_stale_unfinished_imports_and_old_records(): void
    {
        $stale = $this->stage("First Name,Last Name\nAnn,Baker\n");
        $stale = app(MemberImportAnalyzer::class)->analyse($stale);
        $fresh = $this->stage("First Name,Last Name\nBob,Cook\n");
        $old = $this->stage("First Name,Last Name\nCy,Dale\n");
        $old->update(['status' => MemberImport::IMPORTED]);

        MemberImport::whereKey($stale->id)->update(['updated_at' => now()->subHours(30)]);
        MemberImport::whereKey($old->id)->update(['updated_at' => now()->subDays(100)]);
        $stalePath = $stale->stored_path;

        $this->artisan('app:prune-member-imports')->assertSuccessful();

        $this->assertSame(MemberImport::CANCELLED, $stale->fresh()->status);
        $this->assertSame(0, $stale->rows()->count());
        Storage::disk('local')->assertMissing($stalePath);
        $this->assertSame(MemberImport::STAGED, $fresh->fresh()->status);
        Storage::disk('local')->assertExists($fresh->stored_path);
        $this->assertNull(MemberImport::find($old->id));
    }
}
