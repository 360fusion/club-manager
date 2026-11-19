<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The old member replies were copied into event_registrations (2026_09_21_164733) and nothing reads
     * event_user any more. Refuses to run while any old reply has no booking, so no reply is lost.
     */
    public function up(): void
    {
        if (! Schema::hasTable('event_user')) {
            return;
        }

        $notCopied = DB::table('event_user')
            ->join('users', 'users.id', '=', 'event_user.user_id')
            ->whereNotExists(fn ($query) => $query->from('event_registrations')
                ->whereColumn('event_registrations.event_id', 'event_user.event_id')
                ->whereColumn('event_registrations.user_id', 'event_user.user_id'))
            ->count();

        if ($notCopied > 0) {
            throw new RuntimeException("{$notCopied} rows in event_user have no event registration; run the copy migration's up() again before dropping it.");
        }

        Schema::drop('event_user');
    }

    /**
     * Brings back the empty table (the old replies are not restored), so earlier migrations can roll back.
     */
    public function down(): void
    {
        Schema::create('event_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('attendance_status')->default('pending');
            $table->timestamp('summons_sent_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->boolean('attending_dining')->default(false);
            $table->json('menu_selections')->nullable();
            $table->text('dietary_requirements')->nullable();
            $table->string('payment_status')->default('unpaid');
            $table->decimal('amount_paid', 10, 2)->default(0.00);
            $table->timestamp('checked_in_at')->nullable();
            $table->foreignId('ticket_tier_id')->nullable()->constrained('event_ticket_tiers')->onDelete('set null');
            $table->string('ticket_qr_code')->nullable()->unique();
            $table->decimal('discount_applied', 10, 2)->default(0.00);
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
            $table->index(['event_id', 'attendance_status']);
        });
    }
};
