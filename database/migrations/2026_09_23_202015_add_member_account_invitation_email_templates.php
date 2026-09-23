<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $button = '<p><a href="{{invite_url}}" style="display:inline-block;padding:10px 20px;background:#4f46e5;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:bold;">Accept invitation &amp; set up your account</a></p>';

        $templates = [
            'account_invitation' => [
                'name' => 'Lodge Account Email Invitation',
                'subject' => 'Invitation to join {{club_name}} online',
                'body_html' => '<p>Dear {{member_name}},</p><p>{{inviter_name}} has invited you to set up your online member account for {{club_name}}.</p>'.$button.'<p>This invitation link will expire in {{expiry_days}} days.</p>',
            ],
            'account_invitation_reminder' => [
                'name' => 'Lodge Account Invitation Reminder',
                'subject' => 'Reminder: your {{club_name}} invitation',
                'body_html' => '<p>Dear {{member_name}},</p><p>A short reminder that you have been invited to set up your online member account for {{club_name}}.</p>'.$button.'<p>This invitation link will expire in {{expiry_days}} days.</p>',
            ],
        ];

        $placeholders = json_encode(['member_name', 'club_name', 'invite_url', 'expiry_days', 'inviter_name']);

        foreach ($templates as $key => $template) {
            $existing = DB::table('default_email_templates')->where('template_key', $key)->first();

            if ($existing) {
                // Keep the superadmin's wording; only make the new placeholder available.
                DB::table('default_email_templates')->where('template_key', $key)->update(['available_placeholders' => $placeholders, 'updated_at' => now()]);

                continue;
            }

            DB::table('default_email_templates')->insert($template + [
                'template_key' => $key,
                'available_placeholders' => $placeholders,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('default_email_templates')->where('template_key', 'account_invitation_reminder')->delete();
    }
};
