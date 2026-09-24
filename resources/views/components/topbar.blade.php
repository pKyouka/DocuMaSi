<header class="h-16 bg-[#002147] border-b-2 border-[#f1b500] px-6 shrink-0 flex items-center justify-between gap-4 z-20 shadow-md">
    <!-- Left: Brand Logo & Navigation -->
    <div class="flex items-center gap-6">
        <a href="{{ route('home') }}" class="flex items-center gap-3">
            <img src="https://psti.unisayogya.ac.id/wp-content/uploads/2023/11/logo-ti-unisa-putih.png"
                 alt="Program Studi Teknologi Informasi UNISA"
                 class="h-9 sm:h-10 w-auto object-contain"
                 onerror="this.style.display='none'">
            <div class="border-l border-white/20 pl-3">
                <h1 class="text-sm sm:text-base font-black text-white leading-tight tracking-tight">SMART</h1>
                <p class="text-[10px] text-[#f1b500] font-bold uppercase tracking-wider">Arsip Repository TI &bull; PSTI</p>
            </div>
        </a>

        <nav class="hidden sm:flex items-center gap-1.5 pl-4 border-l border-white/10 text-xs font-semibold">
            <a href="{{ route('folders.index') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('folders.*') ? 'bg-[#f1b500] text-[#002147] font-bold' : 'text-white/80 hover:text-white hover:bg-white/10' }} transition-colors">
                File Explorer
            </a>

            <a href="{{ route('google-drive.index') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('google-drive.*') ? 'bg-[#f1b500] text-[#002147] font-bold' : 'text-white/80 hover:text-white hover:bg-white/10' }} transition-colors flex items-center gap-1.5">
                <x-google-drive-icon class="w-3.5 h-3.5 shrink-0" />
                Google Drive
            </a>

            @if(auth()->user()->canManageUsers())
            <a href="{{ route('users.index') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('users.*') ? 'bg-[#f1b500] text-[#002147] font-bold' : 'text-white/80 hover:text-white hover:bg-white/10' }} transition-colors">
                Pengguna
            </a>
            @endif
        </nav>
    </div>

    <!-- Right: User Menu -->
    <div class="flex items-center gap-3">
        <!-- User Dropdown (Alpine.js) -->
        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
            <button @click="open = !open" class="flex items-center gap-2 p-1 rounded-lg hover:bg-white/10 transition-colors focus:outline-none">
                <div class="w-8 h-8 rounded-full bg-[#f1b500] text-[#002147] font-black flex items-center justify-center text-xs shadow-sm ring-2 ring-white/20">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </div>
                <div class="text-left hidden lg:block">
                    <div class="text-xs font-bold text-white leading-tight">{{ auth()->user()->name }}</div>
                    <div class="text-[10px] text-gray-300 leading-tight">{{ auth()->user()->role_label }}</div>
                </div>
                <svg class="w-3.5 h-3.5 text-white/60 hidden lg:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>

            <!-- Dropdown Menu -->
            <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="transform opacity-0 scale-95" x-transition:enter-end="transform opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="transform opacity-100 scale-100" x-transition:leave-end="transform opacity-0 scale-95" class="absolute right-0 mt-2 w-52 bg-white rounded-xl shadow-xl border border-gray-200 py-1.5 z-50 text-xs" style="display: none;">
                <div class="px-4 py-2 border-b border-gray-100">
                    <p class="font-bold text-gray-900 truncate">{{ auth()->user()->name }}</p>
                    <p class="text-[11px] text-gray-500 truncate">{{ auth()->user()->department ?? 'Superadmin Pusat' }}</p>
                </div>

                <a href="{{ route('google-drive.index') }}" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 font-medium">Google Drive</a>
                <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-gray-700 hover:bg-gray-50">Pengaturan Profil</a>
                @if(auth()->user()->canManageUsers())
                <a href="{{ route('users.index') }}" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 font-medium">Kelola Pengguna</a>
                @endif

                <div class="border-t border-gray-100 my-1"></div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="block w-full text-left px-4 py-2 text-red-600 hover:bg-red-50 font-semibold">
                        Keluar (Sign out)
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
