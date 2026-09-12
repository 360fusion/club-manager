<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Spatie\LaravelPdf\Facades\Pdf;

class InvoiceController extends Controller
{
    /**
     * Download or view styled receipt/invoice as PDF.
     */
    public function download(Request $request, string $slug, int $id)
    {
        $club = Club::where('slug', $slug)->firstOrFail();
        $invoice = Invoice::where('club_id', $club->id)->with('user')->findOrFail($id);

        if ($request->query('format') === 'html') {
            return view('pdf.invoice', compact('club', 'invoice'));
        }

        return Pdf::view('pdf.invoice', compact('club', 'invoice'))
            ->name("receipt-{$invoice->invoice_number}.pdf");
    }
}
