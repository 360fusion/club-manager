<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Lodge Governance & Committee' }}</title>
    @vite(['resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full antialiased font-sans text-slate-800 bg-slate-50">
    <div class="min-h-full">
        <!-- Top Nav -->
        <header class="bg-slate-900 border-b border-slate-800 text-white sticky top-0 z-30 shadow-md">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-2xl">🏛️</span>
                    <div>
                        <span class="font-black text-sm tracking-tight text-white block">Lodge Committee & Board Governance</span>
                        <span class="text-[10px] font-bold text-amber-400 uppercase tracking-wider block">UGLE Rule 153, 158, 159, 160 Compliant</span>
                    </div>
                </div>

                <div class="flex items-center gap-3 text-xs font-bold">
                    <a href="{{ url()->previous() }}" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl transition-all">
                        ← Back
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                @if (session()->has('success'))
                    <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2 shadow-sm">
                        <span>✅</span>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                @if (session()->has('error'))
                    <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold flex items-center gap-2 shadow-sm">
                        <span>⚠️</span>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                {{ $slot }}
            </div>
        </main>
    </div>

    @livewireScripts
</body>
</html>
