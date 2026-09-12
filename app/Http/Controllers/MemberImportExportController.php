<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 2) continue;

            $name = trim($row[0] ?? '');
            $email = strtolower(trim($row[1] ?? ''));
            $role = trim($row[2] ?? 'member');
            $memberNumber = trim($row[3] ?? 'MEM-' . rand(100, 999));

            if (empty($name) || empty($email)) continue;

            // Find or create User
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('password123'),
                ]
            );

            // Attach user to club if not attached
            if (! $club->users()->where('user_id', $user->id)->exists()) {
                $club->users()->attach($user->id, [
                    'role' => in_array($role, ['admin', 'coach', 'member', 'treasurer']) ? $role : 'member',
                    'member_number' => $memberNumber,
                    'status' => 'active',
                ]);
                $imported++;
            }
        }

        fclose($handle);

        return redirect()->back()->with('success', "Successfully imported {$imported} new members!");
    }

    /**
     * Export club roster to CSV download stream.
     */
    public function export(string $slug): StreamedResponse
    {
        $club = Club::where('slug', $slug)->with('users')->firstOrFail();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $club->slug . '-members-roster.csv"',
        ];

        return response()->stream(function () use ($club) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Member ID', 'Full Name', 'Email Address', 'Club Role', 'Member Number', 'Status', 'Join Date']);

            foreach ($club->users as $user) {
                fputcsv($handle, [
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->pivot->role,
                    $user->pivot->member_number,
                    $user->pivot->status,
                    $user->pivot->created_at?->format('Y-m-d'),
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }
}
