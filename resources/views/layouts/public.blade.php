<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'SMART') }} - Pencarian Publik</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-gray-900 bg-[#f4f7f5] flex flex-col min-h-screen">

        <!-- Header Publik -->
        <header class="bg-white border-b border-gray-200 sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16 items-center">
                    <!-- Logo -->
                    <div class="flex shrink-0 items-center">
                        <a href="{{ route('search.index') }}" class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-500 flex items-center justify-center text-white font-black shadow-sm">
                                D
                            </div>
                            <div class="hidden sm:block">
                                <h1 class="text-sm font-bold leading-tight text-[#173b30]">SMART</h1>
                                <p class="text-[9px] text-gray-500 uppercase tracking-widest font-semibold">Portal Dokumen Publik</p>
                            </div>
                        </a>
                    </div>

                    <!-- Menu Kanan -->
                    <div class="flex items-center gap-4">
                        @auth
                            <a href="{{ route('dashboard') }}" class="text-sm font-medium text-gray-600 hover:text-[#00875a] transition-colors">
                                Ke Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="inline-flex items-center px-4 py-2 border border-[#00875a] rounded-lg text-sm font-semibold text-[#006c48] hover:bg-[#e7f4ee] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#00875a] transition-colors">
                                Login Pengelola
                            </a>
                        @endauth
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto py-8 sm:py-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                {{ $slot }}
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-white border-t border-gray-200 py-6 text-center">
            <p class="text-sm text-gray-500">&copy; {{ date('Y') }} Universitas 'Aisyiyah Yogyakarta</p>
        </footer>
    </body>
</html>
