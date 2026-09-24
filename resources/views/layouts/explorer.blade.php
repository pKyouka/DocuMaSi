<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'SMART') }} - File Explorer</title>

        <!-- Favicon (PSTI UNISA) -->
        <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
        <link rel="icon" href="https://psti.unisayogya.ac.id/wp-content/uploads/2023/11/cropped-unisa22-scaled-1-32x32.jpg" sizes="32x32">
        <link rel="icon" href="https://psti.unisayogya.ac.id/wp-content/uploads/2023/11/cropped-unisa22-scaled-1-192x192.jpg" sizes="192x192">
        <link rel="apple-touch-icon" href="https://psti.unisayogya.ac.id/wp-content/uploads/2023/11/cropped-unisa22-scaled-1-180x180.jpg">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

        <!-- Scripts & Styles -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-gray-900 bg-gray-50 h-screen overflow-hidden flex flex-col">
        {{ $slot }}
    </body>
</html>
