<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add the event emails to the superadmin's editable templates, without touching any already edited.
     */
    public function up(): void
    {
        $shared = ['club_name', 'event_title', 'event_date', 'event_location', 'cancellation_policy'];

        $templates = [
            [
                'template_key' => 'event_booking_confirmation',
                'name' => 'Event booking confirmation',
                'subject' => '{{status_line}}: {{event_title}}',
                'body_html' => '<h2 style="margin:0 0 4px;">{{heading}}</h2><p style="margin:0 0 16px;color:#475569;">{{club_name}}</p><p><strong>{{event_title}}</strong><br>{{event_date}}<br>{{event_location}}</p>{{waiting_note}}<p style="margin-bottom:4px;"><strong>Booked for</strong></p>{{booked_for}}{{payment_details}}<p>You can view or cancel your booking at any time with this private link:<br><a href="{{manage_url}}">{{manage_url}}</a></p>{{cancellation_policy}}',
                'available_placeholders' => array_merge(['heading', 'status_line', 'contact_name', 'waiting_note', 'booked_for', 'payment_details', 'manage_url'], $shared),
            ],
            [
                'template_key' => 'event_guest_confirmation',
                'name' => 'Event guest confirmation',
                'subject' => '{{guest_status_line}}: {{event_title}}',
                'body_html' => '<h2 style="margin:0 0 4px;">{{heading}}</h2><p style="margin:0 0 16px;color:#475569;">{{club_name}}</p><p>Hello {{guest_name}},</p><p>{{booked_by}} has {{booking_verb}} a place for you at:</p><p><strong>{{event_title}}</strong><br>{{event_date}}<br>{{event_location}}</p>{{waiting_note}}{{meal_choices}}{{dietary_note}}<p>If anything needs to change, please speak to {{booked_by}}, who made the booking, or contact {{club_name}}.</p>{{cancellation_policy}}<p style="color:#64748b"><small>You are receiving this once because {{booked_by}} gave us your email address. If it wasn\'t expected you can ignore it, and we won\'t email you again about this booking.</small></p>',
                'available_placeholders' => array_merge(['heading', 'guest_status_line', 'guest_name', 'booked_by', 'booking_verb', 'waiting_note', 'meal_choices', 'dietary_note'], $shared),
            ],
            [
                'template_key' => 'event_payment_reminder',
                'name' => 'Event payment reminder',
                'subject' => 'Payment reminder: {{event_title}}',
                'body_html' => '<h2 style="margin:0 0 4px;">A reminder to pay for {{event_title}}</h2><p style="margin:0 0 16px;color:#475569;">{{club_name}}</p><p>Hello {{contact_name}},</p><p>You still have <strong>{{amount_due}}</strong> to pay for <strong>{{event_title}}</strong> on {{event_day}}{{due_text}}.</p>{{payment_how}}<p style="color:#64748b"><small>If you have already paid, thank you, and please ignore this reminder; it can take a few days for a payment to be checked.</small></p>',
                'available_placeholders' => ['club_name', 'contact_name', 'event_title', 'event_day', 'amount_due', 'due_text', 'payment_how'],
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
    }

    public function down(): void
    {
        DB::table('default_email_templates')->whereIn('template_key', ['event_booking_confirmation', 'event_guest_confirmation', 'event_payment_reminder'])->delete();
    }
};
