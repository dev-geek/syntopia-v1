<!DOCTYPE html>
<html>
<head>
    <title>Verify Email Code</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body>
    <h2>Enter Verification Code</h2>
    <form method="POST" action="{{ url('/verify-code') }}">
        @csrf
        <div>
            <label for="verification-code">Verification code</label>
            <input type="text" name="verification_code" id="verification-code" required>
            @error('verification_code')
                <div style="color:red">{{ $message }}</div>
            @enderror
        </div>
        <button type="submit">Verify</button>
    </form>
</body>
</html>
