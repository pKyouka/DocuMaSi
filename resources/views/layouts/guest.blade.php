<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? 'Masuk' }} - DocuMaSi | Teknologi Informasi UNISA</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen font-sans antialiased text-gray-800 bg-[#f4f6f9] flex flex-col justify-between selection:bg-[#002147] selection:text-white">
        <!-- Top Navigation Bar matching psti.unisayogya.ac.id -->
        <header class="bg-[#002147] border-b-2 border-[#f1b500] shadow-sm px-6 py-3 shrink-0">
            <div class="max-w-6xl mx-auto flex items-center justify-between">
                <a href="{{ route('home') }}" class="flex items-center gap-3">
                    <img src="https://psti.unisayogya.ac.id/wp-content/uploads/2023/11/logo-ti-unisa-putih.png" 
                         alt="Program Studi Teknologi Informasi UNISA" 
                         class="h-9 sm:h-11 w-auto object-contain"
                         onerror="this.style.display='none'">
                    <div class="border-l border-white/20 pl-3 hidden sm:block">
                        <span class="text-white text-xs font-bold tracking-wider uppercase block">DocuMaSi</span>
                        <span class="text-gray-300 text-[10px] block">Sistem Manajemen Dokumen</span>
                    </div>
                </a>
                <div class="text-right hidden md:block">
                    <span class="text-[#f1b500] text-xs font-semibold tracking-wider uppercase block">Teknologi Informasi</span>
                    <span class="text-white/80 text-[10px] block">Universitas 'Aisyiyah Yogyakarta</span>
                </div>
            </div>
        </header>

        <!-- Center Content / Login Card -->
        <main class="flex-1 flex items-center justify-center p-4 sm:p-6 my-auto">
            <div class="w-full max-w-md">
                {{ $slot }}
            </div>
        </main>

        <!-- Footer matching psti.unisayogya.ac.id -->
        <footer class="bg-[#001733] border-t border-white/10 text-white/70 py-4 px-6 text-center text-xs shrink-0">
            <div class="max-w-6xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-2 text-[11px]">
                <p>&copy; {{ date('Y') }} Program Studi Teknologi Informasi &bull; Universitas 'Aisyiyah Yogyakarta</p>
                <p class="text-white/50">Kampus Terpadu: Jl. Siliwangi (Ring Road Barat) No. 63, Sleman, D.I. Yogyakarta</p>
            </div>
        </footer>
    </body>
</html>
