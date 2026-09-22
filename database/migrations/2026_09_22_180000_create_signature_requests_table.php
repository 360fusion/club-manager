<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signature_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();

            // The record being signed (a Candidate for Form P, an AccountingYearAudit for the auditor sign-off, ...).
            $table->string('signable_type');
            $table->unsignedBigInteger('signable_id');

            // Discriminates between multiple signers on the same signable, e.g. 'form_p_proposer' vs 'form_p_seconder'.
            $table->string('purpose');

            // Who is being asked to sign: a User (has login access) or a club_acc_members roster Member (may not).
            $table->string('signer_type');
            $table->unsignedBigInteger('signer_id');
            $table->string('signer_name');
            $table->string('signer_email')->nullable();

            $table->string('token_hash')->unique();
            $table->string('status')->default('pending');

            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at');
            $table->timestamp('expires_at')->nullable();

            $table->string('method')->nullable();
            $table->string('typed_name')->nullable();
            $table->string('consent_ip')->nullable();
            $table->string('consent_user_agent')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('document_hash')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['signable_type', 'signable_id', 'purpose']);
            $table->index(['club_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_requests');
    }
};
