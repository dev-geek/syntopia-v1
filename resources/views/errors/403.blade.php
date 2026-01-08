<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>403 — Forbidden</title>

	<style>
		:root {
			--bg: #0f172a; /* slate-900 */
			--card: #111827; /* gray-900 */
			--muted: #94a3b8; /* slate-400 */
			--text: #e5e7eb; /* gray-200 */
			--primary: #6366f1; /* indigo-500 */
			--primary-600: #5458ee;
			--ring: rgba(99, 102, 241, 0.25);
		}

		* { box-sizing: border-box; }
		html, body { height: 100%; }
		body {
			margin: 0;
			font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji";
			background: radial-gradient(1200px 800px at 80% -10%, rgba(99,102,241,0.15), transparent 60%),
			            radial-gradient(1000px 600px at -10% 110%, rgba(14,165,233,0.12), transparent 60%),
			            var(--bg);
			color: var(--text);
			line-height: 1.5;
		}

		.wrapper {
			height: 100%;
			display: grid;
			place-items: center;
			padding: 24px;
		}

		.card {
			width: 100%;
			max-width: 720px;
			background: linear-gradient(180deg, rgba(255,255,255,0.02), transparent 60%), var(--card);
			border: 1px solid rgba(148,163,184,0.12);
			border-radius: 16px;
			box-shadow: 0 10px 30px rgba(2,6,23,0.6), 0 0 0 1px rgba(99,102,241,0.10) inset;
			padding: 32px;
		}

		.header { display: flex; align-items: center; gap: 16px; margin-bottom: 8px; }
		.badge {
			font-weight: 700; font-size: 14px; letter-spacing: 0.08em; text-transform: uppercase;
			color: var(--muted);
		}

		.code {
			color: var(--primary);
			background: rgba(99,102,241,0.08);
			border: 1px solid rgba(99,102,241,0.25);
			border-radius: 10px;
			padding: 6px 10px;
			font-weight: 700;
		}

		h1 { font-size: 28px; margin: 8px 0 12px; letter-spacing: -0.01em; }
		p { margin: 0 0 8px; color: var(--muted); }

		.message {
			margin-top: 12px;
			padding: 12px 14px;
			background: rgba(148,163,184,0.08);
			border: 1px solid rgba(148,163,184,0.16);
			border-radius: 10px;
			color: #cbd5e1;
		}

		.actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 20px; }
		.button {
			display: inline-flex; align-items: center; justify-content: center; gap: 10px;
			padding: 12px 16px; border-radius: 12px; border: 1px solid transparent;
			text-decoration: none; font-weight: 600; transition: transform .05s ease, box-shadow .2s ease, background .2s ease, border-color .2s ease;
			will-change: transform;
		}
		.button:hover { transform: translateY(-1px); }
		.button:focus { outline: none; box-shadow: 0 0 0 6px var(--ring); }

		.button-primary { background: var(--primary); color: white; }
		.button-primary:hover { background: var(--primary-600); }

		.button-ghost {
			background: transparent; color: var(--text);
			border-color: rgba(148,163,184,0.25);
		}
		.button-ghost:hover { background: rgba(148,163,184,0.08); }

		.footer { margin-top: 28px; font-size: 12px; color: var(--muted); }
		.small { font-size: 12px; color: var(--muted); }

		.icon {
			display: inline-flex; align-items: center; justify-content: center;
			width: 42px; height: 42px; border-radius: 12px;
			background: rgba(99,102,241,0.12);
			border: 1px solid rgba(99,102,241,0.25);
			color: var(--primary);
		}

		@media (max-width: 480px) {
			h1 { font-size: 22px; }
			.card { padding: 24px; }
		}
	</style>
</head>
<body>
	<div class="wrapper">
		<main class="card" role="main" aria-labelledby="error-title">
			<div class="header">
				<span class="badge">Access Restricted</span>
			</div>
			<h1 id="error-title">
				@if(str_contains($exception?->getMessage() ?? '', 'Registration'))
					Registration restricted
				@else
					Access Denied
				@endif
			</h1>
			<p>
				@if(str_contains($exception?->getMessage() ?? '', 'Registration'))
					We suspect an account has already been registered from this device or network. If this seems wrong, please contact support.
				@else
					You don't have permission to access this resource.
				@endif
			</p>
			<div class="message">
				{{ $exception?->getMessage() ?: 'This action is unauthorized.' }}
			</div>

			<div class="actions">
				<a class="button button-primary" href="{{ url('/') }}">Return to homepage</a>
				<a class="button button-ghost" href="{{ url()->previous() }}">Go back</a>
			</div>

			<div class="footer">
				<span class="small">Request time: {{ now()->toDayDateTimeString() }}</span>
				@if(app()->hasDebugModeEnabled())
					<span class="small"> • Environment: {{ app()->environment() }}</span>
				@endif
			</div>
		</main>
	</div>
</body>
</html>


