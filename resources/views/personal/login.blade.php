@extends('layouts/blankLayout')

@section('title', 'University QAMS | Sign in')

@section('page-style')
@vite(['resources/assets/css/custom/login.css'])
@endsection

@section('content')
<main class="google-login-page">
    <div class="login-container">
        <section class="login-showcase" aria-label="Southern Leyte State University">
            <img class="showcase-background" src="{{ asset('assets/img/custom/landmark.jpg') }}" alt="" />
            <img class="showcase-stripe" src="{{ asset('assets/img/custom/whiteStripe.png') }}" alt="" />
            <img class="showcase-logo" src="{{ asset('assets/img/custom/Southern_Leyte_State_University logo.png') }}" alt="Southern Leyte State University" />
        </section>

        <section class="login-panel" aria-labelledby="login-heading">
            <div class="login-panel-inner">
                <div class="login-system-title" aria-label="Quality Assurance Management System">
                    <span class="login-system-title-primary">Quality Assurance</span>
                    <span class="login-system-title-secondary">Management System</span>
                </div>
                <div class="login-heading">
                    <h2 id="login-heading">Quality Begins with you</h2>
                    <p>Sign in and manage the university's quality assurance with ease.</p>
                </div>

                @if (session('auth_error'))
                    <div class="login-auth-error" role="alert">{{ session('auth_error') }}</div>
                @endif

                <div class="login-actions">
                    <a class="google-login-button" href="{{ route('auth.google.redirect') }}" data-google-login>
                        <svg class="google-mark" data-login-google-mark viewBox="0 0 48 48" aria-hidden="true">
                            <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5Z" />
                            <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.75 7.18l7.73 6C44.43 38.05 46.98 31.91 46.98 24.55Z" />
                            <path fill="#FBBC05" d="M10.53 28.59A14.4 14.4 0 0 1 9.75 24c0-1.59.27-3.13.76-4.59l-7.98-6.19A23.9 23.9 0 0 0 0 24c0 3.87.93 7.53 2.56 10.78l7.97-6.19Z" />
                            <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.91-5.8l-7.73-6c-2.14 1.45-4.89 2.3-8.18 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48Z" />
                        </svg>
                        <span data-login-label>Sign in with Google</span>
                        <span class="spinner-border spinner-border-sm" data-login-spinner role="status" aria-label="Signing in" hidden></span>
                    </a>

                    <div class="login-divider">SECURE UNIVERSITY ACCESS</div>
                </div>

            </div>
            <p class="login-help">
                <i class="ri ri-question-line login-help-icon" aria-hidden="true"></i>
                <span>Trouble signing in? Contact System Administrator for assistance</span>
            </p>
        </section>
    </div>
</main>
@endsection

@section('page-script')
@vite(['resources/assets/js/custom/login.js'])
@endsection
