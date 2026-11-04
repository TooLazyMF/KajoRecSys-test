@php
    $systemTheme = request()->routeIs('dashboard', 'student.dashboard', 'teacher.dashboard', 'teacher.students', 'guidance.dashboard', 'guidance.records.*', 'guidance.cif.*', 'admin.users.*', 'slip-history.*', 'assigned-slips.*');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @if ($systemTheme) data-system-theme @else class="dark" @endif>
    <head>
        @if ($systemTheme)
            <script>
                const colorScheme = window.matchMedia('(prefers-color-scheme: dark)');
                const applySystemTheme = () => document.documentElement.classList.toggle('dark', colorScheme.matches);

                applySystemTheme();
                colorScheme.addEventListener('change', applySystemTheme);
            </script>
        @endif
        @include('partials.head')
        <meta name="csrf-token" content="{{ csrf_token() }}">
    </head>
    <body class="min-h-screen {{ $systemTheme ? 'bg-[#f6f5f1] dark:bg-[#111712]' : 'bg-white dark:bg-zinc-800' }}">
        @php
            $role = auth()->user()->role;
            $dashboardRoute = match ($role) {
                'teacher' => 'teacher.dashboard',
                'student' => 'student.dashboard',
                'guidance_counselor' => 'guidance.dashboard',
                default => 'dashboard',
            };
        @endphp

        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" :href="route($dashboardRoute)" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Workspace')" class="grid">
                    <flux:sidebar.item
                        icon="home"
                        :href="route($dashboardRoute)"
                        :current="request()->routeIs($dashboardRoute)"
                        wire:navigate
                    >
                        {{ match ($role) {
                            'teacher' => __('Teacher Dashboard'),
                            'student' => __('Student Dashboard'),
                            'guidance_counselor' => __('Guidance Dashboard'),
                            default => __('Dashboard'),
                        } }}
                    </flux:sidebar.item>

                    @if ($role === 'teacher')
                        <flux:sidebar.item icon="paper-airplane" :href="route('teacher.referrals-sent')" :current="request()->routeIs('teacher.referrals-sent')" wire:navigate>
                            {{ __('Referral I sent') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            icon="user-plus"
                            :href="route('slips.fillup', 'student-referral-slip')"
                            :current="request()->routeIs('slips.fillup')"
                            wire:navigate
                        >
                            {{ __('Create Student Referral') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            icon="users"
                            :href="route('teacher.referral-history')"
                            :current="request()->routeIs('teacher.referral-history')"
                            wire:navigate
                        >
                            {{ __('Referral History') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            icon="users"
                            :href="route('teacher.students')"
                            :current="request()->routeIs('teacher.students')"
                            wire:navigate
                        >
                            {{ __('Students') }}
                        </flux:sidebar.item>
                    @elseif ($role === 'student')
                        <flux:sidebar.item
                            icon="calendar"
                            :href="route('appointments.index')"
                            :current="request()->routeIs('appointments.*')"
                            wire:navigate
                        >
                            {{ __('Appointments') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            icon="clipboard-document-list"
                            :href="route('assigned-slips.index')"
                            :current="request()->routeIs('assigned-slips.*')"
                            wire:navigate
                        >
                            {{ __('Assigned Slips') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            icon="document-plus"
                            :href="route('student.dashboard').'#request-forms'"
                            :current="false"
                        >
                            {{ __('Request a Form') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            icon="clock"
                            :href="route('slip-history.index')"
                            :current="request()->routeIs('slip-history.*')"
                            wire:navigate
                        >
                            {{ __('Slip History') }}
                        </flux:sidebar.item>
                    @elseif ($role === 'guidance_counselor')
                        <flux:sidebar.item
                            icon="calendar"
                            :href="route('appointments.index')"
                            :current="request()->routeIs('appointments.*')"
                            wire:navigate
                        >
                            {{ __('Appointment Requests') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            icon="clipboard-document-check"
                            :href="route('guidance.slip-review')"
                            :current="request()->routeIs('guidance.slip-review')"
                            wire:navigate
                        >
                            {{ __('Review Submissions') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            icon="inbox"
                            :href="route('guidance.referrals')"
                            :current="false"
                        >
                            {{ __('Received Referrals') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            icon="document-text"
                            :href="route('guidance.cif.index')"
                            :current="request()->routeIs('guidance.cif.*')"
                            wire:navigate
                        >
                            {{ __('Student CIF') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            icon="archive-box"
                            :href="route('guidance.records.index', 'all')"
                            :current="request()->routeIs('guidance.records.*')"
                            wire:navigate
                        >
                            {{ __('Appointment & Slip History') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            icon="users"
                            :href="route('admin.users.index')"
                            :current="request()->routeIs('admin.users.*')"
                            wire:navigate
                        >
                            {{ __('Manage Users') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            icon="arrow-up-tray"
                            :href="route('guidance.dashboard').'#student-import'"
                            :current="false"
                        >
                            {{ __('Import Students File') }}
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
            <flux:spacer />
            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />
                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                        {{ __('Settings') }}
                    </flux:menu.item>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        <script>
            (() => {
                const idleTimeout = 10 * 60 * 1000;
                const activityUrl = @json(route('account-activity.touch'));
                const logoutUrl = @json(route('logout'));
                const loginUrl = @json(route('login'));
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                let lastActivity = Date.now();
                let lastSync = 0;
                let logoutTimer;

                const syncActivity = () => {
                    if (!csrfToken || Date.now() - lastSync < 60000) {
                        return;
                    }

                    lastSync = Date.now();
                    fetch(activityUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                        keepalive: true,
                    }).then((response) => {
                        if (!response.ok) {
                            throw new Error(`Account activity update failed (${response.status}).`);
                        }
                    }).catch((error) => {
                        console.error('Unable to update account activity.', error);
                        lastSync = Date.now() - 60000;
                    });
                };

                const logOutWhenIdle = () => {
                    const remaining = idleTimeout - (Date.now() - lastActivity);
                    if (remaining > 0) {
                        logoutTimer = window.setTimeout(logOutWhenIdle, remaining);
                        return;
                    }

                    const formData = new FormData();
                    if (csrfToken) {
                        formData.append('_token', csrfToken);
                    }

                    fetch(logoutUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json' },
                        body: formData,
                        keepalive: true,
                    }).then((response) => {
                        if (!response.ok) {
                            throw new Error(`Automatic logout failed (${response.status}).`);
                        }
                    }).catch((error) => {
                        console.error('Unable to complete automatic logout.', error);
                    }).finally(() => window.location.assign(loginUrl));
                };

                const registerActivity = () => {
                    lastActivity = Date.now();
                    window.clearTimeout(logoutTimer);
                    logoutTimer = window.setTimeout(logOutWhenIdle, idleTimeout);
                    syncActivity();
                };

                ['pointerdown', 'pointermove', 'keydown', 'touchstart', 'wheel', 'click'].forEach((eventName) => {
                    window.addEventListener(eventName, registerActivity, { passive: true });
                });

                logoutTimer = window.setTimeout(logOutWhenIdle, idleTimeout);
            })();
        </script>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
