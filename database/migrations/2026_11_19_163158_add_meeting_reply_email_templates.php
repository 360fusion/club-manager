<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The meeting reply schedule's emails (App\Mail\MeetingReplyMail, MeetingNumbersMail) as editable templates.
     * Templates already there are left as they are.
     */
    public function up(): void
    {
        $member = ['club_name', 'member_name', 'meeting_title', 'meeting_date', 'meeting_time', 'venue', 'reply_links', 'reply_url', 'payment'];
        $secretary = ['club_name', 'meeting_title', 'meeting_date', 'dining', 'numbers', 'meeting_url'];
        $header = '<h2 style="margin:0 0 4px;">{{meeting_title}}</h2><p style="margin:0 0 16px;color:#475569;">{{club_name}}</p>';
        $when = '<p>{{meeting_date}} at {{meeting_time}}<br>{{venue}}</p>';
        $private = '<p style="color:#64748b"><small>These links are private to you and work until the day of the meeting.</small></p>';

        $templates = [
            [
                'template_key' => 'meeting_reply_ask',
                'name' => 'Meeting: will you be there?',
                'subject' => 'Will you be there? {{meeting_title}}, {{meeting_date}}',
                'body_html' => $header.'<p>Dear {{member_name}},</p><p>Please let the Secretary know whether you will be at the next meeting, so the festive board can be booked.</p>'.$when.'{{reply_links}}'.$private,
                'available_placeholders' => $member,
            ],
            [
                'template_key' => 'meeting_reply_last_call',
                'name' => 'Meeting: last call for dining',
                'subject' => 'Last call for dining: {{meeting_title}}',
                'body_html' => $header.'<p>Dear {{member_name}},</p><p>Replies for dining close at midnight tonight, and we have not heard from you yet.</p>'.$when.'{{reply_links}}'.$private,
                'available_placeholders' => $member,
            ],
            [
                'template_key' => 'meeting_reply_confirm',
                'name' => 'Meeting: reminder to those coming',
                'subject' => 'Reminder: {{meeting_title}}, {{meeting_date}}',
                'body_html' => $header.'<p>Dear {{member_name}},</p><p>You are down as <strong>{{your_reply}}</strong>. If that has changed, please update your reply.</p>'.$when.'{{reply_links}}{{payment}}'.$private,
                'available_placeholders' => [...$member, 'your_reply'],
            ],
            [
                'template_key' => 'meeting_numbers',
                'name' => 'Meeting: dining numbers for the Secretary',
                'subject' => 'Dining numbers: {{meeting_title}}, {{dining}} dining',
                'body_html' => $header.'<p>Replies for dining have closed. These are the numbers so far.</p>{{numbers}}<p><a href="{{meeting_url}}">Open the meeting</a></p>',
                'available_placeholders' => $secretary,
            ],
            [
                'template_key' => 'meeting_numbers_changed',
                'name' => 'Meeting: numbers changed',
                'subject' => 'Numbers changed: {{meeting_title}}, {{dining}} dining',
                'body_html' => $header.'<p><strong>Changed:</strong> {{change}}</p>{{numbers}}<p><a href="{{meeting_url}}">Open the meeting</a></p>',
                'available_placeholders' => [...$secretary, 'change'],
            ],
            [
                'template_key' => 'meeting_numbers_caterer',
                'name' => 'Meeting: numbers for the caterer',
                'subject' => 'Dining numbers: {{meeting_title}}, {{meeting_date}}, {{dining}} dining',
                'body_html' => $header.'<p>The numbers for the festive board.</p><p>{{meeting_date}}<br>{{venue}}</p><p><strong>{{change}}</strong></p>{{numbers}}',
                'available_placeholders' => ['club_name', 'meeting_title', 'meeting_date', 'venue', 'dining', 'numbers', 'change'],
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
        DB::table('default_email_templates')->whereIn('template_key', ['meeting_reply_ask', 'meeting_reply_last_call', 'meeting_reply_confirm', 'meeting_numbers', 'meeting_numbers_changed', 'meeting_numbers_caterer'])->delete();
    }
};
