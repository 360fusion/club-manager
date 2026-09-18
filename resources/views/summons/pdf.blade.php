<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Summons — {{ $club->name }} No. {{ $club->lodge_number ?? '1418' }}</title>
<style>
<style>
  @page {
    size: A4 landscape;
    margin: 10mm 12mm;
  }

  body {
    font-family: "Times New Roman", Times, Georgia, serif;
    color: #111111;
    background: #334155;
    font-size: 9.5pt;
    line-height: 1.3;
    margin: 0;
    padding: 0 20px 40px 20px;
  }

  .page-container {
    display: flex;
    flex-direction: row;
    gap: 0;
    height: 186mm;
    max-height: 186mm;
    width: 273mm;
    max-width: 100%;
    margin: 0 auto 30px auto;
    background: #ffffff;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4), 0 3px 10px rgba(0, 0, 0, 0.2);
    border-radius: 4px;
    box-sizing: border-box;
    page-break-after: always;
    break-after: page;
    page-break-inside: avoid;
    break-inside: avoid;
    position: relative;
  }

  .page-container:last-child {
    page-break-after: avoid;
    break-after: avoid;
    margin-bottom: 0;
  }

  .column {
    flex: 1;
    width: 50%;
    padding: 14px 24px;
    box-sizing: border-box;
    overflow: hidden;
  }

  .column-left {
    border-right: 1px solid #333333;
  }

  .column-right {
  }

  .side-banner {
    background: #1e293b;
    color: #f8fafc;
    padding: 10px 20px;
    border-radius: 12px;
    font-family: system-ui, -apple-system, sans-serif;
    font-size: 13px;
    font-weight: 700;
    margin: 24px auto 14px auto;
    max-width: 273mm;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
  }

  .side-banner span {
    font-size: 11px;
    font-weight: 500;
    color: #cbd5e1;
  }

  .side-print-tag {
    position: absolute;
    bottom: -6mm;
    right: 0;
    font-size: 7.5pt;
    font-family: sans-serif;
    color: #666666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  /* Typography Utilities */
  h2 {
    font-size: 11pt;
    font-weight: bold;
    text-transform: uppercase;
    margin: 0 0 8px 0;
    letter-spacing: 0.5px;
  }

  .section-title {
    font-size: 10pt;
    font-weight: bold;
    text-transform: uppercase;
    margin-top: 12px;
    margin-bottom: 4px;
    letter-spacing: 0.5px;
  }

  /* Member Roll Styling (Page 1 Left) */
  .member-roll-header {
    font-size: 10.5pt;
    font-weight: bold;
    text-align: center;
    margin-bottom: 10px;
  }

  .member-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 8.5pt;
  }

  .member-table td {
    padding: 1.5px 0;
    vertical-align: top;
  }

  .col-year {
    width: 12%;
    font-weight: normal;
  }

  .col-name {
    width: 60%;
  }

  .col-rank {
    width: 28%;
    text-align: right;
  }

  /* Cover Page Styling (Page 1 Right) */
  .cover-container {
    text-align: center;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    height: 100%;
    padding: 18px 14px;
    box-sizing: border-box;
    border: 1.5px solid #222222;
  }

  .emblem-svg {
    width: 65px;
    height: 65px;
    margin: 0 auto 8px auto;
  }

  .prov-title {
    font-size: 11.5pt;
    font-weight: bold;
    letter-spacing: 1px;
    margin-bottom: 6px;
  }

  .prov-officer {
    font-size: 9pt;
    margin-bottom: 4px;
  }

  .prov-officer strong {
    display: block;
    font-size: 9.5pt;
    margin-top: 2px;
  }

  .lodge-title-block {
    margin: 18px 0 12px 0;
  }

  .lodge-main-name {
    font-size: 19pt;
    font-weight: bold;
    letter-spacing: 1.5px;
    text-transform: uppercase;
  }

  .lodge-number {
    font-size: 12.5pt;
    font-weight: bold;
    margin-top: 4px;
  }

  .motto {
    font-style: italic;
    font-size: 10.5pt;
    margin-top: 8px;
  }

  /* Officer List (Page 2 Left) */
  .officer-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 8.5pt;
  }

  .officer-table td {
    padding: 1.5px 0;
    vertical-align: top;
  }

  .role-name {
    width: 42%;
  }

  .holder-name {
    width: 58%;
  }

  /* Business / Agenda Styling (Page 2 Right) */
  .business-list {
    margin: 6px 0 10px 0;
    padding: 0;
    list-style: none;
  }

  .business-item {
    display: flex;
    margin-bottom: 5px;
    font-size: 9.5pt;
  }

  .business-num {
    font-weight: bold;
    width: 22px;
    flex-shrink: 0;
  }

  .business-text {
    flex-grow: 1;
  }

  .notice-box {
    margin-top: 8px;
    font-size: 8.5pt;
    line-height: 1.3;
  }

  @media print {
    body { background: none !important; margin: 0 !important; padding: 0 !important; }
    .no-print { display: none !important; }
    .page-container {
      box-shadow: none !important;
      border-radius: 0 !important;
      margin: 0 !important;
      width: 100% !important;
      max-width: none !important;
    }
  }
