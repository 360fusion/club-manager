<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\User;
use App\Support\ClubAccess;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberImportExportController extends Controller
{
    /**
     * Bulk import members from uploaded CSV file.
     */
    public function import(Request $request, string $slug)
    {
        $club = Club::where('slug', $slug)->firstOrFail();

        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $file = $request->file('csv_file');
        $handle = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($handle); // First line header

        $imported = 0;
        $skipped = 0;

        // Only an owner can hand out the admin role through an import.
        $allowedRoles = ClubAccess::role($request->user(), $club) === 'owner' || $request->user()->is_super_admin
            ? ['admin', 'coach', 'member', 'treasurer']
            : ['coach', 'member', 'treasurer'];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) >= 2) {
                $name = trim($row[0]);
                $email = strtolower(trim($row[1]));
                $role = isset($row[2]) ? strtolower(trim($row[2])) : 'member';
                $memberNumber = isset($row[3]) ? trim($row[3]) : null;

                if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '') {
                    $skipped++;

                    continue;
                }

                // Existing members keep their role and status; an import must not demote an owner or reactivate someone.
                $existingUser = User::where('email', $email)->first();

                if ($existingUser && $club->users()->where('users.id', $existingUser->id)->exists()) {
                    $skipped++;

                    continue;
                }

                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => mb_substr($name, 0, 255),
                        // Nobody knows this password; new members set their own through "forgot password".
                        'password' => Hash::make(Str::random(40)),
                    ]
                );

                $club->users()->syncWithoutDetaching([$user->id]);
                $club->users()->updateExistingPivot($user->id, [
                    'role' => in_array($role, $allowedRoles, true) ? $role : 'member',
                    'member_number' => $memberNumber !== null ? mb_substr($memberNumber, 0, 50) : null,
                    'status' => 'active',
                ]);
                $imported++;
            }
        }

        fclose($handle);

        return redirect()->back()->with('success', "Successfully imported {$imported} new members!".($skipped ? " {$skipped} rows were skipped (invalid, or already in the club)." : ''));
    }

    /**
     * Export club roster to CSV download stream.
     */
    public function export(string $slug): StreamedResponse
    {
        $club = Club::where('slug', $slug)->with('users')->firstOrFail();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$club->slug.'-members-roster.csv"',
        ];

        return response()->stream(function () use ($club) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Member ID', 'Full Name', 'Email Address', 'Club Role', 'Member Number', 'Status', 'Join Date']);

            foreach ($club->users as $user) {
                fputcsv($handle, Csv::safe([
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->pivot->role,
                    $user->pivot->member_number,
                    $user->pivot->status,
                    $user->pivot->created_at?->format('Y-m-d'),
                ]));
            }

            fclose($handle);
        }, 200, $headers);
    }
}
