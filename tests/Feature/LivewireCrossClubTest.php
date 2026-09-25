<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Livewire\Banking\BankAccountsIndex;
use App\Domains\ClubAccounting\Livewire\Banking\BankImportIndex;
use App\Domains\ClubAccounting\Livewire\Banking\BankReconciliationWorkspace;
use App\Domains\ClubAccounting\Livewire\Candidates\CandidateDetail;
use App\Domains\ClubAccounting\Livewire\Candidates\CandidatePipeline;
use App\Domains\ClubAccounting\Livewire\Charity\CharityDashboard;
use App\Domains\ClubAccounting\Livewire\Charity\CharityFestival;
use App\Domains\ClubAccounting\Livewire\Committee\AgendaPackPreviewModal;
use App\Domains\ClubAccounting\Livewire\Committee\LiveMinuteTaker;
use App\Domains\ClubAccounting\Livewire\Committee\MeetingIndex;
use App\Domains\ClubAccounting\Livewire\Committee\MeetingWorkspace;
use App\Domains\ClubAccounting\Livewire\Members\MemberFormPage;
use App\Domains\ClubAccounting\Livewire\Members\MemberImportPage;
use App\Domains\ClubAccounting\Livewire\Members\MemberIndex;
use App\Domains\ClubAccounting\Livewire\Members\MemberProfile;
use App\Domains\ClubAccounting\Livewire\Subscriptions\SubscriptionIndex;
use App\Domains\ClubAccounting\Models\MemberFestivalGiving;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Livewire;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Tests\Concerns\PlantsForeignRecords;
use Tests\TestCase;

/**
 * The accounting and committee screens are Livewire components, and Livewire lets the browser set every
 * public property and call every public method with any arguments. As an admin of a club that owns records
 * of its own, tamper with each component using ids that belong to two other clubs: nothing of theirs may
 * change and none of their content may be rendered.
 */
class LivewireCrossClubTest extends TestCase
{
    use PlantsForeignRecords;
    use RefreshDatabase;

    private const SKIPPED_METHODS = ['mount', 'render', 'boot', 'booted', 'rules', 'messages', 'validationAttributes', 'placeholder', 'paginationView', 'paginationSimpleView'];

    private Club $attackerClub;

    /**
     * Each component with how to mount it on the attacker's own records: [class, mount args, id table].
     *
     * @return list<array{0: class-string, 1: array<string, mixed>, 2: ?string, 3: ?string}>
     */
    private function components(): array
    {
        $slug = ['clubSlug' => $this->attackerClub->slug];

        return [
            [BankAccountsIndex::class, $slug, null, null],
            [BankImportIndex::class, $slug, null, null],
            [BankReconciliationWorkspace::class, $slug, null, null],
            [CandidatePipeline::class, $slug, null, null],
            [CandidateDetail::class, $slug, 'candidateId', 'club_acc_candidates'],
            [CharityDashboard::class, $slug, null, null],
            [CharityFestival::class, $slug, null, null],
            [MeetingIndex::class, $slug, null, null],
            [MeetingWorkspace::class, $slug, 'meetingId', 'club_acc_committee_meetings'],
            [LiveMinuteTaker::class, $slug, 'meetingId', 'club_acc_committee_meetings'],
            [AgendaPackPreviewModal::class, $slug, 'meetingId', 'club_acc_committee_meetings'],
            [MemberIndex::class, $slug, null, null],
            [MemberImportPage::class, $slug, null, null],
            [MemberFormPage::class, $slug, 'memberId', 'club_acc_members'],
            [MemberProfile::class, $slug, 'memberId', 'club_acc_members'],
            [SubscriptionIndex::class, $slug, null, null],
        ];
    }

    /**
     * @return list<int>
     */
    private function foreignIds(string $table): array
    {
        return DB::table($table)->whereIn('club_id', $this->foreignClubIds())->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @return list<int>
     */
    private function foreignClubIds(): array
    {
        return Club::where('id', '!=', $this->attackerClub->id)->pluck('id')->all();
    }

    private function leaked(string $html): ?string
    {
        foreach ($this->foreignClubIds() as $clubId) {
            if (preg_match("/zzcanary[ -]?c?{$clubId}(?![0-9])|zzcanary{$clubId}x/i", $html, $found, PREG_OFFSET_CAPTURE)) {
                return preg_replace('/\s+/', ' ', strip_tags(substr($html, max(0, $found[0][1] - 90), 200)));
            }
        }

        return null;
    }

    /**
     * @return list<ReflectionMethod>
     */
    private function actionsOf(string $class): array
    {
        $actions = [];

        foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $declared = $method->getDeclaringClass()->getName();

            if (str_starts_with($declared, 'Livewire\\') || $method->isStatic() || str_starts_with($method->getName(), '__')) {
                continue;
            }

            if (in_array($method->getName(), self::SKIPPED_METHODS, true) || preg_match('/^(hydrate|dehydrate|updating|updated|get\w+Property|get\w+Attribute)/', $method->getName())) {
                continue;
            }

            if ($method->getAttributes(Computed::class) !== []) {
                continue;
            }

            $actions[] = $method;
        }

        return $actions;
    }

