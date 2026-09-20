<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-[#f1f5f9] dark:bg-slate-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ ($title ?? 'Committee') . ' - ClubAdmin' }}</title>
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('superadmin-theme');
                var dark = stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.classList.toggle('dark', dark);
            } catch (e) {}
        })();
    </script>
    @vite(['resources/js/app.js'])
    @livewireStyles
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="min-h-screen bg-[#f1f5f9] dark:bg-slate-950 text-slate-800 font-sans antialiased">
    @php
        $user = auth()->user();
        $clubSlug = request()->route('clubSlug') ?? request()->route('slug') ?? (isset($club) && is_object($club) ? $club->slug : null);
        $currentClub = isset($club) && is_object($club) ? $club : ($clubSlug ? \App\Models\Club::where('slug', $clubSlug)->first() : null);
        $clubName = $currentClub?->name ?? 'The Lodge of Fraternity';
        $clubSlug = $currentClub?->slug ?? $clubSlug ?? 'lodge-of-fraternity';
        $userClubs = $user ? $user->clubs : collect();
        $currentClubRole = $user && $currentClub ? ($user->clubs->firstWhere('id', $currentClub->id)?->pivot?->role ?? 'admin') : 'admin';
        $pageTitle = $title ?? 'Lodge Committee';
    @endphp

    <div
        x-data="{ sidebarOpen: false, switcherOpen: false, userMenuOpen: false }"
        class="min-h-screen bg-[#f1f5f9] dark:bg-slate-950 text-slate-800 font-sans flex flex-col md:flex-row"
    >
        <!-- Mobile Sidebar Toggle Header -->
        <div class="md:hidden bg-[#1e293b] text-white p-4 flex items-center justify-between border-b border-slate-700 sticky top-0 z-40">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center font-black text-white text-xs shadow-md">
                    CA
                </div>
                <span class="font-extrabold tracking-wider text-base uppercase">CLUBADMIN</span>
            </div>
            <button @click="sidebarOpen = !sidebarOpen" class="p-2 rounded-lg bg-slate-800 text-slate-300 hover:text-white cursor-pointer">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>

        <!-- Left Sidebar Navigation -->
        <aside
            :class="sidebarOpen ? 'block' : 'hidden md:flex'"
            class="w-full md:w-64 bg-[#1e293b] text-slate-300 flex-shrink-0 flex-col justify-between transition-all duration-200 z-30"
        >
            <div>
                <!-- Interactive Workspace Switcher Header -->
                <div class="relative p-4 border-b border-slate-800">
                    <button
                        type="button"
                        @click="switcherOpen = !switcherOpen"
                        class="w-full flex items-center justify-between p-2.5 rounded-xl bg-slate-800/80 hover:bg-slate-800 border border-slate-700/80 transition-all group cursor-pointer text-left"
                    >
                        <div class="flex items-center gap-3 overflow-hidden">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-blue-500 to-blue-400 text-white font-black flex items-center justify-center text-xs shadow-md uppercase flex-shrink-0">
                                {{ strtoupper(substr($clubName, 0, 2)) }}
                            </div>
                            <div class="text-left overflow-hidden">
                                <div class="text-xs font-bold text-white truncate group-hover:text-blue-300 transition-colors">{{ $clubName }}</div>
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    <span class="px-1.5 py-0.2 rounded text-[9px] font-extrabold uppercase bg-blue-500/20 text-blue-300 border border-blue-500/30">
                                        {{ $currentClubRole }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <svg class="w-4 h-4 text-slate-400 group-hover:text-white transition-all transform" :class="{ 'rotate-180': switcherOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <!-- Dropdown Backdrop -->
                    <div v-show="switcherOpen" x-show="switcherOpen" @click="switcherOpen = false" class="fixed inset-0 z-40 bg-transparent" style="display: none;"></div>

                    <!-- Switcher Dropdown Menu -->
                    <div
                        v-show="switcherOpen"
                        x-show="switcherOpen"
                        @click.away="switcherOpen = false"
                        class="absolute top-full left-4 right-4 mt-2 bg-slate-900 border border-slate-700/90 rounded-2xl shadow-2xl z-50 p-2 space-y-1 backdrop-blur-md"
                        style="display: none;"
                    >
                        <div class="px-3 py-1.5 text-[10px] font-extrabold uppercase tracking-wider text-slate-400 border-b border-slate-800">
                            Switch Workspace ({{ count($userClubs) ?: 1 }})
                        </div>

                        <div class="max-h-60 overflow-y-auto space-y-1">
                            @foreach($userClubs as $c)
                                <a
                                    href="{{ route('admin.analytics', ['slug' => $c->slug]) }}"
                                    class="w-full flex items-center justify-between p-2.5 rounded-xl text-left transition-all text-xs {{ $c->slug === $clubSlug ? 'bg-blue-600/20 border border-blue-500/40 text-white font-bold' : 'hover:bg-slate-800 text-slate-300' }}"
                                >
                                    <div class="flex items-center gap-2.5 overflow-hidden">
                                        <div class="w-6 h-6 rounded-md bg-slate-800 border border-slate-700 font-bold text-[10px] text-blue-400 flex items-center justify-center uppercase">
                                            {{ strtoupper(substr($c->name, 0, 2)) }}
                                        </div>
                                        <div class="truncate">
                                            <div class="truncate font-semibold">{{ $c->name }}</div>
                                            <div class="text-[10px] text-slate-400 capitalize">{{ $c->pivot->role ?? 'member' }}</div>
                                        </div>
                                    </div>
                                    @if($c->slug === $clubSlug)
                                        <span class="text-blue-400 text-xs">✓</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>

                        <div class="pt-2 border-t border-slate-800 space-y-1">
                            <a href="{{ route('admin.clubs.index') }}" class="block w-full text-center py-2 px-3 bg-slate-800 hover:bg-slate-700 text-xs font-bold text-blue-300 rounded-xl transition-all">
                                + View All Joined Clubs
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Navigation Links -->
                <nav class="p-3 space-y-0.5 text-sm font-medium">
                    <!-- 1. Dashboard -->
                    <a
                        href="{{ route('admin.analytics', ['slug' => $clubSlug]) }}"
                        class="flex items-center gap-3 px-3.5 py-2 rounded-xl transition-all {{ request()->routeIs('admin.analytics') ? 'bg-slate-800 text-white font-bold shadow-sm' : 'hover:bg-slate-800 hover:text-white text-slate-300' }}"
                    >
                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                        </svg>
                        <span>Dashboard</span>
                    </a>

                    <!-- 2. Meetings & Governance -->
                    <a
                        href="{{ route('admin.meetings.index', ['clubSlug' => $clubSlug]) }}"
                        class="flex items-center gap-3 px-3.5 py-2 rounded-xl transition-all {{ request()->routeIs('admin.meetings.*', 'admin.committee.*') ? 'bg-slate-800 text-white font-bold shadow-sm' : 'hover:bg-slate-800 hover:text-white text-slate-300' }}"
                    >
                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span>Meetings</span>
                    </a>

                    <!-- 3. Events -->
                    <a
                        href="{{ route('admin.events.index', ['clubSlug' => $clubSlug]) }}"
                        class="flex items-center gap-3 px-3.5 py-2 rounded-xl transition-all {{ request()->routeIs('admin.events.*') ? 'bg-slate-800 text-white font-bold shadow-sm' : 'hover:bg-slate-800 hover:text-white text-slate-300' }}"
                    >
                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                        </svg>
                        <span>Events</span>
                    </a>

                    <!-- 5. Members Hub -->
                    <a
                        href="{{ route('admin.club_acc.members.index', ['clubSlug' => $clubSlug]) }}"
                        class="flex items-center gap-3 px-3.5 py-2 rounded-xl transition-all {{ request()->routeIs('admin.club_acc.members.*', 'admin.club_acc.candidates.*', 'admin.club_acc.subscriptions.*', 'admin.users.*', 'admin.officers.*') ? 'bg-slate-800 text-white font-bold shadow-sm' : 'hover:bg-slate-800 hover:text-white text-slate-300' }}"
                    >
                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span>Members</span>
                    </a>

                    <!-- 6. Communications -->
                    <a
                        href="{{ route('admin.posts.index', ['clubSlug' => $clubSlug]) }}"
                        class="flex items-center gap-3 px-3.5 py-2 rounded-xl transition-all {{ request()->routeIs('admin.posts.*', 'admin.newsletters.*', 'admin.updates.*') ? 'bg-slate-800 text-white font-bold shadow-sm' : 'hover:bg-slate-800 hover:text-white text-slate-300' }}"
                    >
                        <svg class="w-5 h-5 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                        </svg>
                        <span>Communications</span>
                    </a>

                    <!-- 8. Website Builder -->
                    <a
                        href="{{ route('admin.pages.index', ['clubSlug' => $clubSlug]) }}"
                        class="flex items-center gap-3 px-3.5 py-2 rounded-xl transition-all {{ request()->routeIs('admin.pages.*') ? 'bg-slate-800 text-white font-bold shadow-sm' : 'hover:bg-slate-800 hover:text-white text-slate-300' }}"
                    >
                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span>Website Builder</span>
                    </a>

                    <!-- 9. File Manager -->
                    <a
                        href="{{ route('admin.media.page', ['clubSlug' => $clubSlug]) }}"
                        class="flex items-center gap-3 px-3.5 py-2 rounded-xl transition-all {{ request()->routeIs('admin.media.*') ? 'bg-slate-800 text-white font-bold shadow-sm' : 'hover:bg-slate-800 hover:text-white text-slate-300' }}"
                    >
                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                        </svg>
                        <span>File Manager</span>
                    </a>

                    <!-- 10. Accounting -->
                    <a
                        href="{{ route('admin.accounting.index', ['clubSlug' => $clubSlug]) }}"
                        class="flex items-center gap-3 px-3.5 py-2 rounded-xl transition-all {{ request()->routeIs('admin.accounting.*') ? 'bg-slate-800 text-white font-bold shadow-sm' : 'hover:bg-slate-800 hover:text-white text-slate-300' }}"
                    >
                        <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        <span>Accounting</span>
                    </a>

                    <!-- 11. Charity Steward & Festival -->
                    <a
                        href="{{ route('admin.club_acc.charity.index', ['clubSlug' => $clubSlug]) }}"
                        class="flex items-center gap-3 px-3.5 py-2 rounded-xl transition-all {{ request()->routeIs('admin.club_acc.charity.*') ? 'bg-slate-800 text-white font-bold shadow-sm' : 'hover:bg-slate-800 hover:text-white text-slate-300' }}"
                    >
                        <span class="text-lg">🤝</span>
                        <span>Charity</span>
                    </a>

                    <!-- 12. Club Settings -->
                    <a
                        href="{{ route('admin.settings.show', ['clubSlug' => $clubSlug]) }}"
                        class="flex items-center gap-3 px-3.5 py-2 rounded-xl transition-all {{ request()->routeIs('admin.settings.*', 'admin.profile.*') ? 'bg-slate-800 text-white font-bold shadow-sm' : 'hover:bg-slate-800 hover:text-white text-slate-300' }}"
                    >
                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span>Club Settings</span>
                    </a>
                </nav>
            </div>

            <div
                class="p-3 border-t border-slate-800"
                x-data="{
                    dark: document.documentElement.classList.contains('dark'),
                    toggle() {
                        this.dark = ! this.dark;
                        document.documentElement.classList.toggle('dark', this.dark);
                        try { localStorage.setItem('superadmin-theme', this.dark ? 'dark' : 'light'); } catch (e) {}
                    },
                }"
            >
                <button
                    type="button"
                    role="switch"
                    :aria-checked="dark"
                    @click="toggle()"
                    class="group flex w-full items-center justify-between gap-3 px-3.5 py-2.5 rounded-xl transition-all text-sm font-medium text-slate-300 hover:text-white hover:bg-slate-800 cursor-pointer"
                >
                    <span class="flex items-center gap-3">
                        <svg x-show="dark" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                        </svg>
                        <svg x-show="! dark" x-cloak class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <span x-text="dark ? 'Dark mode' : 'Light mode'">Light mode</span>
                    </span>

                    <span
                        :class="dark ? 'bg-blue-600' : 'bg-slate-600 group-hover:bg-slate-500'"
                        class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors duration-200"
                    >
                        <span
                            :class="dark ? 'translate-x-[1.125rem]' : 'translate-x-[0.1875rem]'"
                            class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow-sm transition-transform duration-200"
                        ></span>
                    </span>
                </button>
            </div>
        </aside>

        <!-- Main Right Content Area -->
        <main class="flex-1 overflow-y-auto flex flex-col min-w-0">
            <!-- Top Title Bar -->
            <header class="bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 px-6 md:px-10 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-sm">
                <div>
                    <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">{{ $pageTitle }}</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mt-0.5">Managing {{ $clubName }}</p>
                </div>

                <!-- Quick Actions & User Profile -->
                <div class="flex items-center gap-3">
                    @if($currentClub)
                        <a href="{{ route('member.dashboard', $clubSlug) }}" class="hidden sm:flex px-3.5 py-2 bg-emerald-50 dark:bg-emerald-950/40 hover:bg-emerald-100 dark:hover:bg-emerald-900/40 border border-emerald-300 dark:border-emerald-700/60 text-emerald-700 dark:text-emerald-300 text-xs font-bold rounded-xl transition-all items-center gap-1.5 shadow-sm">
                            <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                            </svg>
                            <span>Switch to Member Portal</span>
                        </a>
                    @endif

                    @php $unreadNotifications = $user ? $user->unreadNotifications()->count() : 0; @endphp
                    <a href="{{ route('members.notifications') }}" class="relative flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" aria-label="{{ $unreadNotifications ? 'Notifications, '.$unreadNotifications.' unread' : 'Notifications' }}">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 00-4-5.7V5a2 2 0 10-4 0v.3A6 6 0 006 11v3.2a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 11-6 0" /></svg>
                        @if($unreadNotifications)
                            <span class="absolute -right-1 -top-1 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-bold text-white">{{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}</span>
                        @endif
                    </a>

                    <!-- Top-Right User Profile Avatar & Dropdown -->
                    <div class="relative">
                        <div v-show="userMenuOpen" x-show="userMenuOpen" @click="userMenuOpen = false" class="fixed inset-0 z-40 bg-transparent" style="display: none;"></div>

                        <button
                            type="button"
                            @click="userMenuOpen = !userMenuOpen"
                            class="flex items-center gap-2 p-1.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800 transition-all group focus:outline-none cursor-pointer"
                        >
                            <div class="w-9 h-9 rounded-full overflow-hidden border-2 border-blue-600 shadow-sm bg-gradient-to-tr from-blue-600 to-blue-500 text-white font-black text-xs flex items-center justify-center">
                                @if($user?->avatar_url)
                                    <img src="{{ $user->avatar_url }}" class="w-full h-full object-cover" />
                                @else
                                    <span>{{ strtoupper(substr($user?->name ?? 'ME', 0, 2)) }}</span>
                                @endif
                            </div>
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-200 group-hover:text-blue-600 dark:group-hover:text-blue-400 hidden sm:inline-block max-w-[120px] truncate">
                                {{ $user?->name ?? 'Account' }}
                            </span>
                            <svg class="w-4 h-4 text-slate-400 group-hover:text-slate-600 dark:group-hover:text-slate-300 transition-transform" :class="{ 'rotate-180': userMenuOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <!-- User Menu Dropdown -->
                        <div
                            v-show="userMenuOpen"
                            x-show="userMenuOpen"
                            @click.away="userMenuOpen = false"
                            class="absolute right-0 mt-2 w-64 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xl z-50 p-2 space-y-1 text-slate-700 dark:text-slate-200"
                            style="display: none;"
                        >
                            <!-- Header User Info -->
                            <div class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-800">
                                <div class="text-xs font-black text-slate-900 dark:text-white truncate">{{ $user?->name ?? 'Administrator' }}</div>
                                <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate">{{ $user?->email ?? '' }}</div>
                                <div class="mt-2 flex items-center gap-1.5">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200 border border-blue-200 dark:border-blue-800/60">
                                        {{ $currentClubRole }}
                                    </span>
                                </div>
                            </div>

                            <!-- Global Account Links -->
                            <div class="py-1 space-y-1">
                                <div class="px-3 py-1 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
                                    Global Account
                                </div>

                                <a href="{{ route('members.dashboard') }}" class="flex items-center gap-2.5 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-blue-50 dark:hover:bg-blue-950/40 hover:text-blue-600 dark:hover:text-blue-400 rounded-xl transition-all">
                                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                    </svg>
                                    <span>Home</span>
                                </a>

                                <a href="{{ route('profile.edit') }}" class="flex items-center gap-2.5 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-blue-50 dark:hover:bg-blue-950/40 hover:text-blue-600 dark:hover:text-blue-400 rounded-xl transition-all">
                                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    <span>Global Account & Photo</span>
                                </a>

                                <a href="{{ route('admin.profile.two-factor') }}" class="flex items-center gap-2.5 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-blue-50 dark:hover:bg-blue-950/40 hover:text-blue-600 dark:hover:text-blue-400 rounded-xl transition-all">
                                    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                    <span>Password & 2FA Security</span>
                                </a>
                            </div>

                            <!-- Active Club Links -->
                            <div class="py-1 border-t border-slate-100 dark:border-slate-800 space-y-1">
                                <div class="px-3 py-1 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
                                    Active Club Context
                                </div>

                                <a href="{{ route('member.profile', ['slug' => $clubSlug]) }}" class="flex items-center gap-2.5 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-blue-50 dark:hover:bg-blue-950/40 hover:text-blue-600 dark:hover:text-blue-400 rounded-xl transition-all">
                                    <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span>My Club Membership</span>
                                </a>

                                <a href="{{ route('admin.settings.show', ['clubSlug' => $clubSlug]) }}" class="flex items-center gap-2.5 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-blue-50 dark:hover:bg-blue-950/40 hover:text-blue-600 dark:hover:text-blue-400 rounded-xl transition-all">
                                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span>Club Settings</span>
                                </a>

                                <a href="{{ route('member.dashboard', $clubSlug) }}" class="flex items-center gap-2.5 px-3 py-1.5 text-xs font-semibold text-emerald-700 dark:text-emerald-300 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 rounded-xl transition-all">
                                    <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                    </svg>
                                    <span>Switch to Member Portal</span>
                                </a>
                            </div>

                            <!-- Logout -->
                            <div class="pt-1 border-t border-slate-100 dark:border-slate-800">
                                <form method="POST" action="/logout" class="block">
                                    @csrf
                                    <button type="submit" class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-xl transition-all text-left cursor-pointer">
                                        <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                        </svg>
                                        <span>Log Out</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Slot Content -->
            <div class="p-6 md:p-10 max-w-7xl 2xl:max-w-[1536px] w-full mx-auto space-y-8 flex-1">
                @if (session()->has('success'))
                    <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 text-emerald-800 dark:text-emerald-200 text-xs font-bold flex items-center gap-2 shadow-sm">
                        <span>✅</span>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                @if (session()->has('error'))
                    <div class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 text-rose-800 dark:text-rose-200 text-xs font-bold flex items-center gap-2 shadow-sm">
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
