<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', config('app.name'))</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen">
    <header class="bg-white border-b border-slate-200">
        <div class="max-w-5xl mx-auto px-6 py-5 flex items-center justify-between">
            <a href="{{ route('profiles.index') }}" class="text-lg font-semibold tracking-tight">
                {{ config('app.name') }}
            </a>
            @hasSection('subtitle')
                <span class="text-sm text-slate-500">@yield('subtitle')</span>
            @endif
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-6 py-8">
        @yield('content')
    </main>

    <footer class="max-w-5xl mx-auto px-6 py-8 text-xs text-slate-400">
        Actualizado a cada 15 minutos.
    </footer>
</body>
</html>
