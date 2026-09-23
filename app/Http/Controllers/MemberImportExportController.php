<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Support\Csv;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberImportExportController extends Controller
{
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
