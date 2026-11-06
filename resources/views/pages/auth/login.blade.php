<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Log in to your account')" :description="__('Enter your email and password below to log in')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        @if ($errors->any())
            <div role="alert" class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- @chisel-passkeys --}}
        <x-passkey-verify />
        {{-- @end-chisel-passkeys --}}

        <form id="login-form" method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf
            <input type="hidden" name="login_latitude" id="login-latitude">
            <input type="hidden" name="login_longitude" id="login-longitude">

            <!-- Email Address -->
            <flux:input
                id="login-email"
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    id="login-password"
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

            <label class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                <input
                    id="share-login-location"
                    name="share_location"
                    type="checkbox"
                    value="1"
                    class="mt-1 rounded border-zinc-300 text-emerald-700 focus:ring-emerald-700 dark:border-zinc-600 dark:bg-zinc-800"
                >
                <span>Share this device’s location in my account logs. Your browser will ask permission; coordinates stay in this system and are never sent to a location provider.</span>
            </label>

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    {{ __('Log in') }}
                </flux:button>
            </div>
        </form>

        {{-- @chisel-registration --}}
        <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Don\'t have an account?') }}</span>
            <flux:link :href="route('register')" wire:navigate>{{ __('Sign up') }}</flux:link>
        </div>
        {{-- @end-chisel-registration --}}
        <section aria-labelledby="login-contact-heading" class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/50 dark:text-zinc-300">
            <h2 id="login-contact-heading" class="font-semibold text-zinc-900 dark:text-zinc-100">Contact Us</h2>
            <p class="mt-1">We are the researchers behind KajoRec. For account or login assistance, email us at <a class="font-medium text-emerald-700 underline hover:text-emerald-800 dark:text-emerald-300 dark:hover:text-emerald-200" href="mailto:kajorec@gmail.com">kajorec@gmail.com</a>.</p>
            <p class="mt-1">Support hours: 8:00 AM–3:00 PM.</p>
        </section>
    </div>

    <script>
        document.getElementById('login-form')?.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' || event.isComposing || event.shiftKey || event.ctrlKey || event.altKey || event.metaKey) {
                return;
            }

            const email = document.getElementById('login-email');
            const password = document.getElementById('login-password');

            if (email && !email.value.trim()) {
                event.preventDefault();
                email.focus();
            } else if (password && !password.value) {
                event.preventDefault();
                password.focus();
            }
        });

        const loginForm = document.getElementById('login-form');
        const shareLocation = document.getElementById('share-login-location');
        let locationSubmissionStarted = false;
        let loginSubmitted = false;

        loginForm?.addEventListener('submit', (event) => {
            if (!shareLocation?.checked || locationSubmissionStarted) {
                return;
            }

            event.preventDefault();
            locationSubmissionStarted = true;

            const submitLogin = () => {
                if (loginSubmitted) {
                    return;
                }

                loginSubmitted = true;
                HTMLFormElement.prototype.submit.call(loginForm);
            };
            const submitWithLocation = (position) => {
                document.getElementById('login-latitude').value = position.coords.latitude;
                document.getElementById('login-longitude').value = position.coords.longitude;
                submitLogin();
            };

            if (!navigator.geolocation) {
                submitLogin();
                return;
            }

            navigator.geolocation.getCurrentPosition(submitWithLocation, submitLogin, {
                enableHighAccuracy: false,
                maximumAge: 60000,
                timeout: 5000,
            });
            window.setTimeout(submitLogin, 5500);
        });
    </script>
</x-layouts::auth>
