<x-app-layout>
    <div class="py-4" x-data="{
        showAddModal: false,
        selectedFile: { id: '', name: '', mime_type: '', size: '' },
        openAddModal(file) {
            this.selectedFile = file;
            this.showAddModal = true;
        },
        showFolderModal: false,
        selectedFolder: { id: '', name: '' },
        openFolderModal(folder) {
            this.selectedFolder = folder;
            this.showFolderModal = true;
        }
    }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Header Halaman -->
            <div class="flex flex-wrap items-center justify-between gap-4 pb-4 border-b border-gray-200">
                <div>
                    <h2 class="font-bold text-2xl text-gray-900 leading-tight">Integrasi Google Drive</h2>
                    <p class="text-sm text-gray-500 mt-1">Eksplorasi folder &amp; berkas Google Drive secara live stream. Tambahkan berkas pilihan ke sistem offline SMART.</p>
                </div>
                @if(auth()->user()->google_drive_access_token)
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-1.5 rounded-lg flex items-center gap-1.5 shadow-2xs">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            Stream Aktif: {{ auth()->user()->google_drive_account_email }}
                        </span>
                        <form method="POST" action="{{ route('google-drive.disconnect') }}" onsubmit="return confirm('Yakin ingin memutuskan koneksi akun Google Drive ini?');">
                            @csrf
                            <button type="submit" class="text-xs text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100 border border-red-200 px-3 py-1.5 rounded-lg font-medium transition-colors cursor-pointer">
                                Putuskan / Ganti Akun
                            </button>
                        </form>
                    </div>
                @endif
            </div>
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
                    <div class="w-16 h-16 bg-amber-50/70 rounded-2xl flex items-center justify-center mx-auto mb-4 ring-8 ring-amber-50/50 shadow-2xs">
                        <x-google-drive-icon class="w-10 h-10" />
                    </div>
                    <h3 class="text-xl font-bold text-gray-900">Hubungkan Akun Google Drive</h3>
                    <p class="text-sm text-gray-500 mt-2 leading-relaxed max-w-md mx-auto">
                        Jelajahi berkas Google Drive secara live stream tanpa membebani disk server. Berkas baru akan disimpan offline ke SMART saat Anda mengklik <strong>"Add ke Sistem"</strong>.
                    </p>

                    <div class="mt-6">
                        <a href="{{ route('google-drive.connect') }}" class="inline-flex items-center gap-3 px-6 py-3 rounded-xl bg-[#002147] hover:bg-[#001733] text-white font-semibold shadow-md hover:shadow-lg transition-all text-sm group">
                            <svg class="w-5 h-5" viewBox="0 0 24 24">
                                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                            </svg>
                            <span>Masuk / Hubungkan Google Drive</span>
                        </a>
                    </div>

                    <p class="text-[11px] text-gray-400 mt-4">Izin akses dibatasi hanya untuk membaca berkas (Read-Only).</p>
                </div>
            @else
                <!-- Cloud Stream Explorer Bar -->
                <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-xs">
                    <!-- Navigasi Breadcrumb & Status Bar -->
                    <div class="px-6 py-4 border-b border-gray-200 bg-slate-50/70 flex flex-wrap items-center justify-between gap-4">
                        <div class="flex items-center gap-2 text-xs">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-blue-100 text-blue-800 font-bold">
                                <svg class="w-3.5 h-3.5 text-blue-600 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                Cloud Stream Mode
                            </span>

                            <div class="flex items-center gap-1.5 text-gray-500 font-medium">
                                <a href="{{ route('google-drive.index') }}" class="hover:text-blue-600 hover:underline flex items-center gap-1 {{ $folderId === 'root' ? 'font-bold text-gray-900' : '' }}">
                                    <svg class="w-4 h-4 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>
                                    Drive Saya
                                </a>

                                @if($folderId !== 'root')
                                    <span class="text-gray-400">/</span>
                                    <span class="font-bold text-gray-900 truncate max-w-xs" title="{{ $currentFolder['name'] }}">{{ $currentFolder['name'] }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            @if($folderId !== 'root')
                                <button type="button" @click="openFolderModal({ id: '{{ $folderId }}', name: '{{ addslashes($currentFolder['name']) }}' })" class="px-3 py-1.5 bg-[#002147] hover:bg-[#001733] text-white rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 shadow-2xs cursor-pointer" title="Unduh dan import folder ini dan seluruh isinya ke SMART">
                                    <svg class="w-3.5 h-3.5 text-[#f1b500]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                    <span>Import Folder Ini &amp; Isinya</span>
                                </button>

                                <a href="{{ route('google-drive.index', ['folder_id' => $currentFolder['parent_id'] ?? 'root']) }}" class="px-3 py-1.5 bg-white border border-gray-300 hover:bg-gray-50 rounded-lg text-xs font-semibold text-gray-700 transition-colors flex items-center gap-1.5 shadow-2xs">
                                    <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                                    <span>Kembali ke Folder Atas</span>
                                </a>
                            @endif

                            <a href="{{ route('google-drive.index', ['folder_id' => $folderId]) }}" class="px-3 py-1.5 bg-white border border-gray-300 hover:bg-gray-50 rounded-lg text-xs font-semibold text-blue-600 transition-colors flex items-center gap-1.5 shadow-2xs">
                                <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                <span>Refresh Stream</span>
                            </a>
                        </div>
                    </div>

                    <!-- Penjelasan Mode Stream -->
                    <div class="px-6 py-3 bg-blue-50/50 border-b border-blue-100 flex items-center justify-between text-xs text-blue-900">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>Semua berkas di bawah ini sedang <strong>di-stream langsung</strong> dari Google Drive. Server lokal tidak menyimpan apa pun sebelum Anda mengklik tombol <strong>"Add ke Sistem"</strong>.</span>
                        </div>
                    </div>

                    <div class="p-6 space-y-8">
                        <!-- BAGIAN 1: FOLDER (STREAM EXPLORER) -->
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-[#f1b500]" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>
                                    Folder Google Drive ({{ $foldersFromDrive->count() }})
                                </h3>
                                <span class="text-[11px] text-gray-400">Klik folder untuk menjelajahi isinya secara stream</span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                                @forelse($foldersFromDrive as $folderItem)
                                    <div class="border border-gray-200 hover:border-blue-400 rounded-xl p-3.5 transition-all bg-white hover:shadow-xs group flex flex-col justify-between" x-data="{ openSyncFolder: false }">
                                        <div class="flex items-start gap-3">
                                            <a href="{{ route('google-drive.index', ['folder_id' => $folderItem['id']]) }}" class="w-9 h-9 rounded-lg bg-amber-50 text-[#f1b500] flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>
                                            </a>
                                            <div class="min-w-0 flex-1">
                                                <a href="{{ route('google-drive.index', ['folder_id' => $folderItem['id']]) }}" class="font-bold text-xs text-gray-900 group-hover:text-blue-600 truncate block" title="{{ $folderItem['name'] }}">
                                                    {{ $folderItem['name'] }}
                                                </a>
                                                <p class="text-[10px] text-gray-400 mt-0.5">Folder Drive</p>
                                            </div>
                                        </div>

                                        <div class="mt-3 pt-2.5 border-t border-gray-100 flex items-center justify-between text-[11px]">
                                            <a href="{{ route('google-drive.index', ['folder_id' => $folderItem['id']]) }}" class="text-blue-600 hover:text-blue-800 font-semibold inline-flex items-center gap-1">
                                                <span>Buka Stream</span>
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                            </a>

                                            <button type="button" @click="openFolderModal({ id: '{{ $folderItem['id'] }}', name: '{{ addslashes($folderItem['name']) }}' })" class="text-blue-700 bg-blue-50 hover:bg-blue-100 font-semibold text-[11px] px-2.5 py-1 rounded-lg border border-blue-200 transition-colors flex items-center gap-1 cursor-pointer" title="Import folder ini beserta seluruh berkas di dalamnya ke SMART">
                                                <svg class="w-3 h-3 text-[#f1b500]" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>
                                                <span>Import &amp; Isinya</span>
                                            </button>
                                        </div>
                                    </div>
                                @empty
                                    <div class="col-span-full py-6 text-center text-gray-400 text-xs bg-slate-50/50 rounded-xl border border-dashed border-gray-200">
                                        Tidak ada sub-folder di direktori ini.
                                    </div>
                                @endforelse
                            </div>
                        </div>

                        <!-- BAGIAN 2: BERKAS (STREAM & SELECTIVE ADD) -->
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    Berkas di Google Drive ({{ $filesFromDrive->count() }})
                                </h3>
                                <span class="text-[11px] text-gray-400">Stream preview langsung atau klik Add ke Sistem untuk simpan offline</span>
                            </div>

                            <div class="border border-gray-200 rounded-xl overflow-hidden bg-white shadow-2xs">
                                <table class="min-w-full divide-y divide-gray-200 text-xs">
                                    <thead class="bg-slate-50 text-gray-600 font-semibold">
                                        <tr>
                                            <th class="px-4 py-3 text-left">Nama Berkas</th>
                                            <th class="px-4 py-3 text-left">Tipe &amp; Ukuran</th>
                                            <th class="px-4 py-3 text-center">Status di SMART</th>
                                            <th class="px-4 py-3 text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @forelse($filesFromDrive as $file)
                                            @php
                                                $isImported = in_array($file['id'], $importedDriveIds);
                                            @endphp
                                            <tr class="hover:bg-slate-50/80 transition-colors">
                                                <td class="px-4 py-3 font-medium text-gray-900 max-w-xs md:max-w-md truncate" title="{{ $file['name'] }}">
                                                    <div class="flex items-center gap-2.5">
                                                        <div class="w-7 h-7 rounded bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                                        </div>
                                                        <span class="truncate">{{ $file['name'] }}</span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-gray-500 whitespace-nowrap">
                                                    <div>{{ number_format($file['size'] / 1024, 1) }} KB</div>
                                                    <div class="text-[10px] text-gray-400 truncate max-w-[150px]">{{ $file['mime_type'] }}</div>
                                                </td>
                                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                                    @if($isImported)
                                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                            <svg class="w-3 h-3 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                            Tersimpan Offline
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-medium bg-gray-100 text-gray-600 border border-gray-200">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-blue-400"></span>
                                                            Stream Only
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                                    <div class="flex items-center justify-end gap-2">
                                                        <!-- Tombol Stream Preview (Tidak Men-download ke Server) -->
                                                        <a href="{{ route('google-drive.stream', $file['id']) }}" target="_blank" rel="noopener noreferrer" class="px-2.5 py-1.5 bg-gray-50 hover:bg-gray-100 text-gray-700 border border-gray-200 rounded-lg text-xs font-semibold inline-flex items-center gap-1 transition-colors" title="Buka stream preview berkas langsung dari Google Drive">
                                                            <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                            <span>Stream Preview</span>
                                                        </a>

                                                        <!-- Tombol Add ke Sistem (Simpan Offline) -->
                                                        @if($file['importable'])
                                                            <button type="button" @click="openAddModal({ id: '{{ $file['id'] }}', name: '{{ addslashes($file['name']) }}', mime_type: '{{ $file['mime_type'] }}', size: '{{ number_format($file['size'] / 1024, 1) }} KB' })" class="px-3 py-1.5 bg-[#002147] hover:bg-[#001733] text-white rounded-lg text-xs font-bold inline-flex items-center gap-1.5 transition-colors shadow-2xs cursor-pointer">
                                                                <svg class="w-3.5 h-3.5 text-[#f1b500]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                                                <span>{{ $isImported ? 'Add Ulang' : 'Add ke Sistem' }}</span>
                                                            </button>
                                                        @else
                                                            <span class="text-[11px] text-gray-400 bg-gray-50 px-2 py-1 rounded border border-gray-100">Format Docs/Sheets</span>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="px-4 py-8 text-center text-gray-400 text-xs">
                                                    Tidak ada berkas di dalam direktori folder ini.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MODAL POPUP: ADD KE SISTEM (OFFLINE) -->
                <div x-show="showAddModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                        <div x-show="showAddModal" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs transition-opacity" @click="showAddModal = false"></div>

                        <div x-show="showAddModal" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="relative inline-block w-full max-w-lg p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-xl rounded-2xl border border-gray-200">
                            
                            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-bold text-gray-900" id="modal-title">Tambahkan ke Sistem (Jadikan Offline)</h3>
                                        <p class="text-[11px] text-gray-500">Berkas akan diunduh dan disimpan fisik di server SMART.</p>
                                    </div>
                                </div>
                                <button @click="showAddModal = false" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>

                            <form method="POST" action="{{ route('google-drive.import') }}" class="mt-4 space-y-4">
                                @csrf
                                <input type="hidden" name="drive_file_id" :value="selectedFile.id">

                                <!-- Info Berkas -->
                                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/60 text-xs">
                                    <div class="font-bold text-gray-900 truncate" x-text="selectedFile.name"></div>
                                    <div class="text-gray-500 text-[11px] mt-0.5" x-text="selectedFile.mime_type + ' • ' + selectedFile.size"></div>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Nama Dokumen di SMART</label>
                                    <input type="text" name="document_name" :value="selectedFile.name.replace(/\.[^/.]+$/, '')" required class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Kategori Dokumen *</label>
                                        <select name="category_id" required class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                                            <option value="">Pilih Kategori</option>
                                            @foreach($categories as $category)
                                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Folder Tujuan di SMART</label>
                                        <select name="folder_id" class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                                            <option value="">(Root Direktori Utama)</option>
                                            @foreach($folders as $folder)
                                                <option value="{{ $folder->id }}">{{ $folder->name }} ({{ $folder->department ?? 'Umum' }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Visibilitas Akses</label>
                                    <select name="visibility" required class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                                        <option value="internal">Internal (Khusus Unit &amp; Biro Terkait)</option>
                                        <option value="viewer">Viewer (Dapat Dilihat Publik Viewer)</option>
                                        <option value="private">Private (Hanya Pemilik)</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Bagikan ke Biro Lain (Opsional)</label>
                                    <div class="max-h-24 overflow-y-auto border border-gray-200 rounded-lg p-2 space-y-1 bg-gray-50/50">
                                        @foreach($units as $unit)
                                            <label class="flex items-center gap-2 text-[11px] text-gray-600 hover:text-gray-900 cursor-pointer">
                                                <input type="checkbox" name="shared_departments[]" value="{{ $unit }}" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                <span>{{ $unit }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                                    <button type="button" @click="showAddModal = false" class="px-4 py-2 bg-white hover:bg-gray-50 border border-gray-300 text-gray-700 rounded-lg text-xs font-semibold transition-colors">
                                        Batal
                                    </button>
                                    <button type="submit" class="px-5 py-2 bg-[#002147] hover:bg-[#001733] text-white rounded-lg text-xs font-bold transition-all shadow-xs flex items-center gap-1.5 cursor-pointer">
                                        <svg class="w-4 h-4 text-[#f1b500]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                        <span>Unduh &amp; Simpan Offline ke SMART</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- MODAL POPUP: IMPORT FOLDER & SELURUH ISINYA -->
                <div x-show="showFolderModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-folder-title" role="dialog" aria-modal="true">
                    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                        <div x-show="showFolderModal" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs transition-opacity" @click="showFolderModal = false"></div>

                        <div x-show="showFolderModal" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="relative inline-block w-full max-w-lg p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-xl rounded-2xl border border-gray-200">
                            
                            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-bold text-gray-900" id="modal-folder-title">Import Folder Google Drive ke SMART</h3>
                                        <p class="text-[11px] text-gray-500">Folder dan seluruh berkas di dalamnya akan diunduh dan disimpan offline ke SMART.</p>
                                    </div>
                                </div>
                                <button @click="showFolderModal = false" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>

                            <form method="POST" action="{{ route('google-drive.import-folder') }}" class="mt-4 space-y-4">
                                @csrf
                                <input type="hidden" name="drive_folder_id" :value="selectedFolder.id">

                                <!-- Info Folder -->
                                <div class="p-3 bg-amber-50/60 rounded-xl border border-amber-200/60 text-xs">
                                    <div class="font-bold text-gray-900 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>
                                        <span x-text="selectedFolder.name"></span>
                                    </div>
                                    <div class="text-amber-800 text-[11px] mt-1">Seluruh berkas &amp; sub-folder di dalamnya akan diunduh dan diarsipkan ke SMART.</div>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Nama Folder di SMART</label>
                                    <input type="text" name="folder_name" :value="selectedFolder.name" required class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Kategori Dokumen *</label>
                                        <select name="category_id" required class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                                            <option value="">Pilih Kategori</option>
                                            @foreach($categories as $category)
                                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Simpan di Folder SMART</label>
                                        <select name="parent_id" class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                                            <option value="">(Root Direktori Utama)</option>
                                            @foreach($folders as $folder)
                                                <option value="{{ $folder->id }}">{{ $folder->name }} ({{ $folder->department ?? 'Umum' }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Visibilitas Berkas</label>
                                    <select name="visibility" required class="w-full text-xs rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                                        <option value="internal">Internal (Khusus Unit &amp; Biro Terkait)</option>
                                        <option value="viewer">Viewer (Dapat Dilihat Publik Viewer)</option>
                                        <option value="private">Private (Hanya Pemilik)</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Bagikan ke Biro Lain (Opsional)</label>
                                    <div class="max-h-24 overflow-y-auto border border-gray-200 rounded-lg p-2 space-y-1 bg-gray-50/50">
                                        @foreach($units as $unit)
                                            <label class="flex items-center gap-2 text-[11px] text-gray-600 hover:text-gray-900 cursor-pointer">
                                                <input type="checkbox" name="shared_departments[]" value="{{ $unit }}" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                <span>{{ $unit }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                                    <button type="button" @click="showFolderModal = false" class="px-4 py-2 bg-white hover:bg-gray-50 border border-gray-300 text-gray-700 rounded-lg text-xs font-semibold transition-colors">
                                        Batal
                                    </button>
                                    <button type="submit" class="px-5 py-2 bg-[#002147] hover:bg-[#001733] text-white rounded-lg text-xs font-bold transition-all shadow-xs flex items-center gap-1.5 cursor-pointer">
                                        <svg class="w-4 h-4 text-[#f1b500]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                        <span>Unduh Folder &amp; Isinya ke SMART</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
