<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#09ba44" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#20783e" media="(prefers-color-scheme: dark)">
    <title>KAJOREC | Welcome</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            color-scheme: light dark;
            font-family: Arial, Helvetica, sans-serif;
            color: #292a25;
            background: #f6f5f1;
            --page-bg: #f6f5f1;
            --surface: #fff;
            --text: #292a25;
            --heading: #30392c;
            --muted: #777970;
            --label: #55574f;
            --border: #e1e3dc;
            --field-bg: #fff;
            --placeholder: #a0a198;
            --primary: #09ba44;
            --primary-hover: #079b38;
            --on-primary: #092010;
            --primary-soft: #dff7e6;
            --primary-ink: #28643a;
            --focus-ring: rgba(9, 186, 68, 0.18);
            --welcome-top: #3f5945;
            --welcome-copy: #394d3b;
            --welcome-muted: #485b48;
            --welcome-gradient:
                radial-gradient(ellipse at 84% 14%, rgba(226, 244, 218, 0.95), transparent 32%),
                linear-gradient(145deg, #e0ebdc 0%, #cfe0cb 53%, #b8d0b5 100%);
            --welcome-rings: rgba(255, 255, 255, 0.65);
            font-synthesis: none;
            text-rendering: optimizeLegibility;
            -webkit-font-smoothing: antialiased;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                color-scheme: dark;
                color: #e8f0e9;
                background: #111712;
                --page-bg: #111712;
                --surface: #19231b;
                --text: #e8f0e9;
                --heading: #f2f8f3;
                --muted: #b3c1b5;
                --label: #d3dfd5;
                --border: #3d4d40;
                --field-bg: #111a13;
                --placeholder: #9aaa9c;
                --primary: #20783e;
                --primary-hover: #28894a;
                --on-primary: #fff;
                --primary-soft: #243d2b;
                --primary-ink: #91dfa6;
                --focus-ring: rgba(76, 185, 105, 0.24);
                --welcome-top: #d1e2d3;
                --welcome-copy: #f0f7f0;
                --welcome-muted: #d0dfd1;
                --welcome-gradient:
                    radial-gradient(ellipse at 84% 14%, rgba(50, 94, 59, 0.72), transparent 34%),
                    linear-gradient(145deg, #263d2c 0%, #1e3324 53%, #17271c 100%);
                --welcome-rings: rgba(220, 241, 223, 0.2);
            }
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-width: 320px;
            min-height: 100vh;
            margin: 0;
            display: flex;
            flex-direction: column;
            background: var(--page-bg);
            color: var(--text);
        }

        body.welcome-shell > header {
            position: sticky;
            top: 0;
            z-index: 5;
            width: 100%;
            border-color: var(--border);
            background-color: var(--primary);
            color: var(--on-primary);
        }

        body.welcome-shell > header > div {
            min-height: 84px;
        }

        body.welcome-shell > header img {
            width: 68px !important;
            height: 68px !important;
        }

        body.welcome-shell > header nav a,
        body.welcome-shell > header span.whitespace-nowrap {
            color: var(--on-primary);
        }

        body.welcome-shell > header nav a:hover {
            color: var(--surface);
        }

        body.welcome-shell > header a.rounded-lg {
            border-color: var(--surface);
            background-color: var(--surface);
            color: var(--primary-ink);
        }

        body.welcome-shell > header a.rounded-lg.border {
            border-color: rgba(255, 255, 255, 0.7);
            background-color: transparent;
            color: var(--on-primary);
        }

        body.welcome-shell > header a.rounded-lg.border:hover {
            background-color: var(--surface);
            color: var(--primary-ink);
        }

        .auth-area {
            display: grid;
            flex: 1;
            place-items: center;
            width: 100%;
            padding: 8px 32px;
        }

        button,
        input,
        select {
            font: inherit;
        }

        .page {
            position: relative;
            display: grid;
            grid-template-columns: 1fr 1fr;
            width: min(1180px, 100%);
            min-height: min(560px, calc(100dvh - 105px));
            overflow: hidden;
            background: var(--surface);
            border: 1px solid rgba(41, 42, 37, 0.07);
            border-radius: 28px;
            box-shadow: 0 28px 80px rgba(46, 48, 39, 0.1);
        }

        @media (prefers-color-scheme: dark) {
            .page {
                border-color: rgba(220, 241, 223, 0.1);
                box-shadow: 0 28px 80px rgba(0, 0, 0, 0.32);
            }
        }

        .form-panel {
            position: relative;
            z-index: 2;
            left: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 20px clamp(32px, 4vw, 52px);
            transition: left 700ms cubic-bezier(0.22, 1, 0.36, 1);
        }

        .page[data-mode="register"] .form-panel {
            left: 100%;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 11px;
            margin-bottom: 16px;
            color: var(--heading);
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .brand-mark {
            display: grid;
            width: 34px;
            height: 34px;
            place-items: center;
            border-radius: 50%;
            background: var(--primary-soft);
            color: var(--primary-ink);
            font-family: Georgia, "Times New Roman", serif;
            font-size: 20px;
            letter-spacing: 0;
        }

        h1,
        h2,
        p {
            margin-top: 0;
        }

        .form-heading {
            margin-bottom: 9px;
            color: var(--heading);
            font-family: Georgia, "Times New Roman", serif;
            font-size: clamp(34px, 4vw, 42px);
            font-weight: 500;
            letter-spacing: -0.04em;
        }

        .form-intro {
            margin-bottom: 28px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.7;
        }

        .session-status {
            margin-bottom: 18px;
            color: var(--primary-ink);
            font-size: 14px;
        }

        .auth-form[hidden] {
            display: none;
        }

        .form-fields {
            display: grid;
            gap: 16px;
        }

        [data-auth-view="register"] .form-fields {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }

        [data-auth-view="register"] .name-fields,
        [data-auth-view="register"] .field[for="register-id-number"],
        [data-auth-view="register"] .submit-button {
            grid-column: 1 / -1;
        }

        [data-auth-view="register"] .form-intro {
            margin-bottom: 6px;
        }

        [data-auth-view="register"] .field {
            gap: 5px;
        }

        [data-auth-view="register"] .field input,
        [data-auth-view="register"] .field select {
            min-height: 42px;
        }

        [data-auth-view="register"] .switch-copy {
            margin-top: 8px;
        }

        .name-fields {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        @media (min-width: 1000px) {
            [data-auth-view="register"] .name-fields {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        @media (max-width: 520px) {
            .name-fields { grid-template-columns: 1fr; }
        }

        .field {
            display: grid;
            gap: 8px;
            color: var(--label);
            font-size: 12px;
            font-weight: 600;
        }

        .field input,
        .field select {
            width: 100%;
            min-height: 48px;
            padding: 0 14px;
            border: 1px solid var(--border);
            border-radius: 10px;
            outline: none;
            background: var(--field-bg);
            color: var(--text);
            font-size: 14px;
            transition: border-color 160ms ease, box-shadow 160ms ease;
        }

        .field input::placeholder {
            color: var(--placeholder);
        }

        .field input:focus,
        .field select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--focus-ring);
        }

        .password-input-wrap {
            position: relative;
        }

        .password-input-wrap input {
            padding-right: 68px;
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 12px;
            padding: 4px 0;
            border: 0;
            background: transparent;
            color: var(--primary-ink);
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transform: translateY(-50%);
        }

        .password-toggle:focus-visible {
            border-radius: 3px;
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }

        .field-error {
            color: #b42318;
            font-size: 12px;
            font-weight: 400;
        }

        .form-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin: 2px 0 3px;
            color: var(--muted);
            font-size: 12px;
        }

        .remember {
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }

        .remember input {
            accent-color: var(--primary);
        }

        .text-button {
            padding: 0;
            border: 0;
            background: none;
            color: var(--primary-ink);
            cursor: pointer;
            font-size: inherit;
            font-weight: 600;
            text-decoration: none;
        }

        .text-button:hover {
            color: var(--primary-ink);
            text-decoration: underline;
        }

        .submit-button {
            min-height: 50px;
            margin-top: 3px;
            border: 0;
            border-radius: 10px;
            background: var(--primary);
            color: var(--on-primary);
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: background 160ms ease, transform 160ms ease;
        }

        .submit-button:hover {
            transform: translateY(-1px);
            background: var(--primary-hover);
        }

        .switch-copy {
            margin: 22px 0 0;
            color: var(--muted);
            text-align: center;
            font-size: 13px;
        }

        .switch-copy .text-button {
            margin-left: 4px;
        }

        .welcome-panel {
            position: relative;
            z-index: 1;
            left: 0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            padding: 48px clamp(32px, 5vw, 64px);
            background: var(--welcome-gradient);
            transition: left 700ms cubic-bezier(0.22, 1, 0.36, 1);
        }

        .page[data-mode="register"] .welcome-panel {
            left: -100%;
        }

        .welcome-panel::before,
        .welcome-panel::after {
            position: absolute;
            z-index: 0;
            border: 1px solid var(--welcome-rings);
            border-radius: 50%;
            content: "";
            pointer-events: none;
        }

        .welcome-panel::before {
            right: -175px;
            bottom: -220px;
            width: 590px;
            height: 590px;
        }

        .welcome-panel::after {
            right: -110px;
            bottom: -155px;
            width: 460px;
            height: 460px;
        }

        .welcome-top,
        .welcome-copy,
        .contact-content,
        .welcome-footer {
            position: relative;
            z-index: 1;
        }

        .welcome-copy,
        .contact-content {
            transition: opacity 240ms ease, transform 240ms ease, visibility 240ms;
        }

        .contact-content {
            visibility: hidden;
            opacity: 0;
            transform: translateY(12px);
        }

        .page[data-mode="contact"] .welcome-copy {
            visibility: hidden;
            opacity: 0;
            transform: translateY(-12px);
        }

        .page[data-mode="contact"] .contact-content {
            visibility: visible;
            opacity: 1;
            transform: translateY(-50%);
        }

        .contact-content {
            position: absolute;
            top: 50%;
            right: clamp(32px, 5vw, 64px);
            left: clamp(32px, 5vw, 64px);
            width: auto;
            margin: 0;
            transform: translateY(calc(-50% + 12px));
        }

        .contact-cards {
            display: grid;
            gap: 12px;
            margin-top: 28px;
        }

        .contact-card {
            padding: 16px 18px;
            border: 1px solid rgba(63, 89, 69, 0.18);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.34);
            color: var(--welcome-copy);
        }

        .contact-card-label {
            display: block;
            margin-bottom: 7px;
            color: var(--welcome-top);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .contact-card a {
            color: var(--primary-ink);
            font-size: 16px;
            font-weight: 700;
            text-decoration: none;
        }

        .contact-card a:hover {
            text-decoration: underline;
        }

        .contact-back {
            margin-top: 22px;
            padding: 0;
            border: 0;
            background: transparent;
            color: var(--primary-ink);
            cursor: pointer;
            font: inherit;
            font-size: 13px;
            font-weight: 700;
        }

        .contact-back:hover {
            text-decoration: underline;
        }

        .welcome-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            color: var(--welcome-top);
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .online-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-transform: none;
            letter-spacing: 0;
        }

        .online-indicator::before {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--primary);
            content: "";
        }

        .welcome-copy {
            max-width: 430px;
            margin: auto 0;
            padding: 56px 0 64px;
        }

        .eyebrow {
            margin-bottom: 19px;
            color: var(--welcome-top);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.17em;
            text-transform: uppercase;
        }

        .welcome-title {
            margin-bottom: 22px;
            color: var(--welcome-copy);
            font-family: Georgia, "Times New Roman", serif;
            font-size: clamp(45px, 6vw, 68px);
            font-weight: 500;
            letter-spacing: -0.055em;
            line-height: 1.04;
        }

        .welcome-title span {
            color: var(--primary-ink);
            font-style: italic;
        }

        .welcome-description {
            max-width: 340px;
            margin-bottom: 0;
            color: var(--welcome-muted);
            font-size: 14px;
            line-height: 1.8;
        }

        .welcome-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            color: var(--welcome-top);
            font-size: 10px;
        }

        .footer-line {
            width: 38px;
            height: 1px;
            flex: 0 0 auto;
            background: var(--primary);
        }

        @media (max-width: 760px) {
            .auth-area {
                padding: 16px 14px;
            }

            .page {
                grid-template-columns: 1fr;
                width: min(520px, 100%);
                min-height: auto;
                border-radius: 18px;
            }

            .form-panel {
                left: 0 !important;
                grid-row: 1;
                padding: 28px clamp(20px, 5vw, 36px) 32px;
                transition: none;
            }

            .brand {
                margin-bottom: 22px;
            }

            .welcome-panel {
                left: 0 !important;
                grid-row: 2;
                min-height: 220px;
                padding: 22px clamp(20px, 5vw, 36px);
                transition: none;
            }

            .page[data-mode="login"] .welcome-panel {
                grid-row: 1;
            }

            .page[data-mode="login"] .form-panel {
                grid-row: 2;
            }

            .welcome-copy {
                padding: 24px 0;
            }

            .welcome-title {
                max-width: 360px;
                margin-bottom: 14px;
                font-size: clamp(36px, 9vw, 48px);
            }

            .page[data-mode="register"] .form-panel {
                grid-row: 1;
            }

            .page[data-mode="contact"] {
                min-height: min(680px, calc(100dvh - 120px));
            }

            .page[data-mode="contact"] .form-panel {
                display: none;
            }

            .page[data-mode="contact"] .welcome-panel {
                grid-row: 1;
                min-height: min(680px, calc(100dvh - 120px));
                padding: 28px clamp(20px, 5vw, 36px);
            }

        }

        @media (max-width: 640px) {
            .form-intro {
                margin-bottom: 16px;
                line-height: 1.5;
            }

            .form-fields {
                gap: 12px;
            }

            .field input,
            .field select {
                min-height: 44px;
            }

            .submit-button {
                min-height: 46px;
            }

            .name-fields {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .switch-copy {
                margin-top: 16px;
            }

            body.welcome-shell > header > div {
                min-height: 72px;
                padding-right: 16px;
                padding-left: 16px;
            }

            body.welcome-shell > header img {
                width: 52px !important;
                height: 52px !important;
            }

            body.welcome-shell > header > div > div {
                gap: 8px;
            }

            body.welcome-shell > header > div {
                flex-wrap: wrap;
                padding-top: 8px;
                padding-bottom: 8px;
            }

            body.welcome-shell > header > div > div {
                width: 100%;
                margin-left: 0;
                justify-content: space-between;
            }

            body.welcome-shell > header nav {
                gap: 12px;
            }

            body.welcome-shell > header nav a {
                font-size: 12px;
            }

            body.welcome-shell > header .rounded-lg {
                padding: 8px 10px;
                font-size: 12px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body class="welcome-shell">
    <x-kajorec-header />

    <div class="auth-area">
        <main
            class="page"
            data-auth-page
            data-mode="{{ old('_welcome_panel') === 'register' ? 'register' : 'login' }}"
        >
            <section class="form-panel" aria-labelledby="form-heading">
            <div class="brand" aria-label="KAJOREC Guidance Office">
                <span class="brand-mark" aria-hidden="true">K</span>
                KAJOREC
            </div>

            <div data-auth-view="login" @if (old('_welcome_panel') === 'register') hidden inert @endif>
                <h1 class="form-heading" id="form-heading" tabindex="-1">Welcome to KAJOREC</h1>
                <p class="form-intro">Enter your details to sign in to your KAJOREC account.</p>

                @if (session('status'))
                    <p class="session-status" role="status">{{ session('status') }}</p>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="auth-form">
                    @csrf
                    <input type="hidden" name="_welcome_panel" value="login">

                    <div class="form-fields">
                        <label class="field" for="login-email">
                            Email address
                            <input
                                id="login-email"
                                name="email"
                                type="email"
                                value="{{ old('email') }}"
                                placeholder="you@example.com"
                                autocomplete="email"
                                required
                                autofocus
                            >
                            @error('email')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </label>

                        <div class="field">
                            <label for="login-password">Password</label>
                            <div class="password-input-wrap">
                                <input
                                    id="login-password"
                                    name="password"
                                    type="password"
                                    placeholder="Enter your password"
                                    autocomplete="current-password"
                                    required
                                >
                                <button
                                    class="password-toggle"
                                    type="button"
                                    data-password-toggle
                                    aria-controls="login-password"
                                    aria-label="Show password"
                                    aria-pressed="false"
                                >Show</button>
                            </div>
                            @error('password')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-options">
                            <label class="remember">
                                <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                                Remember me
                            </label>
                            @if (Route::has('password.request'))
                                <a class="text-button" href="{{ route('password.request') }}">Forgot password?</a>
                            @endif
                        </div>

                        <button class="submit-button" type="submit">Sign in</button>
                    </div>
                </form>

                <p class="switch-copy">
                    New to KAJOREC?
                    <button class="text-button" type="button" data-show-panel="register">Create an account</button>
                </p>
            </div>

            <div data-auth-view="register" @if (old('_welcome_panel') !== 'register') hidden inert @endif>
                <h1 class="form-heading" tabindex="-1">Create your account</h1>
                <p class="form-intro">A few details and you’ll be ready to get started.</p>
                <p class="form-intro">Use an email address you can access. We will send a verification code; verify it to use your account.</p>

                <form method="POST" action="{{ route('register.store') }}" class="auth-form">
                    @csrf
                    <input type="hidden" name="_welcome_panel" value="register">

                    <div class="form-fields">
                        <div class="name-fields">
                            <label class="field" for="register-first-name">First name
                                <input id="register-first-name" name="first_name" type="text" value="{{ old('first_name') }}" autocomplete="given-name" required>
                                @error('first_name')<span class="field-error">{{ $message }}</span>@enderror
                            </label>
                            <label class="field" for="register-last-name">Last name
                                <input id="register-last-name" name="last_name" type="text" value="{{ old('last_name') }}" autocomplete="family-name" required>
                                @error('last_name')<span class="field-error">{{ $message }}</span>@enderror
                            </label>
                            <label class="field" for="register-middle-initial">M.I. (optional)
                                <input id="register-middle-initial" name="middle_initial" type="text" value="{{ old('middle_initial') }}" maxlength="10" autocomplete="additional-name" placeholder="e.g. A">
                                @error('middle_initial')<span class="field-error">{{ $message }}</span>@enderror
                            </label>
                            <label class="field" for="register-name-extension">Suffix (optional)
                                <input id="register-name-extension" name="name_extension" type="text" value="{{ old('name_extension') }}" maxlength="20" placeholder="e.g. Jr., III">
                                @error('name_extension')<span class="field-error">{{ $message }}</span>@enderror
                            </label>
                        </div>

                        <label class="field" for="register-id-number">
                            Student / Teacher ID
                            <input
                                id="register-id-number"
                                name="id_number"
                                type="text"
                                value="{{ old('id_number') }}"
                                placeholder="Enter your ID number"
                                autocomplete="off"
                                required
                            >
                            @error('id_number')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="field" for="register-gender">
                            Gender
                            <select id="register-gender" name="gender" required>
                                <option value="">Select gender</option>
                                <option value="male" @selected(old('gender') === 'male')>Male</option>
                                <option value="female" @selected(old('gender') === 'female')>Female</option>
                            </select>
                            @error('gender')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="field" for="register-email">
                            Email address
                            <input
                                id="register-email"
                                name="email"
                                type="email"
                                value="{{ old('email') }}"
                                placeholder="you@example.com"
                                autocomplete="email"
                                required
                            >
                            @error('email')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </label>

                        <div class="field">
                            <label for="register-password">Password</label>
                            <div class="password-input-wrap">
                                <input
                                    id="register-password"
                                    name="password"
                                    type="password"
                                    placeholder="Create a password"
                                    autocomplete="new-password"
                                    required
                                >
                                <button
                                    class="password-toggle"
                                    type="button"
                                    data-password-toggle
                                    aria-controls="register-password"
                                    aria-label="Show password"
                                    aria-pressed="false"
                                >Show</button>
                            </div>
                            @error('password')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="field">
                            <label for="register-password-confirmation">Confirm password</label>
                            <div class="password-input-wrap">
                                <input
                                    id="register-password-confirmation"
                                    name="password_confirmation"
                                    type="password"
                                    placeholder="Enter your password again"
                                    autocomplete="new-password"
                                    required
                                >
                                <button
                                    class="password-toggle"
                                    type="button"
                                    data-password-toggle
                                    aria-controls="register-password-confirmation"
                                    aria-label="Show password"
                                    aria-pressed="false"
                                >Show</button>
                            </div>
                        </div>

                        <button class="submit-button" type="submit">Create account</button>
                    </div>
                </form>

                <p class="switch-copy">
                    Already have an account?
                    <button class="text-button" type="button" data-show-panel="login">Sign in</button>
                </p>
            </div>

            </section>

            <aside class="welcome-panel" aria-label="KAJOREC information">
            <div class="welcome-top">
                <span>Guidance Office Records</span>
                <span class="online-indicator">Here to support you</span>
            </div>

            <div class="welcome-copy" data-welcome-content>
                <p class="eyebrow">Benguet State University · Bokod Campus</p>
                <h2 class="welcome-title">A clearer path to <span>wellbeing.</span></h2>
                <p class="welcome-description">
                    Find the support you need with organized records, confidential guidance,
                    and appointments designed around your wellbeing.
                </p>
            </div>

            <section class="contact-content" data-contact-content inert aria-hidden="true" aria-labelledby="contact-heading">
                <p class="eyebrow">KAJOREC SUPPORT</p>
                <h2 class="welcome-title" id="contact-heading" tabindex="-1">Contact <span>Us</span></h2>
                <p class="welcome-description">We are the researchers behind KajoRec. Get in touch if you need help with your account or signing in.</p>

                <div class="contact-cards">
                    <article class="contact-card">
                        <span class="contact-card-label">Email</span>
                        <a href="mailto:kajorec@gmail.com">kajorec@gmail.com</a>
                    </article>
                    <article class="contact-card">
                        <span class="contact-card-label">Support hours</span>
                        <strong>8:00 AM–3:00 PM</strong>
                    </article>
                </div>

                <button class="contact-back" type="button" data-show-panel="login">Back to login</button>
            </section>

            <div class="welcome-footer">
                <span>Guidance and support, made accessible</span>
                <span class="footer-line" aria-hidden="true"></span>
                <span>KAJOREC</span>
            </div>
            </aside>
        </main>
    </div>

    <script>
        const authPage = document.querySelector('[data-auth-page]');
        const authViews = authPage.querySelectorAll('[data-auth-view]');
        const welcomeContent = authPage.querySelector('[data-welcome-content]');
        const contactContent = authPage.querySelector('[data-contact-content]');

        function showAuthView(viewName, moveFocus = true) {
            authPage.dataset.mode = viewName;
            welcomeContent.inert = false;
            welcomeContent.setAttribute('aria-hidden', 'false');
            contactContent.inert = true;
            contactContent.setAttribute('aria-hidden', 'true');

            authViews.forEach((view) => {
                const isActive = view.dataset.authView === viewName;
                view.hidden = !isActive;
                view.inert = !isActive;
            });

            if (moveFocus) {
                authPage.querySelector(`[data-auth-view="${viewName}"] h1`).focus();
            }
        }

        function showContactPanel() {
            authPage.dataset.mode = 'contact';
            contactContent.inert = false;
            contactContent.setAttribute('aria-hidden', 'false');
            welcomeContent.inert = true;
            welcomeContent.setAttribute('aria-hidden', 'true');

            authViews.forEach((view) => {
                const isActive = view.dataset.authView === 'login';
                view.hidden = !isActive;
                view.inert = !isActive;
            });

            requestAnimationFrame(() => contactContent.querySelector('h2').focus({ preventScroll: true }));
        }

        document.querySelectorAll('[data-show-panel]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                if (button.dataset.showPanel === 'contact') {
                    showContactPanel();
                } else {
                    showAuthView(button.dataset.showPanel);
                }
            });
        });

        authPage.querySelectorAll('[data-password-toggle]').forEach((button) => {
            const passwordInput = document.getElementById(button.getAttribute('aria-controls'));

            button.addEventListener('click', () => {
                const isVisible = passwordInput.type === 'text';
                passwordInput.type = isVisible ? 'password' : 'text';
                button.textContent = isVisible ? 'Show' : 'Hide';
                button.setAttribute('aria-label', `${isVisible ? 'Show' : 'Hide'} password`);
                button.setAttribute('aria-pressed', String(!isVisible));
            });
        });

        authPage.querySelector('[data-auth-view="register"] form').addEventListener('submit', (event) => {
            const confirmed = window.confirm('Please confirm that all the information you entered is correct before creating your account.');

            if (!confirmed) {
                event.preventDefault();
            }
        });

        showAuthView(authPage.dataset.mode, false);
    </script>
</body>
</html>
