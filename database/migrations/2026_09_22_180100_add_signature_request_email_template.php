<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('default_email_templates')->where('template_key', 'signature_request')->exists()) {
            return;
        }

        DB::table('default_email_templates')->insert([
            'template_key' => 'signature_request',
            'name' => 'Signature request',
            'subject' => 'Please sign: {{document_label}}',
            'body_html' => '<h2 style="margin:0 0 4px;">A signature is needed</h2><p style="margin:0 0 16px;color:#475569;">{{club_name}}</p><p>Hello {{signer_name}},</p><p>{{club_name}} has asked you to sign:</p><p><strong>{{document_label}}</strong></p><p><a href="{{sign_url}}">Review and sign</a></p><p style="color:#64748b"><small>This link is private to you. You can type your name or draw your signature on the next page.</small></p>',
            'available_placeholders' => json_encode(['club_name', 'signer_name', 'document_label', 'sign_url']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('default_email_templates')->where('template_key', 'signature_request')->delete();
    }
};
