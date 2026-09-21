<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * More editable event emails, and a pay-now link in the reminder. Templates already edited are left as they are.
     */
    public function up(): void
    {
        $common = ['club_name', 'contact_name', 'event_title', 'event_date'];

        $templates = [
            [
                'template_key' => 'event_payment_received',
                'name' => 'Event payment received',
                'subject' => 'Payment received: {{event_title}}',
                'body_html' => '<h2 style="margin:0 0 4px;">Payment received</h2><p style="margin:0 0 16px;color:#475569;">{{club_name}}</p><p>Hello {{contact_name}},</p><p>Thank you, we have received <strong>{{amount_received}}</strong> for <strong>{{event_title}}</strong> on {{event_date}}.</p><p>Booking total {{total}}: {{payment_status}}.{{balance_text}}</p><p style="color:#64748b"><small>Reference {{reference}}</small></p>',
                'available_placeholders' => array_merge($common, ['amount_received', 'total', 'payment_status', 'balance_text', 'reference']),
            ],
            [
                'template_key' => 'event_refund',
                'name' => 'Event refund',
                'subject' => 'Refund: {{event_title}}',
                'body_html' => '<h2 style="margin:0 0 4px;">Refund</h2><p style="margin:0 0 16px;color:#475569;">{{club_name}}</p><p>Hello {{contact_name}},</p><p>We have refunded <strong>{{amount_refunded}}</strong> for <strong>{{event_title}}</strong> on {{event_date}}. It can take a few days to reach you.</p><p style="color:#64748b"><small>Reference {{reference}}</small></p>',
                'available_placeholders' => array_merge($common, ['amount_refunded', 'reference']),
            ],
            [
                'template_key' => 'event_place_available',
                'name' => 'Event waiting list: a place has opened',
                'subject' => 'A place is available: {{event_title}}',
                'body_html' => '<h2 style="margin:0 0 4px;">You are booked in</h2><p style="margin:0 0 16px;color:#475569;">{{club_name}}</p><p>Hello {{contact_name}},</p><p>Good news: a place has opened up, so your booking for <strong>{{event_title}}</strong> ({{people}} people) is now confirmed.</p><p>{{event_date}}<br>{{event_location}}</p>{{pay_link}}',
                'available_placeholders' => array_merge($common, ['event_location', 'people', 'pay_link']),
            ],
        ];

        foreach ($templates as $template) {
            if (DB::table('default_email_templates')->where('template_key', $template['template_key'])->exists()) {
                continue;
            }

            DB::table('default_email_templates')->insert([
                ...$template,
                'available_placeholders' => json_encode($template['available_placeholders']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $reminder = DB::table('default_email_templates')->where('template_key', 'event_payment_reminder')->first();

        if ($reminder && ! str_contains($reminder->body_html, '{{pay_link}}') && str_contains($reminder->body_html, '{{payment_how}}')) {
            $placeholders = array_values(array_unique(array_merge(json_decode($reminder->available_placeholders ?? '[]', true) ?: [], ['pay_link'])));

            DB::table('default_email_templates')->where('id', $reminder->id)->update([
                'body_html' => str_replace('{{payment_how}}', '{{pay_link}}{{payment_how}}', $reminder->body_html),
                'available_placeholders' => json_encode($placeholders),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('default_email_templates')->whereIn('template_key', ['event_payment_received', 'event_refund', 'event_place_available'])->delete();
    }
};
