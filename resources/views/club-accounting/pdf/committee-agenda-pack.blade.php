<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Committee Agenda Pack — {{ $meeting->title }}</title>
<style>
    @page {
        size: A4 portrait;
        margin: 15mm 15mm 20mm 15mm;
    }
    body {
        font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        color: #1e293b;
        font-size: 10pt;
        line-height: 1.45;
        margin: 0;
        padding: 0;
        background: #ffffff;
    }
    .header-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
        border-bottom: 2px solid #0f172a;
        padding-bottom: 15px;
    }
    .lodge-title {
        font-size: 18pt;
        font-weight: bold;
        color: #0f172a;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin: 0;
    }
    .lodge-subtitle {
        font-size: 10pt;
        color: #64748b;
        font-weight: bold;
        margin-top: 4px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .badge-rule {
        display: inline-block;
        padding: 4px 8px;
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fde68a;
        font-size: 8pt;
        font-weight: bold;
        border-radius: 4px;
        text-transform: uppercase;
        margin-top: 6px;
    }
    .meta-box {
        width: 100%;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        margin-bottom: 20px;
        border-collapse: collapse;
    }
    .meta-box td {
        padding: 8px 12px;
        font-size: 9pt;
        border-bottom: 1px solid #e2e8f0;
    }
    .meta-label {
        font-weight: bold;
        color: #475569;
        width: 18%;
        text-transform: uppercase;
        font-size: 8pt;
    }
    .meta-value {
        color: #0f172a;
        font-weight: 500;
    }
    h2.section-heading {
        font-size: 12pt;
        font-weight: bold;
        color: #0f172a;
        border-bottom: 1.5px solid #cbd5e1;
        padding-bottom: 5px;
        margin-top: 20px;
        margin-bottom: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .agenda-item {
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        margin-bottom: 10px;
        background: #ffffff;
        page-break-inside: avoid;
    }
    .agenda-header {
        background: #f1f5f9;
        padding: 8px 12px;
        font-weight: bold;
        font-size: 10pt;
        color: #0f172a;
        border-bottom: 1px solid #e2e8f0;
    }
    .agenda-type {
        float: right;
        font-size: 8pt;
        font-weight: bold;
        color: #4338ca;
        background: #e0e7ff;
        padding: 2px 6px;
        border-radius: 3px;
        text-transform: uppercase;
    }
    .agenda-body {
        padding: 10px 12px;
        font-size: 9pt;
        color: #334155;
    }
    .recommendation-box {
        margin-top: 6px;
        padding: 6px 10px;
        background: #f0fdf4;
        border-left: 3px solid #16a34a;
        font-size: 8.5pt;
        color: #166534;
    }
    .data-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 8px;
        margin-bottom: 15px;
        font-size: 8.5pt;
        page-break-inside: avoid;
    }
    .data-table th {
        background: #0f172a;
        color: #ffffff;
        text-align: left;
        padding: 6px 10px;
        font-size: 8pt;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .data-table td {
        padding: 6px 10px;
        border-bottom: 1px solid #e2e8f0;
        color: #334155;
    }
    .data-table tr:nth-child(even) td {
        background: #f8fafc;
    }
    .text-right {
        text-align: right;
    }
    .text-center {
        text-align: center;
    }
    .text-bold {
        font-weight: bold;
    }
    .text-muted {
        color: #64748b;
    }
    .roll-call-box {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        padding: 10px;
        border-radius: 6px;
        margin-bottom: 15px;
        font-size: 9pt;
    }
    .footer-signatures {
        margin-top: 35px;
        width: 100%;
        border-collapse: collapse;
        page-break-inside: avoid;
    }
    .footer-signatures td {
        width: 50%;
        padding: 10px 20px;
        vertical-align: top;
    }
    .sig-line {
        border-top: 1px dashed #94a3b8;
        margin-top: 40px;
        padding-top: 5px;
        font-size: 8.5pt;
        color: #475569;
        text-align: center;
    }
</style>
</head>
<body>

    <!-- Header Block -->
    <table class="header-table">
        <tr>
            <td style="width: 75%;">
                <div class="lodge-title">{{ $club->name }}</div>
                <div class="lodge-subtitle">Committee & Executive Board of General Purposes</div>
                <div class="badge-rule">UGLE Rule 153, 158 & 159 Compliant Agenda Pack</div>
            </td>
            <td style="width: 25%; text-align: right; vertical-align: middle;">
                <div style="font-size: 28pt;">🏛️</div>
                <div style="font-size: 8pt; color: #64748b; font-weight: bold; margin-top: 4px;">
                    PACK REF: COM-{{ str_pad($meeting->id, 4, '0', STR_PAD_LEFT) }}
                </div>
            </td>
        </tr>
    </table>

    <!-- Meeting Metadata Box -->
    <table class="meta-box">
        <tr>
            <td class="meta-label">Meeting Title:</td>
            <td class="meta-value" colspan="3"><strong>{{ $meeting->title }}</strong></td>
        </tr>
        <tr>
            <td class="meta-label">Date & Time:</td>
            <td class="meta-value">{{ $meeting->meeting_date ? $meeting->meeting_date->format('l, jS F Y \a\t H:i') : 'TBD' }}</td>
            <td class="meta-label">Location:</td>
            <td class="meta-value">{{ $meeting->location ?: 'Lodge Committee Room' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Committee Chair:</td>
            <td class="meta-value">{{ $meeting->chair?->name ?? 'Worshipful Master / Chairman' }}</td>
            <td class="meta-label">Secretary:</td>
            <td class="meta-value">{{ $meeting->secretary?->name ?? 'Lodge Secretary' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Status:</td>
            <td class="meta-value" colspan="3">
                <span style="font-weight: bold; text-transform: uppercase; color: #0284c7;">
                    {{ $meeting->status->label() }}
                </span>
                <span class="text-muted" style="margin-left: 12px;">
                    Generated on {{ now()->format('d M Y, H:i') }}
                </span>
            </td>
        </tr>
    </table>

    <!-- Roll Call & Attendees -->
    <h2 class="section-heading">1. Committee Roll-Call & Apologies</h2>
    <div class="roll-call-box">
        @php
            $present = $meeting->attendees->where('attendance_type', \App\Domains\ClubAccounting\Enums\AttendanceType::Present);
            $apologies = $meeting->attendees->where('attendance_type', \App\Domains\ClubAccounting\Enums\AttendanceType::Apology);
            $remote = $meeting->attendees->where('attendance_type', \App\Domains\ClubAccounting\Enums\AttendanceType::RemoteLink);
        @endphp

        <div style="margin-bottom: 6px;">
            <strong style="color: #0f172a;">Expected in Attendance:</strong>
            @if($present->isNotEmpty() || $remote->isNotEmpty())
                <span>{{ $present->pluck('name')->merge($remote->map(fn($r) => $r->name . ' (Remote)'))->implode(', ') }}</span>
            @else
                <span class="text-muted">None recorded</span>
            @endif
        </div>

        @if($apologies->isNotEmpty())
            <div style="margin-top: 6px; color: #991b1b;">
                <strong>Apologies for Absence Received:</strong>
                <span>{{ $apologies->pluck('name')->implode(', ') }}</span>
            </div>
        @endif
    </div>

    <!-- Section 2: Order of Business -->
    <h2 class="section-heading">2. Order of Business (Agenda)</h2>
    @forelse($meeting->agendaItems as $item)
        @php
            $itemTypeEnum = $item->item_type instanceof \App\Domains\ClubAccounting\Enums\CommitteeItemType
                ? $item->item_type
                : \App\Domains\ClubAccounting\Enums\CommitteeItemType::tryFrom($item->item_type);
            $typeLabel = $itemTypeEnum ? $itemTypeEnum->label() : ($item->item_type ?: 'General');
        @endphp
        <div class="agenda-item">
            <div class="agenda-header">
                <span>{{ $item->order }}. {{ $item->title }}</span>
                <span class="agenda-type">{{ $typeLabel }}</span>
            </div>
            <div class="agenda-body">
                @if($item->description)
                    <div style="margin-bottom: 6px;">{{ $item->description }}</div>
                @endif

                @if($item->discussion_notes)
                    <div style="margin-top: 4px; font-style: italic; color: #475569;">
                        <strong>Briefing Notes:</strong> {{ $item->discussion_notes }}
                    </div>
                @endif

                @if($item->recommendation_text)
                    <div class="recommendation-box">
                        <strong>Committee Recommendation:</strong> {{ $item->recommendation_text }}
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div style="padding: 12px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 6px; font-size: 9pt; color: #64748b; text-align: center;">
            No structured agenda items recorded for this meeting.
        </div>
    @endforelse

    <!-- Section 3: Accounts & Bills for Audit (Rule 158) -->
    @if(isset($unpaid_bills) && $unpaid_bills->isNotEmpty())
        <h2 class="section-heading">3. Accounts & Invoices for Audit (Rule 158)</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Invoice / Bill Ref</th>
                    <th>Payee / Vendor</th>
                    <th>Category</th>
                    <th>Due Date</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @php $totalBills = 0; @endphp
                @foreach($unpaid_bills as $bill)
                    @php $totalBills += $bill->amount; @endphp
                    <tr>
                        <td>{{ $bill->bill_number }}</td>
                        <td><strong>{{ $bill->vendor_name }}</strong></td>
                        <td>{{ $bill->category ?: 'General Supplies' }}</td>
                        <td>{{ $bill->due_date ? $bill->due_date->format('d M Y') : 'Immediate' }}</td>
                        <td class="text-right text-bold">{{ \App\Support\Currencies::symbolFor($club) }}{{ number_format($bill->amount, 2) }}</td>
                    </tr>
                @endforeach
                <tr style="background: #f1f5f9;">
                    <td colspan="4" class="text-bold text-right">Total Invoices Pending Audit & Payment Approval:</td>
                    <td class="text-right text-bold" style="color: #b91c1c;">{{ \App\Support\Currencies::symbolFor($club) }}{{ number_format($totalBills, 2) }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    <!-- Section 4: Candidates Awaiting Committee Vetting (Rule 159) -->
    @if(isset($candidates) && $candidates->isNotEmpty())
        <h2 class="section-heading">4. Candidate Vetting Registry (Rule 159)</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Candidate Name</th>
                    <th>Application Date</th>
                    <th>Email Contact</th>
                    <th>Committee Action Required</th>
                </tr>
            </thead>
            <tbody>
                @foreach($candidates as $cand)
                    <tr>
                        <td><strong>{{ $cand->name }}</strong></td>
                        <td>{{ $cand->created_at->format('d M Y') }}</td>
                        <td>{{ $cand->email }}</td>
                        <td style="color: #d97706; font-weight: bold;">Verify Proposer/Seconder Credentials</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- Section 5: Action Items & Delegated Tasks -->
    @if($meeting->tasks->isNotEmpty())
        <h2 class="section-heading">5. Committee Action Points & Delegated Tasks</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Action Item</th>
                    <th>Assigned Brethren</th>
                    <th>Target Due Date</th>
                    <th>Current Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($meeting->tasks as $task)
                    <tr>
                        <td><strong>{{ $task->title }}</strong></td>
                        <td>{{ $task->assigned_to_name ?: ($task->assignedTo?->name ?? 'Unassigned') }}</td>
                        <td>{{ $task->due_date ? \Carbon\Carbon::parse($task->due_date)->format('d M Y') : 'Prior to next meeting' }}</td>
                        <td>
                            <span style="font-weight: bold; text-transform: uppercase; font-size: 7.5pt; color: {{ $task->status->value === 'completed' ? '#16a34a' : '#d97706' }};">
                                {{ $task->status->value }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- Section 6: Notices of Motion Pipeline -->
    @if($meeting->noticesOfMotion->isNotEmpty())
        <h2 class="section-heading">6. Notices of Motion for Open Lodge Summons</h2>
        @foreach($meeting->noticesOfMotion as $motion)
            <div style="background: #f8fafc; border-left: 3px solid #6366f1; border-top: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; border-radius: 4px; padding: 8px 12px; margin-bottom: 8px; font-size: 9pt;">
                <strong>{{ $motion->title }}</strong>
                <div style="margin-top: 4px; font-style: italic; color: #334155;">
                    "{{ $motion->motion_text }}"
                </div>
                <div style="margin-top: 4px; font-size: 8pt; color: #64748b;">
                    Proposer: {{ $motion->proposer_name ?: ($motion->proposer?->name ?? 'Committee') }} • Status: {{ ucfirst(str_replace('_', ' ', $motion->status)) }}
                </div>
            </div>
        @endforeach
    @endif

    <!-- Formal Sign-Off Lines -->
    <table class="footer-signatures">
        <tr>
            <td>
                <div class="sig-line">
                    <strong>{{ $meeting->chair?->name ?? 'Worshipful Master / Chairman' }}</strong><br>
                    Committee Chairman
                </div>
            </td>
            <td>
                <div class="sig-line">
                    <strong>{{ $meeting->secretary?->name ?? 'Lodge Secretary' }}</strong><br>
                    Secretary & Scribe
                </div>
            </td>
        </tr>
    </table>

</body>
</html>
