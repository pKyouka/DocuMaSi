<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Integrasi Google Drive</h2>
                <p class="text-sm text-gray-500 mt-1">Hubungkan akun Google Drive untuk mendeteksi folder/file dan mengunduhnya ke arsip lokal SMART.</p>
            </div>
            @if(auth()->user()->google_drive_access_token)
                <div class="flex items-center gap-3">
                    <span class="text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-1.5 rounded-lg flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        Terhubung: {{ auth()->user()->google_drive_account_email }}
                    </span>
                    <form method="POST" action="{{ route('google-drive.disconnect') }}" onsubmit="return confirm('Yakin ingin memutuskan koneksi akun Google Drive ini?');">
                        @csrf
                        <button type="submit" class="text-xs text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100 border border-red-200 px-3 py-1.5 rounded-lg font-medium transition-colors">
                            Putuskan / Ganti Akun
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if(!config('services.google.client_id') || !config('services.google.client_secret'))
                <div class="bg-amber-50 border-l-4 border-amber-500 p-4 rounded-xl text-amber-800 text-sm">
                    <p class="font-bold flex items-center gap-2">
                        <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        Konfigurasi Google Client ID belum diatur di file .env
                    </p>
                    <p class="mt-1 text-xs text-amber-700">
                        Untuk dapat login ke Google Drive, tambahkan kredensial OAuth dari Google Cloud Console ke file <code>.env</code> Anda:
                    </p>
                    <pre class="mt-2 bg-amber-100/70 p-3 rounded-lg text-xs font-mono overflow-x-auto">GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-client-secret
