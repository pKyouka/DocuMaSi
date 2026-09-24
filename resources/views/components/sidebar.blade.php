<aside class="w-64 bg-[#0f172a] text-white flex flex-col hidden md:flex shrink-0">
    <!-- Logo & Title -->
    <div class="h-16 flex items-center px-6 bg-[#0B1121] border-b border-gray-800">
        <div class="w-8 h-8 rounded bg-blue-500 flex items-center justify-center mr-3 text-white font-bold text-xl shadow-md">
            S
        </div>
        <div>
            <h1 class="text-lg font-bold leading-tight tracking-wide text-gray-100">SMART</h1>
            <p class="text-[10px] text-gray-400 uppercase tracking-widest font-semibold">Arsip Repository TI</p>
        </div>
    </div>

    <!-- Navigation (Hanya File Explorer sesuai instruksi) -->
    <nav class="flex-1 overflow-y-auto py-4">
        <ul class="space-y-1 px-3">
            <li>
                <a href="{{ route('folders.index') }}" class="flex items-center px-3 py-2.5 rounded-lg {{ request()->routeIs('folders.*') || request()->routeIs('dashboard') ? 'bg-blue-600/20 text-blue-400 font-medium' : 'text-gray-300 hover:bg-gray-800 hover:text-white transition-colors' }}">
                    <svg class="w-5 h-5 mr-3 {{ request()->routeIs('folders.*') || request()->routeIs('dashboard') ? 'text-blue-400' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>
                    File Explorer
                </a>
            </li>
            <li>
                <a href="{{ route('google-drive.index') }}" class="flex items-center px-3 py-2.5 rounded-lg {{ request()->routeIs('google-drive.*') ? 'bg-blue-600/20 text-blue-400 font-medium' : 'text-gray-300 hover:bg-gray-800 hover:text-white transition-colors' }}">
                    <svg class="w-5 h-5 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h16v16H4zM8 8h8M8 12h8M8 16h5"></path></svg>
                    Impor dari Google Drive
                </a>
            </li>

            @if(auth()->user()->isSuperAdmin())
            <li>
                <a href="{{ route('users.index') }}" class="flex items-center px-3 py-2.5 rounded-lg {{ request()->routeIs('users.*') ? 'bg-blue-600/20 text-blue-400 font-medium' : 'text-gray-300 hover:bg-gray-800 hover:text-white transition-colors' }}">
                    <svg class="w-5 h-5 mr-3 {{ request()->routeIs('users.*') ? 'text-blue-400' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    Pengguna
                </a>
            </li>
            @endif

            <li class="pt-4 mt-4 border-t border-gray-800">
                <a href="{{ route('search.index') }}" target="_blank" class="flex items-center px-3 py-2 rounded-lg text-xs text-gray-400 hover:bg-gray-800 hover:text-gray-200 transition-colors">
                    <svg class="w-4 h-4 mr-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                    Portal Viewer Publik
                </a>
            </li>
        </ul>
    </nav>

    <!-- Footer Sidebar -->
    <div class="p-4 border-t border-gray-800 bg-[#0B1121] text-center">
        <div class="text-xs text-gray-400 mb-1">
            <span class="font-semibold text-blue-400">SMART</span>
        </div>
        <div class="text-[10px] text-gray-500">
            Sistem Manajemen Arsip Repository Teknologi Informasi
        </div>
    </div>
</aside>
