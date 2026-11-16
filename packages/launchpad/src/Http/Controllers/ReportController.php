<?php

namespace Launchpad\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Launchpad\Moderation\Reports;

class ReportController
{
    public function store(Request $request, Reports $reports): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(array_keys(Reports::types()))],
            'id' => ['required', 'integer'],
            'reason' => ['required', 'string', Rule::in(array_keys(Reports::reasons()))],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);

        $subject = Reports::types()[$data['type']]::findOrFail($data['id']);
        $reports->file($request->user(), $subject, $data['reason'], $data['details'] ?? null);

        return back()->with('status', __('Thank you. Our team will look at it.'));
    }
}
