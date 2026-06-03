<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@hasSection('title')@yield('title') | @endif Stat Engine</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Barlow+Condensed:ital,wght@0,700;0,900;1,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-court-black text-white">
    <nav class="border-b border-white/5 bg-court-dark/50 backdrop-blur-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-6 h-16 flex items-center">
            <a href="/chatbot" class="flex items-center gap-2">
                <div class="size-8 bg-hoop-orange rounded flex items-center justify-center font-display font-black text-xl italic text-white shadow-[0_0_20px_rgba(255,93,34,0.3)]">
                    E
                </div>
                <span class="font-display text-xl font-bold tracking-tight uppercase italic">
                    Stat Engine
                </span>
            </a>
        </div>
    </nav>

    <div class="min-h-[calc(100vh-4rem)] flex flex-col sm:justify-center items-center pt-6 sm:pt-0 px-4">
        <div class="w-full sm:max-w-md mt-6 p-8 bg-court-dark border border-white/10 rounded-2xl shadow-2xl">
            {{ $slot }}
        </div>
    </div>
</body>
</html>
