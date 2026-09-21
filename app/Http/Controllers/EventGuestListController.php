<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Event;
use App\Services\Events\GuestListBuilder;
use App\Support\Csv;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Printable guest list, catering summary and CSV for an event.
 */
class EventGuestListController extends Controller
{
    public function guestList(Request $request, string $clubSlug, int $id, GuestListBuilder $builder): Response
    {
        [$club, $event] = $this->find($clubSlug, $id);
        $sort = $request->query('sort') === 'table' ? 'table' : 'name';

        return $this->render($request, 'events.guest-list', $event, [
            'club' => $club,
            'event' => $event,
            'people' => $builder->people($event, $sort),
            'waitlist' => $builder->waitlist($event),
            'sort' => $sort,
        ], 'guest-list', 'portrait');
    }

    public function catering(Request $request, string $clubSlug, int $id, GuestListBuilder $builder): Response
    {
        [$club, $event] = $this->find($clubSlug, $id);

        return $this->render($request, 'events.catering-summary', $event, [
            'club' => $club,
            'event' => $event,
            'summary' => $builder->catering($event),
        ], 'catering-summary', 'portrait');
    }

    public function export(string $clubSlug, int $id, GuestListBuilder $builder): StreamedResponse
    {
        [, $event] = $this->find($clubSlug, $id);
        $people = $builder->people($event);
        $filename = 'guest-list-'.Str::slug($event->title).'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($people) {
            echo Csv::line(['Name', 'Guest of', 'Type', 'Email', 'Lodge / organisation', 'Ticket', 'Dining', 'Starter', 'Main', 'Dessert', 'Dietary requirements', 'Table', 'Payment status', 'Amount paid', 'Checked in']);

            foreach ($people as $person) {
                echo Csv::line([
                    $person['name'],
                    $person['guest_of'],
                    $person['is_guest'] ? 'Guest' : 'Member',
                    $person['email'],
                    $person['organisation'],
                    $person['ticket'],
                    $person['dining'] ? 'Yes' : 'No',
                    $person['meal']['starter'],
                    $person['meal']['main'],
                    $person['meal']['dessert'],
                    $person['dietary'],
                    $person['table_label'],
                    $person['payment_status'],
                    number_format($person['amount_paid'], 2, '.', ''),
                    $person['checked_in'] ? 'Yes' : 'No',
                ]);
            }
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{0: Club, 1: Event}
     */
    private function find(string $clubSlug, int $id): array
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        return [$club, Event::where('club_id', $club->id)->findOrFail($id)];
    }

    /**
     * The page itself (with a Print button), or a PDF when ?download=1.
     *
     * @param  array<string, mixed>  $data
     */
    private function render(Request $request, string $view, Event $event, array $data, string $name, string $orientation): Response
    {
        if (! $request->boolean('download')) {
            return response()->view($view, $data + ['printable' => true]);
        }

        return Pdf::loadView($view, $data + ['printable' => false])
            ->setPaper('a4', $orientation)
            ->download($name.'-'.Str::slug($event->title).'.pdf');
    }
}