GOOGLE_REDIRECT_URI="${APP_URL}/google-drive/callback"</pre>
                </div>
            @endif

            @if(!auth()->user()->google_drive_access_token)
                <div class="bg-white border border-gray-200 rounded-2xl p-10 text-center shadow-xs max-w-xl mx-auto">
                    <div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center mx-auto mb-4 ring-8 ring-blue-50/50">
                        <svg class="w-9 h-9" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12.01 1.99c-1.74 0-3.32.74-4.44 1.94l6.07 10.51 6.07-10.51c-1.12-1.2-2.7-1.94-4.44-1.94h-3.26zm-5.75 3.3l-5.76 9.98c.5 1.05 1.3 1.93 2.31 2.51l6.07-10.51-2.62-1.98zm11.48 0l-2.62 1.98 6.07 10.51c1.01-.58 1.81-1.46 2.31-2.51l-5.76-9.98zm-11.72 13.72c1.12 1.2 2.7 1.94 4.44 1.94h6.52c1.74 0 3.32-.74 4.44-1.94l-3.26-5.64h-8.88l-3.26 5.64z"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900">Hubungkan Akun Google Drive</h3>
                    <p class="text-sm text-gray-500 mt-2 leading-relaxed max-w-md mx-auto">
                        Setelah login, sistem akan membaca folder dan berkas yang ada di Drive Anda. Berkas dapat diimpor dan disimpan ke server SMART secara aman.
                    </p>

                    <div class="mt-6">
                        <a href="{{ route('google-drive.connect') }}" class="inline-flex items-center gap-3 px-6 py-3 rounded-xl bg-[#002147] hover:bg-[#001733] text-white font-semibold shadow-md hover:shadow-lg transition-all text-sm group">
                            <svg class="w-5 h-5" viewBox="0 0 24 24">
                                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                            </svg>
                            <span>Masuk / Login dengan Google</span>
                        </a>
                    </div>

                    <p class="text-[11px] text-gray-400 mt-4">Izin akses dibatasi hanya untuk membaca berkas (Read-Only).</p>
                </div>
            @else
                <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-xs" x-data="{ activeTab: 'folders', selectedFolder: null }">
                    <!-- Tab Header -->
                    <div class="px-6 py-4 border-b border-gray-200 bg-slate-50/50 flex flex-wrap items-center justify-between gap-4">
                        <div class="flex items-center gap-2">
                            <button @click="activeTab = 'folders'" :class="activeTab === 'folders' ? 'bg-[#002147] text-white shadow-xs font-bold' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100 font-medium'" class="px-3.5 py-1.5 rounded-lg text-xs transition-all flex items-center gap-2">
                                <svg class="w-4 h-4 text-[#f1b500]" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path></svg>
                                <span>Folder Terdeteksi ({{ $foldersFromDrive->count() }})</span>
                            </button>
                            <button @click="activeTab = 'files'" :class="activeTab === 'files' ? 'bg-[#002147] text-white shadow-xs font-bold' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100 font-medium'" class="px-3.5 py-1.5 rounded-lg text-xs transition-all flex items-center gap-2">
                                <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                <span>Berkas Satuan ({{ $filesFromDrive->count() }})</span>
                            </button>
                        </div>
                        <a href="{{ route('google-drive.index') }}" class="text-xs text-blue-600 hover:text-blue-800 font-semibold flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                            <span>Muat Ulang Drive</span>
                        </a>
                    </div>

                    <!-- TAB 1: FOLDER GOOGLE DRIVE -->
                    <div x-show="activeTab === 'folders'" class="p-6">
                        <div class="mb-4">
                            <h4 class="text-sm font-bold text-gray-900">Pilih Folder Google Drive untuk Dibuat di SMART</h4>
                            <p class="text-xs text-gray-500 mt-0.5">Sistem akan membuat folder lokal di SMART, mengunduh seluruh berkas di dalamnya, dan mengatur izin sharing offline antar biro.</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @forelse($foldersFromDrive as $folderItem)
                                <div class="border border-gray-200 hover:border-blue-400 rounded-xl p-4 transition-all bg-white hover:shadow-sm flex flex-col justify-between"
                                     x-data="{ openForm: false }">
                                    <div>
                                        <div class="flex items-start gap-3">
                                            <div class="w-10 h-10 rounded-xl bg-amber-50 text-[#f1b500] flex items-center justify-center shrink-0">
                                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path></svg>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <h5 class="font-bold text-sm text-gray-900 truncate" title="{{ $folderItem['name'] }}">{{ $folderItem['name'] }}</h5>
                                                <p class="text-[11px] text-gray-400 mt-0.5">Google Drive Folder</p>
                                            </div>
                                        </div>

                                        <!-- Form Drawer per Folder -->
                                        <div x-show="openForm" x-collapse class="mt-4 pt-3 border-t border-gray-100">
                                            <form method="POST" action="{{ route('google-drive.import-folder') }}" class="space-y-3">
                                                @csrf
                                                <input type="hidden" name="drive_folder_id" value="{{ $folderItem['id'] }}">

                                                <div>
                                                    <label class="block text-[11px] font-semibold text-gray-700 mb-1">Nama Folder di SMART</label>
                                                    <input type="text" name="folder_name" value="{{ $folderItem['name'] }}" required class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                                                </div>

                                                <div>
                                                    <label class="block text-[11px] font-semibold text-gray-700 mb-1">Simpan di Dalam Folder Lokal</label>
                                                    <select name="parent_id" class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                                                        <option value="">(Root Direktori Utama)</option>
                                                        @foreach($folders as $f)
                                                            <option value="{{ $f->id }}">{{ $f->name }} ({{ $f->department ?? 'Umum' }})</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div>
                                                    <label class="block text-[11px] font-semibold text-gray-700 mb-1">Kategori Dokumen</label>
                                                    <select name="category_id" required class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                                                        <option value="">Pilih Kategori Dokumen</option>
                                                        @foreach($categories as $category)
                                                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div>
                                                    <label class="block text-[11px] font-semibold text-gray-700 mb-1">Visibilitas Akses</label>
                                                    <select name="visibility" required class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                                                        <option value="internal">Internal (Khusus Unit &amp; Biro Terkait)</option>
                                                        <option value="viewer">Viewer (Dapat Dilihat Publik Viewer)</option>
                                                        <option value="private">Private (Hanya Pemilik)</option>
                                                    </select>
                                                </div>

                                                <div>
                                                    <label class="block text-[11px] font-semibold text-gray-700 mb-1">Bagikan Offline ke Biro Lain</label>
                                                    <div class="max-h-24 overflow-y-auto border border-gray-200 rounded-lg p-2 space-y-1 bg-gray-50/50">
                                                        @foreach($units as $unit)
                                                            <label class="flex items-center gap-2 text-[11px] text-gray-600 hover:text-gray-900 cursor-pointer">
                                                                <input type="checkbox" name="shared_departments[]" value="{{ $unit }}" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                                <span>{{ $unit }}</span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                </div>

                                                <button type="submit" class="w-full py-2 px-3 bg-[#002147] hover:bg-[#001733] text-white rounded-lg text-xs font-bold transition-all shadow-xs flex items-center justify-center gap-1.5 cursor-pointer">
                                                    <svg class="w-3.5 h-3.5 text-[#f1b500]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                                                    <span>Download &amp; Buat Folder di SMART</span>
                                                </button>
                                            </form>
                                        </div>
                                    </div>

                                    <div class="mt-3 pt-2">
                                        <button @click="openForm = !openForm" class="w-full py-1.5 px-3 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-xs font-semibold transition-colors flex items-center justify-center gap-1.5">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                            <span x-text="openForm ? 'Tutup Pengaturan' : 'Buat Folder &amp; Impor Isinya'"></span>
                                        </button>
                                    </div>
                                </div>
                            @empty
                                <div class="col-span-full py-12 text-center text-gray-400 text-xs">
                                    <svg class="w-12 h-12 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>
                                    Tidak ada folder yang ditemukan di akun Google Drive ini.
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <!-- TAB 2: BERKAS SATUAN -->
                    <div x-show="activeTab === 'files'" class="divide-y divide-gray-100">
                        <div class="p-6 bg-slate-50/30">
                            <h4 class="text-sm font-bold text-gray-900">Berkas Satuan di Google Drive</h4>
                            <p class="text-xs text-gray-500 mt-0.5">Pilih berkas individu yang ingin diunduh dan disimpan ke dalam folder SMART yang sudah ada.</p>
                        </div>
                        @forelse($filesFromDrive as $item)
                            <div class="px-6 py-4 flex items-center justify-between gap-4 hover:bg-slate-50/80 transition-colors">
                                <div class="min-w-0">
                                    <div class="font-medium text-gray-900 text-sm truncate">{{ $item['name'] }}</div>
                                    <div class="text-xs text-gray-500 mt-1">{{ $item['mime_type'] }} &bull; {{ number_format($item['size'] / 1024, 1) }} KB</div>
                                </div>
                                @if($item['importable'])
                                    <details class="shrink-0">
                                        <summary class="cursor-pointer text-xs font-semibold px-3 py-1.5 rounded-lg bg-blue-50 text-blue-700 hover:bg-blue-100 transition-colors list-none inline-flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                                            Impor Berkas
                                        </summary>
                                        <form method="POST" action="{{ route('google-drive.import') }}" class="mt-3 p-4 bg-gray-50 rounded-xl space-y-3 w-80 border border-gray-200 shadow-sm">
                                            @csrf
                                            <input type="hidden" name="drive_file_id" value="{{ $item['id'] }}">
                                            <div>
                                                <label class="block text-[11px] font-semibold text-gray-700 mb-1">Kategori</label>
                                                <select name="category_id" required class="w-full rounded-md border-gray-300 text-xs">
                                                    <option value="">Pilih kategori</option>
                                                    @foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-[11px] font-semibold text-gray-700 mb-1">Folder Tujuan</label>
                                                <select name="folder_id" class="w-full rounded-md border-gray-300 text-xs">
                                                    <option value="">(Root Dokumen)</option>
                                                    @foreach($folders as $folder)<option value="{{ $folder->id }}">{{ $folder->name }} ({{ $folder->department ?? 'Umum' }})</option>@endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-[11px] font-semibold text-gray-700 mb-1">Visibilitas</label>
                                                <select name="visibility" required class="w-full rounded-md border-gray-300 text-xs">
                                                    <option value="internal">Internal</option><option value="private">Private</option><option value="viewer">Viewer</option>
                                                </select>
                                            </div>
                                            <fieldset class="text-xs text-gray-600">
                                                <legend class="font-semibold text-gray-700 mb-1 text-[11px]">Bagikan ke biro</legend>
                                                <div class="max-h-24 overflow-y-auto space-y-1 border border-gray-200 p-2 rounded-md bg-white">
                                                    @foreach($units as $unit)
                                                        <label class="flex items-start gap-2 text-[11px]"><input type="checkbox" name="shared_departments[]" value="{{ $unit }}" class="mt-0.5 rounded border-gray-300 text-blue-600"> <span>{{ $unit }}</span></label>
                                                    @endforeach
                                                </div>
                                            </fieldset>
                                            <button class="w-full px-3 py-2 rounded-lg bg-[#002147] text-white text-xs font-bold hover:bg-[#001733] transition-colors">Download &amp; Simpan Lokal</button>
                                        </form>
                                    </details>
                                @else
                                    <span class="text-xs text-gray-400 bg-gray-100 px-2 py-1 rounded-md">Format Belum Didukung</span>
                                @endif
                            </div>
                        @empty
                            <div class="px-6 py-10 text-center text-xs text-gray-500">Tidak ada berkas yang bisa dibaca di akun ini.</div>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