    /**
     * @return array<int, mixed>
     */
    private function argumentsFor(ReflectionMethod $method, int $id): array
    {
        return array_map(function (ReflectionParameter $parameter) use ($id) {
            $type = $parameter->getType() instanceof ReflectionNamedType ? $parameter->getType()->getName() : null;
            $idLike = (bool) preg_match('/id$/i', $parameter->getName());

            return match (true) {
                $type === 'int' => $id,
                $type === 'array' => [$id],
                $type === 'bool' => true,
                $type === 'float' => 1.0,
                $type === 'string' => $idLike ? (string) $id : 'x',
                default => $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : $id,
            };
        }, $method->getParameters());
    }

    /**
     * Public properties a browser can overwrite (not #[Locked]) whose names look like record ids.
     *
     * @return list<string>
     */
    private function unlockedIdProperties(string $class): array
    {
        $names = [];

        foreach ((new ReflectionClass($class))->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getAttributes(Locked::class) !== []) {
                continue;
            }

            if (preg_match('/(^|_)ids?$|Ids?$/', $property->getName())) {
                $names[] = $property->getName();
            }
        }

        return $names;
    }

    private function actAsAttackerWithForeignRecords(): void
    {
        $this->seed(DatabaseSeeder::class);

        $type = ClubType::first();
        $this->attackerClub = Club::withoutEvents(fn () => Club::create(['club_type_id' => $type->id, 'name' => 'Attacker Club', 'slug' => 'attacker-club', 'status' => 'active']));
        $attacker = User::factory()->create(['id' => 9000]);
        $this->attackerClub->users()->attach($attacker->id, ['role' => 'owner', 'status' => 'active']);

        // The attacker's own rows go first so the generic `*_id = 1` references in them resolve to its own data.
        $this->plantForeignRecords([$this->attackerClub->id, ...$this->foreignClubIds()]);

        Http::fake();
        $this->withExceptionHandling();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->actingAs($attacker);
    }

    /**
     * @param  list<array{0: class-string, 1: array<string, mixed>, 2: ?string, 3: ?string}>  $components
     * @return array{leaks: list<string>, changes: list<string>, blind: array<string, string>, tested: int}
     */
    private function tamper(array $components): array
    {
        $foreign = [1, 2, 3, 4, 5, 6];
        $leaks = [];
        $changes = [];
        $tested = [];
        $blind = [];

        foreach ($components as [$class, $mount, $idParam, $idTable]) {
            $short = (new ReflectionClass($class))->getShortName();
            $ownId = $idTable ? (int) DB::table($idTable)->where('club_id', $this->attackerClub->id)->value('id') : null;

            // 1. mounting on another club's record must be refused
            if ($idParam) {
                foreach ($this->foreignIds($idTable) as $foreignId) {
                    DB::beginTransaction();
                    $before = $this->snapshot();

                    try {
                        $html = Livewire::test($class, [...$mount, $idParam => $foreignId])->html();

                        if ($found = $this->leaked($html)) {
                            $leaks["{$short} mounted on foreign {$idParam}={$foreignId} renders {$found}"] = true;
                        }
                    } catch (\Throwable) {
                        // refused, which is the secure outcome
                    }

                    foreach ($this->foreignChanges($before, $this->snapshot(), $this->attackerClub->id) as $change) {
                        $changes["{$short} mounted on foreign {$idParam}={$foreignId} changed {$change}"] = true;
                    }

                    DB::rollBack();
                }
            }

            // 2. tamper with a component mounted on the attacker's own records
            $mountArgs = $idParam ? [...$mount, $idParam => $ownId] : $mount;

            try {
                Livewire::test($class, $mountArgs)->html();
            } catch (\Throwable $e) {
                $blind[$short] = mb_substr($e->getMessage(), 0, 200);
            }

            $properties = $this->unlockedIdProperties($class);

            foreach ($foreign as $id) {
                foreach ([null, ...$this->actionsOf($class)] as $action) {
                    DB::beginTransaction();
                    $before = $this->snapshot();
                    $label = ($properties === [] ? '' : $short.' with '.implode(', ', $properties)." = {$id}, ").($action ? "{$short}::{$action->getName()}(...) with id {$id}" : 'render only');
                    $tested[$label] = true;

                    try {
                        $component = Livewire::test($class, $mountArgs);

                        foreach ($properties as $property) {
                            $component->set($property, $id);
                        }

                        if ($action) {
                            $component->call($action->getName(), ...$this->argumentsFor($action, $id));
                        }

                        if ($found = $this->leaked($component->html())) {
                            $leaks["{$label} renders {$found}"] = true;
                        }
                    } catch (\Throwable) {
                        // refused, or failed validation
                    }

                    foreach ($this->foreignChanges($before, $this->snapshot(), $this->attackerClub->id) as $change) {
                        $changes["{$label} changed {$change}"] = true;
                    }

                    DB::rollBack();
                }
            }
        }

        return ['leaks' => array_keys($leaks), 'changes' => array_keys($changes), 'blind' => $blind, 'tested' => count($tested)];
    }

    public function test_tampered_livewire_components_cannot_reach_another_clubs_records(): void
    {
        $this->actAsAttackerWithForeignRecords();

        $found = $this->tamper($this->components());

        $this->assertSame([], $found['blind'], "Components that cannot render on the fixtures, so tampering with them proves nothing:\n".implode("\n", array_map(fn ($k, $v) => "{$k}: {$v}", array_keys($found['blind']), $found['blind'])));
        $this->assertGreaterThan(50, $found['tested'], 'The harness exercised suspiciously little.');
        $this->assertSame([], $found['leaks'], "Livewire renders that exposed another club's content:\n".implode("\n", $found['leaks']));
        $this->assertSame([], $found['changes'], "Livewire calls that changed another club's data:\n".implode("\n", $found['changes']));
    }

    /**
     * The harness only notices text, so this checks the numbers: another club's member giving figures must
     * not be loaded into the modal, on either charity screen.
     */
    public function test_charity_giving_modal_refuses_another_clubs_member(): void
    {
        $this->actAsAttackerWithForeignRecords();

        $foreignMember = (int) DB::table('club_acc_members')->whereIn('club_id', $this->foreignClubIds())->value('id');
        DB::table('club_acc_member_festival_giving')->insert([
            'member_id' => $foreignMember,
            'regular_giving_amount' => 4321.00,
            'total_donated_to_date' => 8765.00,
            'qualifies_for_jewel' => 1,
            'qualifies_for_bar' => 1,
            'created_at' => '2026-06-01 12:00:00',
            'updated_at' => '2026-06-01 12:00:00',
        ]);

        foreach ([CharityFestival::class, CharityDashboard::class] as $class) {
            $component = Livewire::test($class, ['clubSlug' => $this->attackerClub->slug]);

            try {
                $component->call('openGivingModal', $foreignMember);
            } catch (\Throwable) {
                // refused
            }

            $this->assertNotSame('4321.00', (string) $component->get('regular_giving_amount'), "{$class} loaded another club's giving amount");
            $this->assertNotSame($foreignMember, $component->get('givingMemberId'), "{$class} accepted another club's member");
        }
    }

    /**
     * Control: a component that saves for whichever member id the browser sends, defined only here. The
     * harness must catch it, otherwise a clean result above proves nothing.
     */
    public function test_the_harness_catches_a_component_that_forgets_to_scope_by_club(): void
    {
        $this->actAsAttackerWithForeignRecords();

        $found = $this->tamper([[UnscopedGivingProbe::class, ['clubSlug' => $this->attackerClub->slug], null, null]]);

        $this->assertNotEmpty(preg_grep('/UnscopedGivingProbe.*saveGiving.*changed club_acc_member_festival_giving/', $found['changes']), 'Harness missed an unscoped Livewire write.');
    }
}

/**
 * Deliberately vulnerable: trusts a browser-supplied member id.
 */
class UnscopedGivingProbe extends Component
{
    public ?int $givingMemberId = null;

    public function saveGiving(): void
    {
        MemberFestivalGiving::updateOrCreate(
            ['member_id' => $this->givingMemberId],
            ['regular_giving_amount' => 1, 'total_donated_to_date' => 1, 'qualifies_for_jewel' => false, 'qualifies_for_bar' => false],
        );
    }

    public function render(): string
    {
        return '<div></div>';
    }
}
