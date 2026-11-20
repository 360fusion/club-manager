<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dues reminders (app:send-dues-reminders): when each subscription was last chased, when the member said they had
     * paid (which pauses reminders), and the reminder email as an editable template.
     */
    public function up(): void
    {
        Schema::table('club_acc_member_subscriptions', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('said_paid_at')->nullable();
        });

        if (DB::table('default_email_templates')->where('template_key', 'dues_reminder')->exists()) {
            return;
        }

        DB::table('default_email_templates')->insert([
            'template_key' => 'dues_reminder',
            'name' => 'Dues: reminder to pay',
            'subject' => 'Your {{year}} dues: {{left}} still to pay',
            'body_html' => '<h2 style="margin:0 0 4px;">Your dues for {{year}}</h2><p style="margin:0 0 16px;color:#475569;">{{club_name}}</p>'
                .'<p>Dear {{member_name}},</p><p>This is a friendly reminder about your subscription. You have paid <strong>{{paid}}</strong> of <strong>{{amount_due}}</strong>, so <strong>{{left}}</strong> is still to pay, due by {{due_date}}.</p>'
                .'<p>{{progress}}</p>{{bank_details}}'
                .'<p><a href="{{dues_url}}">See your dues and payments</a></p>'
                .'<p style="color:#64748b"><small>If you have already paid, thank you: you can tell us on that page and we will stop reminding you while the Treasurer checks the bank.</small></p>',
            'available_placeholders' => json_encode(['club_name', 'member_name', 'year', 'amount_due', 'paid', 'left', 'due_date', 'progress', 'reference', 'dues_url', 'bank_details']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('default_email_templates')->where('template_key', 'dues_reminder')->delete();

        Schema::table('club_acc_member_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['reminder_sent_at', 'said_paid_at']);
        });
    }
};
