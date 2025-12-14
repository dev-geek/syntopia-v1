@extends('dashboard.layouts.dashboard')

@section('title', 'Dashboard')

@section('content')
    {{-- Show to Super Admin --}}
    @hasrole('Super Admin')
        @include('dashboard.includes.cards')
    @endhasrole

    {{-- Show to Sub Admin --}}
    @hasrole('Sub Admin')
        @include('dashboard.includes.subadmin-cards')
    @endhasrole

    {{-- Show to role User --}}
    @role('User')
        @if (!Auth::user()->hasValidSubscriberPassword())
            @include('dashboard.includes.password-notification')
        @endif
        @include('dashboard.includes.login-to-software-notification')
    @endrole
@endsection
