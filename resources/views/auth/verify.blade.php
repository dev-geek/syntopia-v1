@extends('auth.layouts.auth')

@section('title', 'Signup - Syntopia')

@push('bootstrap-js')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@endpush

@push('styles')
<style>
    .auth-heading-text.large {
        font-size: 50px;
        font-weight: 700;
        width: 50%;
        color: #5b0dd5;
        padding: 30px 0px 0px;
    }
    .auth-heading-text.large span {
        color: black;
    }
    .auth-flex-container {
        height: 55vh;
    }
    @media (max-width: 768px) {
        .auth-heading-text.large {
            font-size: 40px;
            width: 100%;
        }
        .auth-flex-container {
            height: 75vh;
        }
    }
</style>
@endpush

@section('content')
<x-auth-logo-container />

<div class="auth-flex-container">
    <div class="auth-container-box">
        <div class="auth-heading-text large">
            {{ __('Before proceeding, please check your email for a verification link.') }}
            {{ __('If you did not receive the email') }},
            <form class="d-inline" method="POST" action="{{ route('verification.resend') }}">
                @csrf
                <button type="submit" class="btn btn-link p-0 m-0 align-baseline">{{ __('click here to request another') }}</button>.
            </form>
        </div>
    </div>
</div>
@endsection
