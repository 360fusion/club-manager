<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Http\Requests\AccountingContact\StoreContactRequest;
use App\Http\Requests\AccountingContact\UpdateContactRequest;
use App\Models\Accounting\AccountingContact;
use App\Models\Club;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Currencies;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ledger contacts: suppliers, customers and members shown as contacts. Split out of AccountingAdminController; the URLs, route names and permissions are unchanged.
 */
class AccountingContactController extends Controller
{
    public function createContact(string $clubSlug): Response
    {
        $club = $this->findClub($clubSlug);

        return Inertia::render('Admin/Accounting/ContactForm', [
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
            ],
            'contact' => null,
        ]);
    }

    public function editMemberContact(string $clubSlug, int $userId): Response
    {
        $club = $this->findClub($clubSlug);
        $user = User::whereHas('clubs', fn ($q) => $q->where('clubs.id', $club->id))
            ->where('users.id', $userId)
            ->firstOrFail();

        // Auto-split the user's name into parts if not already set
        $nameParts = explode(' ', trim($user->name), 3);
        $defaultFirst = $nameParts[0] ?? '';
        $defaultMiddle = count($nameParts) === 3 ? $nameParts[1] : null;
        $defaultLast = count($nameParts) >= 2 ? end($nameParts) : null;

        $contact = AccountingContact::firstOrCreate(
            [
                'club_id' => $club->id,
                'user_id' => $user->id,
            ],
            [
                'type' => 'person',
                'name' => $user->name,
                'first_name' => $defaultFirst,
                'middle_names' => $defaultMiddle,
                'last_name' => $defaultLast,
                'preferred_name' => null,
                'contact_person' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? null,
                'role' => 'Club Member',
                'is_active' => true,
                'is_member' => true,
            ]
        );

        // ── Financial Summary ──────────────────────────────────────────────
        $userInvoices = Invoice::where('club_id', $club->id)
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        $amountOwed = $userInvoices->where('status', DocumentStatus::Unpaid->value)->sum('amount');
        $totalPaid = $userInvoices->where('status', DocumentStatus::Paid->value)->sum('amount');
        $creditBalance = $userInvoices->where('status', DocumentStatus::Refunded->value)->sum('amount');
        $invoiceCount = $userInvoices->count();

        $lastPaidInvoice = $userInvoices->where('status', DocumentStatus::Paid->value)->first();
        $lastPaymentDate = $lastPaidInvoice?->paid_at?->format('d M Y')
            ?? $lastPaidInvoice?->created_at?->format('d M Y');
        $lastPaymentAmount = $lastPaidInvoice ? Currencies::format($lastPaidInvoice->amount, $club) : null;

        $unpaidInvoices = $userInvoices->where('status', DocumentStatus::Unpaid->value)->map(fn ($inv) => [
            'id' => $inv->id,
            'invoice_number' => $inv->invoice_number,
            'title' => $inv->title,
            'amount' => Currencies::format($inv->amount, $club),
            'created_at' => $inv->created_at->format('d M Y'),
        ])->values();

        // Active membership plan
        $membership = \DB::table('memberships')
            ->join('membership_plans', 'memberships.membership_plan_id', '=', 'membership_plans.id')
            ->where('memberships.club_id', $club->id)
            ->where('memberships.user_id', $user->id)
            ->where('memberships.status', 'active')
            ->select('membership_plans.name', 'membership_plans.price', 'membership_plans.billing_period',
                'memberships.starts_at', 'memberships.ends_at', 'memberships.status')
            ->orderByDesc('memberships.starts_at')
            ->first();

        // Member since (oldest membership record, or club pivot created_at)
        $memberSince = \DB::table('club_user')
            ->where('club_id', $club->id)
            ->where('user_id', $user->id)
            ->value('created_at');

        $memberSummary = [
            'amount_owed' => $amountOwed,
            'amount_owed_formatted' => Currencies::format($amountOwed, $club),
            'credit_balance' => $creditBalance,
            'credit_balance_formatted' => Currencies::format($creditBalance, $club),
            'total_paid' => $totalPaid,
            'total_paid_formatted' => Currencies::format($totalPaid, $club),
            'invoice_count' => $invoiceCount,
            'last_payment_date' => $lastPaymentDate,
            'last_payment_amount' => $lastPaymentAmount,
            'unpaid_invoices' => $unpaidInvoices,
            'membership_plan' => $membership ? $membership->name : null,
            'membership_price' => $membership ? Currencies::format($membership->price, $club) : null,
            'membership_period' => $membership ? $membership->billing_period : null,
            'membership_renews' => ($membership && $membership->ends_at)
                ? Carbon::parse($membership->ends_at)->format('d M Y') : null,
            'membership_status' => $membership ? $membership->status : 'no_plan',
            'member_since' => $memberSince
                ? Carbon::parse($memberSince)->format('d M Y') : null,
        ];
        // ─────────────────────────────────────────────────────────────────

        return Inertia::render('Admin/Accounting/ContactForm', [
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
            ],
            'contact' => [
                'id' => $contact->id,
                'type' => $contact->type,
                'name' => $contact->name,
                'first_name' => $contact->first_name,
                'middle_names' => $contact->middle_names,
                'last_name' => $contact->last_name,
                'preferred_name' => $contact->preferred_name,
                'contact_person' => $contact->contact_person,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'role' => $contact->role,
                'tax_id' => $contact->tax_id,
                'address_line_1' => $contact->address_line_1,
                'address_line_2' => $contact->address_line_2,
                'city' => $contact->city,
                'postcode' => $contact->postcode,
                'country' => $contact->country,
                'notes' => $contact->notes,
                'is_active' => $contact->is_active,
                'is_member' => true,
            ],
            'member_summary' => $memberSummary,
        ]);
    }

    public function editContact(string $clubSlug, int $id): Response
    {
        $club = $this->findClub($clubSlug);
        $contact = AccountingContact::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        return Inertia::render('Admin/Accounting/ContactForm', [
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
            ],
            'contact' => [
                'id' => $contact->id,
                'type' => $contact->type,
                'name' => $contact->name,
                'first_name' => $contact->first_name,
                'middle_names' => $contact->middle_names,
                'last_name' => $contact->last_name,
                'preferred_name' => $contact->preferred_name,
                'contact_person' => $contact->contact_person,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'role' => $contact->role,
                'tax_id' => $contact->tax_id,
                'address_line_1' => $contact->address_line_1,
                'address_line_2' => $contact->address_line_2,
                'city' => $contact->city,
                'postcode' => $contact->postcode,
                'country' => $contact->country,
                'notes' => $contact->notes,
                'is_active' => $contact->is_active,
                'is_member' => $contact->is_member ?? false,
            ],
        ]);
    }

    public function storeContact(StoreContactRequest $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        $validated = $request->validated();

        $firstName = $validated['first_name'] ?? null;
        $lastName = $validated['last_name'] ?? null;
        $middleNames = $validated['middle_names'] ?? null;

        if (! empty($firstName)) {
            $fullName = trim(implode(' ', array_filter([
                $firstName,
                $middleNames,
                $lastName,
            ])));
        } else {
            $fullName = trim($validated['name'] ?? '');
            $parts = explode(' ', $fullName, 2);
            $firstName = $parts[0] ?? $fullName;
            $lastName = $parts[1] ?? null;
        }

        AccountingContact::create([
            'club_id' => $club->id,
            'type' => $validated['type'] ?? 'person',
            'name' => $fullName,
            'first_name' => $firstName,
            'middle_names' => $middleNames,
            'last_name' => $lastName,
            'preferred_name' => $validated['preferred_name'] ?? null,
            'contact_person' => $validated['contact_person'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'role' => $validated['role'],
            'tax_id' => $validated['tax_id'] ?? null,
            'address_line_1' => $validated['address_line_1'] ?? null,
            'address_line_2' => $validated['address_line_2'] ?? null,
            'city' => $validated['city'] ?? null,
            'postcode' => $validated['postcode'] ?? null,
            'country' => $validated['country'] ?? 'United Kingdom',
            'notes' => $validated['notes'] ?? null,
            'is_active' => true,
            'is_member' => false,
        ]);

        return redirect()->route('admin.accounting.index', ['clubSlug' => $club->slug, 'tab' => 'contacts'])->with('success', 'New contact added to directory.');
    }

    public function updateContact(UpdateContactRequest $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $contact = AccountingContact::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $validated = $request->validated();

        $firstName = $validated['first_name'] ?? null;
        $lastName = $validated['last_name'] ?? null;
        $middleNames = $validated['middle_names'] ?? null;

        if (! empty($firstName)) {
            $fullName = trim(implode(' ', array_filter([
                $firstName,
                $middleNames,
                $lastName,
            ])));
        } else {
            $fullName = trim($validated['name'] ?? $contact->name);
            $parts = explode(' ', $fullName, 2);
            $firstName = $contact->first_name ?: ($parts[0] ?? $fullName);
            $lastName = $contact->last_name ?: ($parts[1] ?? null);
        }

        $updateData = [
            'name' => $fullName,
            'first_name' => $firstName,
            'middle_names' => $middleNames,
            'last_name' => $lastName,
            'preferred_name' => $validated['preferred_name'] ?? $contact->preferred_name,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'role' => $contact->is_member ? 'Club Member' : $validated['role'],
            'tax_id' => $validated['tax_id'] ?? null,
            'address_line_1' => $validated['address_line_1'] ?? null,
            'address_line_2' => $validated['address_line_2'] ?? null,
            'city' => $validated['city'] ?? null,
            'postcode' => $validated['postcode'] ?? null,
            'country' => $validated['country'] ?? 'United Kingdom',
            'notes' => $validated['notes'] ?? null,
        ];

        if (isset($validated['type'])) {
            $updateData['type'] = $validated['type'];
        }
        if (array_key_exists('contact_person', $validated)) {
            $updateData['contact_person'] = $validated['contact_person'];
        }

        $contact->update($updateData);

        return redirect()->route('admin.accounting.index', ['clubSlug' => $club->slug, 'tab' => 'contacts'])->with('success', 'Contact details updated successfully.');
    }

    public function destroyContact(string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $contact = AccountingContact::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $contact->delete();

        return redirect()->route('admin.accounting.index', ['clubSlug' => $club->slug, 'tab' => 'contacts'])->with('success', 'Contact removed from directory.');
    }
}
