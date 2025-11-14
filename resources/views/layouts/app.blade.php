<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

<meta http-equiv="Content-Security-Policy" content="
        default-src 'self' data: gap: https://ssl.gstatic.com https://livebuzzstudio.test;
        style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://sbl.onfastspring.com https://cdn.paddle.com https://sandbox-cdn.paddle.com;
        font-src 'self' https://fonts.gstatic.com;
        script-src 'self' https://livebuzzstudio.test https://somedomain.com https://sbl.onfastspring.com https://cdn.jsdelivr.net https://cdn.paddle.com https://sandbox-cdn.paddle.com https://secure.payproglobal.com 'unsafe-inline' 'unsafe-eval';
        img-src 'self' https://syntopia.ai https://sbl.onfastspring.com data:;
        connect-src 'self' https://livebuzzstudio.test https://livebuzzstudio.test.onfastspring.com https://sbl.onfastspring.com https://sandbox-api.paddle.com https://sandbox-cdn.paddle.com https://api.paddle.com https://cdn.paddle.com https://store.payproglobal.com https://secure.payproglobal.com;
        frame-src 'self' https://livebuzzstudio.test https://livebuzzstudio.test.onfastspring.com https://sbl.onfastspring.com https://cdn.paddle.com https://sandbox-cdn.paddle.com https://sandbox-buy.paddle.com;
        media-src 'self' data: https://sbl.onfastspring.com;">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Syntopia') }}</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" type="image/png" href="{{ asset('favicon.png') }}">

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito" rel="stylesheet">

    <!-- Bootstrap CSS (CDN) -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <link href="{{ asset('css/password-toggle.css') }}" rel="stylesheet">

    <!-- Scripts -->
    <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>



</head>
<body>
    <div id="app">
        @include('components.alert-messages')
        @include('components.spinner-overlay')

        <main class="py-4">
            @yield('content')
        </main>
    </div>
    <!-- Bootstrap JS and Popper.js (for Bootstrap components) -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <!-- Spinner Utility Script -->
    <script src="{{ asset('js/spinner-utils.js') }}"></script>

    <!-- Password Toggle Script -->
    <script src="{{ asset('js/password-toggle.js') }}"></script>

    <!-- Stack for scripts from components -->
    @stack('scripts')

    <!-- Your custom scripts -->
    @yield('scripts')
</body>
</html>
