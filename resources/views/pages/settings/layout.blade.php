<div
    class="flex items-start max-md:flex-col"
    x-data="{
        securityConfirmationError: null,
        async prepareSecurityConfirmation() {
            this.securityConfirmationError = null;

            try {
                const response = await fetch('{{ route('security.confirmation.prepare') }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                });

                if (!response.ok) {
                    throw new Error('Unable to open security confirmation. Please try again.');
                }

                window.Flux.modal('security-confirmation').show();
            } catch (error) {
                this.securityConfirmationError = error.message;
            }
        },
    }"
>
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <flux:navlist aria-label="{{ __('Settings') }}">
            <flux:navlist.item :href="route('profile.edit')" wire:navigate>{{ __('Profile') }}</flux:navlist.item>
            <flux:navlist.item
                :href="route('security.edit')"
                x-on:click.prevent="prepareSecurityConfirmation()"
            >
                {{ __('Security') }}
            </flux:navlist.item>
            <flux:navlist.item :href="route('appearance.edit')" wire:navigate>{{ __('Appearance') }}</flux:navlist.item>
        </flux:navlist>

        <p
            x-show="securityConfirmationError"
            x-text="securityConfirmationError"
            role="alert"
            class="mt-2 text-sm text-red-600 dark:text-red-400"
        ></p>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <flux:heading>{{ $heading ?? '' }}</flux:heading>
        <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>

        <div class="mt-5 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>

    <flux:modal
        name="security-confirmation"
        :show="$errors->has('password')"
        focusable
        class="max-w-md"
    >
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Confirm your identity') }}</flux:heading>

            {{-- @chisel-passkeys --}}
            <x-passkey-verify
                options-route="passkey.confirm-options"
                submit-route="passkey.confirm"
                :label="__('Confirm with passkey')"
                :loading-label="__('Confirming...')"
                :separator="__('Or confirm with password')"
            />
            {{-- @end-chisel-passkeys --}}

            <form method="POST" action="{{ route('password.confirm.store') }}" class="space-y-4">
                @csrf

                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                    viewable
                    autofocus
                />

                @if ($errors->has('password'))
                    <p role="alert" class="text-sm text-red-600 dark:text-red-400">
                        {{ $errors->first('password') }}
                    </p>
                @endif

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" type="submit" data-test="confirm-password-button">
                        {{ __('Confirm') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
