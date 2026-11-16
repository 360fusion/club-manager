<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CopyDatabaseCommandTest extends TestCase
{
    use RefreshDatabase;

    private function oldDatabase(): void
    {
        config(['database.connections.old_db' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        $this->artisan('migrate', ['--database' => 'old_db', '--force' => true]);
    }

    public function test_it_copies_every_row_parents_first_into_a_freshly_migrated_database(): void
    {
        $this->oldDatabase();
        $old = DB::connection('old_db');
        $old->table('club_types')->insert(['id' => 7, 'name' => 'Lodge', 'code' => 'lodge', 'available_modules' => '[]', 'default_settings' => '[]', 'created_at' => now(), 'updated_at' => now()]);
        $old->table('clubs')->insert(['id' => 40, 'club_type_id' => 7, 'name' => 'Old Lodge', 'slug' => 'old-lodge', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $old->table('users')->insert(['id' => 90, 'name' => 'Old Member', 'email' => 'old@example.test', 'password' => 'x', 'created_at' => '0000-00-00 00:00:00', 'updated_at' => now()]);
        $old->table('club_user')->insert(['club_id' => 40, 'user_id' => 90, 'role' => 'member', 'status' => 'active']);

        $this->artisan('app:copy-database', ['source' => 'old_db'])->assertSuccessful();

        $this->assertSame('Old Lodge', Club::findOrFail(40)->name);
        $this->assertNull(User::findOrFail(90)->created_at, 'a MySQL zero date becomes empty');
        $this->assertSame(1, DB::table('club_user')->where(['club_id' => 40, 'user_id' => 90])->count());
        $this->assertSame($old->table('default_email_templates')->count(), DB::table('default_email_templates')->count(), 'starter rows are replaced, not doubled');
    }

    public function test_it_refuses_a_database_that_already_has_users(): void
    {
        $this->oldDatabase();
        User::factory()->create();

        $this->artisan('app:copy-database', ['source' => 'old_db'])->assertFailed();
        $this->assertSame(1, User::count());
    }
}
