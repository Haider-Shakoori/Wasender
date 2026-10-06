<x-layouts.guest title="Create workspace">
    <p class="eyebrow">Get started</p>
    <h1 class="mt-2 text-2xl font-semibold tracking-tight">Create your workspace</h1>
    <p class="muted mt-2">A secure tenant isolated from every other organization.</p>

    <form class="mt-7 space-y-5" method="post" action="{{ route('register.store') }}">
        @csrf

        @error('registration')
            <p class="rounded-xl bg-rose-500/10 p-3 text-sm text-[var(--danger)]">{{ $message }}</p>
        @enderror

        <x-input label="Full name" name="name" required autofocus autocomplete="name" />
        <x-input label="Company name" name="company" required autocomplete="organization" />
        <x-input label="Email address" name="email" type="email" required autocomplete="email" />
        <x-input label="Password" name="password" type="password" hint="Use at least 12 characters with mixed case and a number." required autocomplete="new-password" />
        <x-input label="Confirm password" name="password_confirmation" type="password" required autocomplete="new-password" />

        <label class="flex items-start gap-3 text-sm leading-6 text-[var(--text-secondary)]">
            <input class="mt-1 size-4 accent-[var(--accent)]" name="terms" type="checkbox" value="1" {{ old('terms') ? 'checked' : '' }} required>
            <span>I agree to the Terms of Service and responsible-messaging policy.</span>
        </label>
        @error('terms')
            <p class="text-sm text-[var(--danger)]">{{ $message }}</p>
        @enderror

        <x-button class="w-full" type="submit" loading-label="Creating workspace…">Create workspace</x-button>
    </form>

    <p class="mt-6 text-center text-sm text-[var(--text-muted)]">Already registered? <a class="font-semibold text-[var(--accent)] hover:underline" href="{{ route('login') }}">Sign in</a></p>
</x-layouts.guest>
