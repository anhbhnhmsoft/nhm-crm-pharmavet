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
        <div class="w-full max-w-5xl flex justify-end gap-2 mb-6">
            <a href="{{ route('partner.register', ['lang' => 'vi']) }}" @class([
                'inline-flex items-center rounded-xl border px-3 py-2 text-sm font-semibold transition-colors',
                app()->getLocale() === 'vi'
                    ? 'border-zinc-900 bg-zinc-900 text-white'
                    : 'border-zinc-300 bg-white text-zinc-700 hover:border-zinc-500 hover:text-zinc-900',
            ])>
                {{ __('auth.registration.languages.vi') }}
            </a>
            <a href="{{ route('partner.register', ['lang' => 'en']) }}" @class([
                'inline-flex items-center rounded-xl border px-3 py-2 text-sm font-semibold transition-colors',
                app()->getLocale() === 'en'
                    ? 'border-zinc-900 bg-zinc-900 text-white'
                    : 'border-zinc-300 bg-white text-zinc-700 hover:border-zinc-500 hover:text-zinc-900',
            ])>
                {{ __('auth.registration.languages.en') }}
            </a>
        </div>
        
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