</style>
</head>
<body>

  <!-- Top Floating Action Toolbar (Hidden during Print) -->
  <div class="no-print" style="position: fixed; top: 0; left: 0; right: 0; background: #0f172a; color: #ffffff; padding: 12px 24px; display: flex; align-items: center; justify-content: space-between; z-index: 99999; box-shadow: 0 4px 20px rgba(0,0,0,0.3); font-family: system-ui, -apple-system, sans-serif;">
    <div style="display: flex; align-items: center; gap: 12px;">
      <span style="font-weight: 800; font-size: 14px; color: #f8fafc;">📜 {{ $meeting->title }} — Summons PDF Preview</span>
      <span style="background: #1e293b; color: #94a3b8; padding: 3px 10px; border-radius: 9999px; font-size: 11px; font-weight: 600;">Double-Sided A4 Landscape Duplex</span>
    </div>

    <div style="display: flex; align-items: center; gap: 10px;">
      <button onclick="window.print()" style="background: #3b82f6; color: #ffffff; border: none; padding: 8px 16px; border-radius: 8px; font-weight: 700; font-size: 12px; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: all;">
        🖨️ Print Double-Sided (Duplex A4)
      </button>
      <a href="{{ route('admin.meetings.pdf', ['clubSlug' => $club->slug, 'id' => $meeting->id, 'download' => 1]) }}" style="background: #10b981; color: #ffffff; border: none; padding: 8px 16px; border-radius: 8px; font-weight: 700; font-size: 12px; text-decoration: none; display: flex; align-items: center; gap: 6px; transition: all;">
        📥 Download Summons (.pdf)
      </a>
    </div>
  </div>
  
  <div class="no-print" style="height: 55px;"></div>

  <!-- SIDE 1 (FRONT / OUTER SHEET) -->
  <div class="no-print side-banner">
    <div>📄 SIDE 1 (FRONT / OUTER SHEET)</div>
    <span>Double-Sided Print Side A: Right Half = Front Cover | Left Half = Membership Roll</span>
  </div>

  <div class="page-container">
    <div class="no-print side-print-tag">[ SIDE 1: FRONT (OUTER SHEET) ]</div>
    
    <!-- Page 1 Left: Full Membership Roll -->
    <div class="column column-left">
      <div class="member-roll-header">
        {{ $club->name }} No {{ $club->lodge_number ?? '1418' }} Members
      </div>

      <table class="member-table">
        @forelse($members as $m)
          <tr>
            <td class="col-year">{{ $m->pivot->year_joined ?? $m->created_at->format('Y') }}</td>
            <td class="col-name">{{ $m->pivot->rank_prefix ?? 'WBro' }} {{ $m->name }}</td>
            <td class="col-rank">{{ $m->pivot->rank_suffix ?? ($m->pivot->rank ?? '') }}</td>
          </tr>
        @empty
          <tr><td colspan="3">No members enrolled.</td></tr>
        @endforelse
      </table>
    </div>

    <!-- Page 1 Right: Cover Page Header -->
    <div class="column column-right">
      <div class="cover-container">
        
        <!-- Logo / Emblem -->
        <div>
          @if($meeting->front_page_logo)
            <img src="{{ $meeting->front_page_logo }}" style="max-height: 75px; width: auto; margin: 0 auto 10px auto; display: block;" alt="Logo" />
          @else
            <svg class="emblem-svg" viewBox="0 0 100 100" fill="none" stroke="#111" stroke-width="2">
              <!-- Compass -->
              <path d="M50 15 L20 85 M50 15 L80 85" stroke-width="3"/>
              <circle cx="50" cy="15" r="5" fill="#111"/>
              <!-- Square -->
              <path d="M25 45 L50 70 L75 45 M50 70 L50 95" stroke-width="3"/>
            </svg>
          @endif

          <div class="prov-title">{{ $meeting->front_page_title ?? 'PROVINCIAL GRAND LODGE' }}</div>
          
          <div class="prov-officer">
            Provincial Grand Master
            <strong>{{ $meeting->provincial_grand_master ?? 'R WBro John David Watts' }}</strong>
          </div>

          <div class="prov-officer" style="margin-top: 6px;">
            Deputy Provincial Grand Master
            <strong>{{ $meeting->deputy_provincial_grand_master ?? 'WBro Andrew Peter Faul Foster PSGD' }}</strong>
          </div>

          <div class="prov-officer" style="margin-top: 6px;">
            Assistant Provincial Grand Masters
            <div style="font-size: 8.5pt; margin-top: 2px;">
              {!! nl2br(e($meeting->assistant_provincial_grand_masters ?? "WBro Dr. Rakesh Bhalla PSGD\nWBro Thomas Fred Gittins PSGD\nWBro Martin Rankin PJGD\nWBro Michael Stuart Shaw PJGD\nWBro Lt Col John William Henry")) !!}
            </div>
          </div>
        </div>

        <!-- Lodge Main Name & Motto -->
        <div class="lodge-title-block">
          <div class="lodge-main-name">{{ strtoupper($meeting->cover_club_name ?? $club->name) }}</div>
          <div class="lodge-number">No {{ $meeting->cover_club_number ?? ($club->lodge_number ?? '1418') }}</div>
          <div class="motto">{{ $meeting->cover_motto ?? ($club->motto ?? 'Fraternus Amor Maneto') }}</div>
        </div>

        <!-- Master Name -->
        <div style="font-size: 11pt; font-weight: bold;">
          {{ $meeting->cover_worshipful_master ?? ('WBro ' . ($worshipfulMaster->name ?? 'KD Lord')) }}<br>
          <span style="font-size: 10pt; font-weight: normal; letter-spacing: 1px;">MASTER</span>
        </div>

      </div>
    </div>

  </div>


  <!-- SIDE 2 (BACK / INSIDE SHEET) -->
  <div class="no-print side-banner">
    <div>📄 SIDE 2 (BACK / INNER SHEET)</div>
    <span>Double-Sided Print Side B: Left Half = Officers for Year | Right Half = Summons Letter & Agenda</span>
  </div>

  <div class="page-container">
    <div class="no-print side-print-tag">[ SIDE 2: BACK (INNER SHEET) ]</div>
    
    <!-- Page 2 Left: Officers Roster, Honorary Members, & Secretary Contacts -->
    <div class="column column-left">
      
      <h2>{{ $meeting->officers_year_label ?? 'OFFICERS FOR 2025-2026' }}</h2>
      
      <table class="officer-table">
        @if(!empty($meeting->officers_roster) && is_array($meeting->officers_roster) && count($meeting->officers_roster) > 0)
          @foreach($meeting->officers_roster as $officer)
            <tr>
              <td class="role-name"><strong>{{ $officer['role'] ?? '' }}:</strong></td>
              <td class="holder-name">{{ $officer['name'] ?? '' }}</td>
            </tr>
          @endforeach
        @elseif(isset($officerAssignments) && count($officerAssignments) > 0)
          @foreach($officerAssignments as $assignment)
            <tr>
              <td class="role-name"><strong>{{ $assignment->officerRole->title }}:</strong></td>
              <td class="holder-name">
                {{ $assignment->prefix_titles ?? 'WBro' }} {{ $assignment->user ? $assignment->user->name : $assignment->custom_name }}
                @if($assignment->suffix_titles)
                  <span style="font-size: 7.5pt; color: #444;">{{ $assignment->suffix_titles }}</span>
                @endif
              </td>
            </tr>
          @endforeach
        @elseif(!empty($club->settings['officers_roster']) && is_array($club->settings['officers_roster']))
          @foreach($club->settings['officers_roster'] as $officer)
            <tr>
              <td class="role-name"><strong>{{ $officer['role'] ?? '' }}:</strong></td>
              <td class="holder-name">{{ $officer['name'] ?? '' }}</td>
            </tr>
          @endforeach
        @else
          <tr><td>Worshipful Master:</td><td>WBro Kristopher D Lord.</td></tr>
          <tr><td>Senior Warden:</td><td>Bro Corey N Irons.</td></tr>
          <tr><td>Junior Warden:</td><td>Bro David A Chapman.</td></tr>
          <tr><td>Chaplain:</td><td>WBro Kenneth Hardy PM PPJGD.</td></tr>
          <tr><td>Treasurer:</td><td>WBro George F Bird PM PAGDC PPJGW.</td></tr>
          <tr><td>Secretary:</td><td>WBro Ian McCabe PM.</td></tr>
          <tr><td>Director of Ceremonies:</td><td>WBro Richard I Barlow PM PPGSwd.</td></tr>
          <tr><td>Almoner:</td><td>WBro Stephen Bakewell PM PPGReg.</td></tr>
          <tr><td>Charity Steward:</td><td>WBro Kristopher D Lord.</td></tr>
          <tr><td>Senior Deacon:</td><td>Bro Ethan E Chapman.</td></tr>
          <tr><td>Junior Deacon:</td><td>Bro Alfie TS Mensah.</td></tr>
          <tr><td>Inner Guard:</td><td>Bro Jonathon A Corney.</td></tr>
          <tr><td>Tyler:</td><td>WBro Donald Marshall PM PPJGW.</td></tr>
        @endif
      </table>

      @if($meeting->honorary_members_text || $club->default_honorary_members_text)
        <div class="section-title">HONORARY MEMBER</div>
        <div style="font-size: 8.5pt; color: #222;">
          {!! nl2br(e($meeting->honorary_members_text ?? $club->default_honorary_members_text)) !!}
        </div>
      @endif

      <div class="section-title">SECRETARY CONTACT DETAILS</div>
      <div style="font-size: 8.5pt; line-height: 1.3;">
        Email: {{ $secretaryUser->email ?? ('secretary@' . $club->slug . '.org') }}<br>
        Tel: {{ $club->settings['phone'] ?? '07718 903347' }}
      </div>

      <div style="margin-top: 10px; font-size: 8pt; font-style: italic; color: #333; border-top: 1px solid #ddd; pt: 4px;">
        {{ $meeting->sick_distressed_notes ?? 'Should you be aware of any illness or any other unusual circumstances involving any member, please communicate same to W Bro S Bakewell, Tel: 01642 888383.' }}
      </div>

    </div>

    <!-- Page 2 Right: Formal Request Letter, Business Agenda, Festive Board, & Fraternal Visits -->
    <div class="column column-right">
      
      <div style="display: flex; justify-content: space-between; font-size: 9.5pt; font-weight: bold; margin-bottom: 8px;">
        <span>{{ $meeting->salutation ?? 'Dear Sir and Brother,' }}</span>
        <span>{{ \Carbon\Carbon::parse($meeting->meeting_date)->format('j M Y') }}</span>
      </div>

      <div style="font-size: 9pt; text-align: justify; line-height: 1.3; margin-bottom: 10px;">
        {{ $meeting->intro_text ?? ('You are respectfully requested to attend the Regular Meeting of this Lodge of Free and Accepted Masons, to be held in the ' . $meeting->venue . ', on ' . \Carbon\Carbon::parse($meeting->meeting_date)->format('l jS F Y') . ' at ' . $meeting->starts_at . '.') }}
        <br><br>
        By order of the Worshipful Master.<br>
        <em>Yours fraternally — WBro {{ $secretaryUser->name ?? 'I McCabe' }}, Secretary</em>
      </div>

      <h2>BUSINESS</h2>
      <div class="business-list">
        @forelse($meeting->agendaItems as $item)
          <div class="business-item">
            <div class="business-num">{{ $item->item_number }}.</div>
            <div class="business-text">
              <strong>{{ $item->title }}</strong>
              @if($item->description)
                <div style="font-size: 8.5pt; color: #333;">{{ $item->description }}</div>
              @endif
            </div>
          </div>
        @empty
          <div class="business-item"><div class="business-num">1.</div><div class="business-text">To confirm the minutes of the Regular Meeting held previously.</div></div>
          <div class="business-item"><div class="business-num">2.</div><div class="business-text">To report on the proceedings of Grand Lodge.</div></div>
          <div class="business-item"><div class="business-num">3.</div><div class="business-text">To transact any other lawful Masonic Business.</div></div>
        @endforelse
      </div>

      @if(isset($charityGrants) && count($charityGrants) > 0)
        <div class="section-title" style="margin-top: 8px;">CHARITABLE DONATION PROPOSALS & VOTE</div>
        <div style="font-size: 8.5pt; line-height: 1.3; color: #222;">
          @foreach($charityGrants as $grant)
            <div style="margin-bottom: 4px; padding-bottom: 4px; border-bottom: 1px dashed #ccc;">
              <strong>£{{ number_format($grant->amount, 2) }}</strong> to <strong>{{ $grant->recipient_name }}</strong> — {{ $grant->purpose }}
              @if($grant->proposer || $grant->seconder)
                <div style="font-size: 7.5pt; color: #555;">
                  @if($grant->proposer) Proposed by: Bro {{ $grant->proposer->first_name }} {{ $grant->proposer->last_name }} @endif
                  @if($grant->seconder) | Seconded by: Bro {{ $grant->seconder->first_name }} {{ $grant->seconder->last_name }} @endif
                </div>
              @endif
            </div>
          @endforeach
        </div>
      @endif

      <div style="font-size: 8.5pt; margin-top: 6px;">
        <strong>Dress:</strong> {{ $meeting->dress_code ?? 'Dinner Jacket, White Gloves' }}<br>
        <strong>Rehearsal:</strong> {{ $meeting->rehearsal_text ?? ('The rehearsal should it be necessary will be at ' . ($meeting->rehearsal_starts_at ?: '6:00pm') . ' at ' . $meeting->venue . '.') }}
      </div>

      <div class="section-title">FESTIVE BOARD</div>
      <div class="notice-box" style="background: #fdfdfd; border: 1px solid #ccc; padding: 6px 8px; font-size: 8.5pt;">
        {{ $meeting->festive_board_theme ?? 'The Lodge of Fraternity will be holding their Festive Board.' }} Please confirm your attendance to {{ $secretaryUser->name ?? 'Secretary' }} by emailing {{ $secretaryUser->email ?? 'secretary@lodge.org' }}.<br>
        Price: <strong>£{{ number_format($meeting->dining_cost_member, 2) }}</strong><br>
        Sort Code: <strong>{{ $meeting->bank_sort_code ?: ($club->settings['bank_sort_code'] ?? '20-65-18') }}</strong> | Acc No: <strong>{{ $meeting->bank_account_number ?: ($club->settings['bank_account_number'] ?? '83920145') }}</strong><br>
        Reference: <strong>your name or names</strong>.<br>
        @if($meeting->payment_link)
          Pay Online: <strong><a href="{{ $meeting->payment_link }}" style="color: #000; text-decoration: underline;">{{ $meeting->payment_link }}</a></strong><br>
        @endif
        Bookings must be made by {{ \Carbon\Carbon::parse($meeting->rsvp_cutoff_at)->format('jS F Y') }}.
        @if(!empty(trim($meeting->festive_board_menu ?? '')))
          <div style="margin-top: 6px; border-top: 1px solid #ddd; padding-top: 4px;">
            <strong>Menu:</strong><br>
            {!! nl2br(e($meeting->festive_board_menu)) !!}
          </div>
        @endif
      </div>

      @if($meeting->fraternal_visits_text)
        <div class="section-title">FRATERNAL VISITS</div>
        <div style="font-size: 8.5pt; line-height: 1.3; color: #222;">
          {!! nl2br(e($meeting->fraternal_visits_text)) !!}
        </div>
      @endif

    </div>

  </div>

</body>
</html>
