@extends('auth.layouts.auth')

@section('title', 'Admin Reset Password')


@push('scripts')
<script>
    function showLoading(show) {
        const spinner = document.getElementById('loadingSpinner');
        const buttonSpinner = document.querySelector('.button-spinner');
        const buttonText = document.querySelector('.button-text');
        const submitButton = document.getElementById('submitButton');

        if (show) {
            spinner.style.display = 'flex';
            buttonSpinner.style.display = 'block';
            buttonText.textContent = 'Processing...';
            submitButton.disabled = true;
        } else {
            spinner.style.display = 'none';
            buttonSpinner.style.display = 'none';
            const currentLabel = buttonText.textContent || '';
            buttonText.textContent = currentLabel.includes('Reset') ? 'Reset Password' : 'Continue';
            submitButton.disabled = false;
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const emailInput = document.getElementById('email');
        const securityQuestions = document.getElementById('securityQuestions');
        const form = document.getElementById('passwordResetForm');
        const submitButton = document.getElementById('submitButton');
        let emailVerified = false;

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('email') && urlParams.has('show_questions')) {
            emailInput.value = urlParams.get('email');
            emailInput.readOnly = true;
            securityQuestions.style.display = 'block';
            const buttonTextEl = document.querySelector('.button-text');
            if (buttonTextEl) buttonTextEl.textContent = 'Reset Password';
            emailVerified = true;
        }

        form.addEventListener('submit', async function(e) {
            if (!emailVerified) {
                e.preventDefault();
                showLoading(true);

                try {
                    const response = await fetch('{{ route("admin.password.check-email") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            email: emailInput.value
                        })
                    });

                    const data = await response.json();

                    if (data.requires_security_questions) {
                        emailInput.readOnly = true;
                        securityQuestions.style.display = 'block';
                        const buttonTextEl2 = document.querySelector('.button-text');
                        if (buttonTextEl2) buttonTextEl2.textContent = 'Reset Password';
                        emailVerified = true;

                        const emailField = document.createElement('input');
                        emailField.type = 'hidden';
                        emailField.name = 'email';
                        emailField.value = emailInput.value;
                        form.appendChild(emailField);
                    } else {
                        form.submit();
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                } finally {
                    showLoading(false);
                }
            }
        });
    });
</script>
@endpush

@section('content')
<div id="loadingSpinner" class="spinner-container">
    <div class="spinner"></div>
</div>

<div class="auth-flex-container">
    <div class="auth-container-box">
        <x-auth-logo-container />
        <h2 class="auth-heading-text">Admin Password Reset</h2>

        @if (session('status'))
            <div class="auth-alert auth-alert-success" role="alert">
                @if (is_array(session('status')))
                    {{ session('status.message') }}
                @else
                    {{ session('status') }}
                @endif
            </div>
        @endif

        <form method="POST" action="{{ route('admin.password.email') }}" id="passwordResetForm">
            @csrf

            <div class="form-group">
                <label for="email" class="auth-label">Email Address</label>
                <input id="email" type="email" class="auth-form-control @error('email') auth-is-invalid @enderror"
                       name="email" value="{{ old('email', request('email', '')) }}"
                       required autocomplete="email" autofocus
                       {{ request()->has('email') ? 'readonly' : '' }}>

                @error('email')
                    <span class="error-message" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
            </div>

            <div id="securityQuestions" style="display: none;">
                <div class="form-group">
                    <label for="city" class="auth-label">What city were you born in?</label>
                    <input id="city" type="text" class="auth-form-control @error('city') auth-is-invalid @enderror"
                           name="city" value="{{ old('city') }}">
                    @error('city')
                        <span class="error-message" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="pet" class="auth-label">What was your first pet's name?</label>
                    <input id="pet" type="text" class="auth-form-control @error('pet') auth-is-invalid @enderror"
                           name="pet" value="{{ old('pet') }}">
                    @error('pet')
                        <span class="error-message" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>
            </div>

            <button type="submit" class="auth-primary-button black" id="submitButton">
                <span class="button-content">
                    <span class="button-text">Continue</span>
                    <span class="button-spinner"></span>
                </span>
            </button>
        </form>

        <div style="margin-top: 20px;">
            <a href="{{ route('admin.login') }}">Back to Login</a>
        </div>
    </div>
</div>
@endsection
