<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Invoice;

class InvoiceController extends Controller
{
    /**
     * Download or view styled receipt/invoice.
     */
    public function download(string $slug, int $id)
    {
        $club = Club::where('slug', $slug)->firstOrFail();
        $invoice = Invoice::where('club_id', $club->id)->with('user')->findOrFail($id);

        return response()->make("
            <!DOCTYPE html>
            <html>
            <head>
                <title>Receipt #{$invoice->invoice_number} - {$club->name}</title>
                <style>
                    body { font-family: 'Helvetica Neue', Arial, sans-serif; padding: 40px; color: #1e293b; background: #fff; }
                    .invoice-box { max-width: 650px; margin: auto; padding: 30px; border: 1px solid #e2e8f0; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
                    .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 20px; }
                    .title { font-size: 24px; font-weight: bold; color: #0284c7; }
                    .badge { background: #dcfce7; color: #15803d; font-size: 12px; font-weight: bold; padding: 4px 12px; border-radius: 99px; }
                    .details { margin: 30px 0; }
                    .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
                    .table th { background: #f8fafc; font-size: 12px; text-transform: uppercase; color: #64748b; }
                    .total { font-size: 20px; font-weight: bold; color: #0f172a; text-align: right; margin-top: 20px; }
                    .footer { text-align: center; margin-top: 40px; font-size: 12px; color: #94a3b8; }
                </style>
            </head>
            <body>
                <div class='invoice-box'>
                    <div class='header'>
                        <div>
                            <div class='title'>{$club->name}</div>
                            <div style='font-size: 12px; color: #64748b;'>Official Payment Receipt</div>
                        </div>
                        <span class='badge'>PAID</span>
                    </div>

                    <div class='details'>
                        <p><strong>Receipt Number:</strong> {$invoice->invoice_number}</p>
                        <p><strong>Issued To:</strong> {$invoice->user->name} ({$invoice->user->email})</p>
                        <p><strong>Date:</strong> ".($invoice->paid_at ? $invoice->paid_at->format('F d, Y @ H:i') : date('F d, Y'))."</p>
                    </div>

                    <table class='table'>
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th style='text-align: right;'>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>{$invoice->title}</td>
                                <td style='text-align: right;'>£".number_format($invoice->amount, 2)."</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class='total'>
                        Total Paid: £".number_format($invoice->amount, 2)."
                    </div>

                    <div class='footer'>
                        Thank you for your support of {$club->name}.<br/>
                        Powered by ClubManager Platform
                    </div>
                </div>
            </body>
            </html>
        ", 200, ['Content-Type' => 'text/html']);
    }
}
