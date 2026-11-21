<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Plainer wording for the invitation email (no "Portal", no Title Case). Only a template still holding the original
 * words is changed, so a superadmin's own wording is kept.
 */
return new class extends Migration
{
    private const OLD_SUBJECT = 'Invitation to Join {{club_name}} Portal';

    private const OLD_BODY = '<p>Dear {{member_name}},</p><p>You have been invited by {{club_name}} to activate your online member portal access.</p><p><a href="{{invite_url}}" style="display:inline-block;padding:10px 20px;background:#4f46e5;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:bold;">Accept Invitation & Setup Password</a></p><p>This invitation link will expire in {{expiry_days}} days.</p>';

    private const NEW_SUBJECT = '{{club_name}} has invited you to Club Manager';

    private const NEW_BODY = '<p>Dear {{member_name}},</p><p>{{club_name}} has invited you to see its meetings, reply to summonses and book dinners online.</p><p><a href="{{invite_url}}" style="display:inline-block;padding:10px 20px;background:#2563eb;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;">Choose your password</a></p><p>This link works for {{expiry_days}} days.</p>';

    public function up(): void
    {
        DB::table('default_email_templates')
            ->where('template_key', 'account_invitation')
            ->where('subject', self::OLD_SUBJECT)
            ->where('body_html', self::OLD_BODY)
            ->update(['name' => 'Invitation to sign in', 'subject' => self::NEW_SUBJECT, 'body_html' => self::NEW_BODY]);
    }

    public function down(): void
    {
        DB::table('default_email_templates')
            ->where('template_key', 'account_invitation')
            ->where('subject', self::NEW_SUBJECT)
            ->where('body_html', self::NEW_BODY)
            ->update(['name' => 'Lodge Account Email Invitation', 'subject' => self::OLD_SUBJECT, 'body_html' => self::OLD_BODY]);
    }
};
