<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Subscription Required - Syntopia</title>

    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="{{ asset('syntopia-logo.webp') }}">
    <link rel="shortcut icon" type="image/webp" href="{{ asset('syntopia-logo.webp') }}">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            line-height: 1.5;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .subscription-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            padding: 40px;
            max-width: 600px;
            width: 90%;
            text-align: center;
            margin: 20px;
        }

        .subscription-icon {
            font-size: 64px;
            color: #3b82f6;
            margin-bottom: 20px;
        }

        h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 16px;
            color: #1e293b;
        }

        p {
            font-size: 16px;
            color: #64748b;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .btn {
            display: inline-block;
            background: #3b82f6;
            color: white;
            font-weight: 600;
            padding: 12px 28px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }

        .btn:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }

        .btn-outline {
            background: transparent;
            color: #3b82f6;
            border: 1px solid #3b82f6;
            margin-left: 12px;
        }

        .btn-outline:hover {
            background: #f1f5f9;
            color: #2563eb;
        }

        .logo {
            max-width: 180px;
            margin-bottom: 30px;
        }

        @media (max-width: 640px) {
            .subscription-container {
                padding: 30px 20px;
            }

            .subscription-icon {
                font-size: 48px;
            }

            h1 {
                font-size: 24px;
            }

            .btn {
                width: 100%;
                margin-bottom: 12px;
            }

            .btn-outline {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="subscription-container">
        <img src="{{ asset('syntopia-logo.webp') }}" alt="Syntopia" class="logo">

        <div class="subscription-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                <path d="M12 8v4"></path>
                <path d="M12 16h.01"></path>
            </svg>
        </div>

        <h1>Subscription Required</h1>

        <p>
            You need an active subscription to access this page. Choose a plan that best suits your needs
            to continue using our services without interruption.
        </p>

        <div>
            <a href="{{ route('subscription') }}" class="btn">View Subscription Plans</a>
            <a href="{{ route('user.dashboard') }}" class="btn btn-outline">Go to Dashboard</a>
        </div>
    </div>

    @if(session('error'))
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Subscription Required',
                text: '{{ session('error') }}',
                confirmButtonText: 'View Plans',
                confirmButtonColor: '#3b82f6',
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '{{ route('subscription') }}';
                }
            });
        });
    </script>
    @endif
</body>
</html>
