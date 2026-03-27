<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('auth.registration.title') }} - {{ config('app.name', 'PharmaVet') }}</title>
        
        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="bg-zinc-50 dark:bg-black text-zinc-900 antialiased min-h-screen flex flex-col items-center justify-center p-6">
        
        <div class="mb-8">
            <a href="/" class="flex items-center gap-2 group">
                 <div class="w-10 h-10 bg-zinc-900 dark:bg-white rounded-xl flex items-center justify-center group-hover:rotate-12 transition-transform">
                    <span class="text-white dark:text-zinc-900 font-bold text-xl">P</span>
                 </div>
                 <span class="text-2xl font-bold tracking-tight">PharmaVet</span>
            </a>
        </div>

        <livewire:auth.partner-registration />
        @livewireScripts
    </body>
</html>
