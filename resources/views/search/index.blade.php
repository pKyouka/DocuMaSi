<x-public-layout>
    <div class="max-w-7xl mx-auto" x-data="{
        previewModal: false,
        selectedDoc: null,
        openPreview(doc) {
            this.selectedDoc = doc;
            this.previewModal = true;
        }
    }">
        <!-- Hero Search Section -->
        <div class="bg-gradient-to-r from-[#173b30] to-[#0f2820] rounded-2xl p-6 sm:p-10 mb-8 shadow-xl relative overflow-hidden">
            <div class="relative z-10 max-w-4xl mx-auto text-center">
                <span class="inline-block bg-[#00875a]/30 text-[#a9d8c1] text-xs font-semibold uppercase tracking-widest px-3 py-1 rounded-full mb-3 border border-[#a9d8c1]/20">
                    Portal Dokumen Publik &amp; Viewer
                </span>
                <h1 class="text-3xl sm:text-4xl font-extrabold text-white mb-2 tracking-tight">Cari Dokumen Resmi</h1>
                <p class="text-[#d5e9df] mb-6 text-sm sm:text-base max-w-2xl mx-auto">
                    Akses dan temukan pedoman, surat keputusan, kalender akademik, dan dokumen publik dari seluruh biro &amp; program studi.
                </p>
                
                <!-- Main Search Bar -->
                <form action="{{ route('search.index') }}" method="GET" class="space-y-3">
                    <div class="flex flex-col sm:flex-row bg-white rounded-xl p-2 shadow-2xl gap-2 border border-gray-200">
                        <div class="flex-1 flex items-center pl-3">
                            <svg class="w-5 h-5 text-gray-400 mr-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama, nomor, atau kata kunci dokumen..." class="w-full border-none focus:ring-0 text-gray-800 placeholder-gray-400 bg-transparent py-2.5 text-sm sm:text-base">
                        </div>
                        <button type="submit" class="bg-[#00875a] hover:bg-[#006c48] text-white rounded-lg px-6 py-2.5 font-semibold text-sm transition-colors shadow-md flex items-center justify-center gap-2">
                            <span>Cari</span>
                        </button>
                    </div>

                    <!-- Filter Row: Kategori, Biro, Tahun, Jenis Dokumen -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-1 text-left">
                        <!-- 1. Kategori -->
                        <div>
                            <select name="category" onchange="this.form.submit()" class="w-full bg-[#112d24] text-[#d5e9df] text-xs rounded-lg border border-[#2b594b] py-2 px-2.5 focus:border-[#a9d8c1] focus:ring-0">
                                <option value="">Semua Kategori</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ request('category') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- 2. Biro / Unit -->
                        <div>
                            <select name="biro" onchange="this.form.submit()" class="w-full bg-[#112d24] text-[#d5e9df] text-xs rounded-lg border border-[#2b594b] py-2 px-2.5 focus:border-[#a9d8c1] focus:ring-0">
                                <option value="">Semua Biro / Unit</option>
                                @foreach($biros as $b)
                                    <option value="{{ $b }}" {{ request('biro') == $b ? 'selected' : '' }}>{{ $b }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- 3. Tahun -->
                        <div>
                            <select name="year" onchange="this.form.submit()" class="w-full bg-[#112d24] text-[#d5e9df] text-xs rounded-lg border border-[#2b594b] py-2 px-2.5 focus:border-[#a9d8c1] focus:ring-0">
                                <option value="">Semua Tahun</option>
                                @foreach($years as $y)
                                    <option value="{{ $y }}" {{ request('year') == $y ? 'selected' : '' }}>Tahun {{ $y }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- 4. Jenis Dokumen -->
                        <div>
                            <select name="type" onchange="this.form.submit()" class="w-full bg-[#112d24] text-[#d5e9df] text-xs rounded-lg border border-[#2b594b] py-2 px-2.5 focus:border-[#a9d8c1] focus:ring-0">
                                <option value="">Semua Format File</option>
                                @foreach($types as $ext => $label)
                                    <option value="{{ $ext }}" {{ request('type') == $ext ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    @if(request()->anyFilled(['q', 'category', 'biro', 'year', 'type']))
                        <div class="pt-1 text-center">
                            <a href="{{ route('search.index') }}" class="inline-flex items-center text-xs text-[#a9d8c1] hover:underline">
                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                Reset Filter
                            </a>
                        </div>
                    @endif
                </form>
            </div>
        </div>

        <!-- Tabel Hasil Dokumen (Diagram Section 5) -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                <div>
                    <h2 class="text-base font-bold text-gray-800">Daftar Dokumen Publik</h2>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Menampilkan {{ $documents->total() }} dokumen dipublikasikan
                    </p>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50/50">
                        <tr>
                            <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Nama Dokumen
                            </th>
                            <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Biro / Unit
                            </th>
                            <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Tanggal
                            </th>
                            <th scope="col" class="px-6 py-3.5 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        @forelse($documents as $doc)
                        <tr class="hover:bg-[#f3faf6] transition-colors">
                            <!-- 1. Nama Dokumen -->
                            <td class="px-6 py-4">
                                <div class="flex items-start">
                                    <div class="w-8 h-8 rounded bg-[#e7f4ee] text-[#00875a] flex items-center justify-center mr-3 shrink-0 mt-0.5">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    </div>
                                    <div>
                                        <button @click="openPreview({{ json_encode([
                                            'uuid' => $doc->uuid,
                                            'name' => $doc->name,
                                            'document_number' => $doc->document_number,
                                            'department' => $doc->department ?? 'Umum',
                                            'category' => $doc->category->name ?? '-',
                                            'date' => $doc->effective_display_date ? $doc->effective_display_date->format('d F Y') : '-',
                                            'description' => $doc->description ?? 'Tidak ada deskripsi tambahan.',
                                            'has_file' => (bool)$doc->latestVersion,
                                            'is_downloadable' => (bool)$doc->is_downloadable,
                                            'preview_url' => route('documents.preview', $doc),
                                            'download_url' => route('documents.download', $doc),
                                            'detail_url' => route('documents.show', $doc),
                                        ]) }})" class="text-sm font-semibold text-gray-900 hover:text-[#00875a] text-left transition-colors">
                                            {{ $doc->name }}
                                        </button>
                                        <div class="flex items-center gap-2 mt-1 text-xs text-gray-500">
                                            @if($doc->document_number)
                                                <span>No: {{ $doc->document_number }}</span>
                                                <span>&bull;</span>
                                            @endif
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-600">
                                                {{ $doc->category->name ?? '-' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- 2. Biro -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 font-medium">
                                {{ $doc->department ?? '-' }}
                            </td>

                            <!-- 3. Tanggal (display_date sesuai diagram Section 4) -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                {{ $doc->effective_display_date ? $doc->effective_display_date->format('d F Y') : '-' }}
                            </td>

                            <!-- 4. Aksi [Lihat] -->
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                <div class="inline-flex items-center gap-2">
                                    <button @click="openPreview({{ json_encode([
                                        'uuid' => $doc->uuid,
                                        'name' => $doc->name,
                                        'document_number' => $doc->document_number,
                                        'department' => $doc->department ?? 'Umum',
                                        'category' => $doc->category->name ?? '-',
                                        'date' => $doc->effective_display_date ? $doc->effective_display_date->format('d F Y') : '-',
                                        'description' => $doc->description ?? 'Tidak ada deskripsi tambahan.',
                                        'has_file' => (bool)$doc->latestVersion,
                                        'is_downloadable' => (bool)$doc->is_downloadable,
                                        'preview_url' => route('documents.preview', $doc),
                                        'download_url' => route('documents.download', $doc),
                                        'detail_url' => route('documents.show', $doc),
                                    ]) }})" class="inline-flex items-center px-3 py-1.5 bg-[#e7f4ee] text-[#006c48] hover:bg-[#00875a] hover:text-white rounded-md text-xs font-semibold transition-colors shadow-sm">
                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                        Lihat
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-sm text-gray-500">
                                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 mb-3">
                                    <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                </div>
                                <p class="font-medium text-gray-700">Tidak ada dokumen ditemukan</p>
                                <p class="text-xs text-gray-500 mt-1">Coba gunakan filter atau kata kunci yang berbeda.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            @if($documents->hasPages())
            <div class="px-6 py-4 border-t border-gray-100 bg-gray-50">
                {{ $documents->links() }}
            </div>
            @endif
        </div>

        <!-- Modal Detail & Preview (Diagram Section 5) -->
        <div x-show="previewModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak>
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div @click="previewModal = false" class="fixed inset-0 transition-opacity bg-gray-900 bg-opacity-60" aria-hidden="true"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full border border-gray-100">
                    <template x-if="selectedDoc">
                        <div>
                            <!-- Header Modal -->
                            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                                <div class="pr-6">
                                    <span class="inline-block px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-100 text-blue-800 mb-1" x-text="selectedDoc.category"></span>
                                    <h3 class="text-lg font-bold text-gray-900 leading-snug" x-text="selectedDoc.name"></h3>
                                </div>
                                <button @click="previewModal = false" class="text-gray-400 hover:text-gray-600 p-1.5 rounded-lg hover:bg-gray-200 transition-colors">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </div>

                            <!-- Detail Content (Diagram Section 5: nama dokumen, biro, tanggal, deskripsi, preview) -->
                            <div class="p-6 space-y-5">
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 bg-gray-50 p-4 rounded-xl border border-gray-100 text-sm">
                                    <div>
                                        <div class="text-xs text-gray-500 uppercase tracking-wider font-semibold">Biro / Unit</div>
                                        <div class="text-sm font-semibold text-gray-900 mt-0.5" x-text="selectedDoc.department"></div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 uppercase tracking-wider font-semibold">Tanggal Tampil</div>
                                        <div class="text-sm font-semibold text-gray-900 mt-0.5" x-text="selectedDoc.date"></div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 uppercase tracking-wider font-semibold">Nomor Dokumen</div>
                                        <div class="text-sm font-semibold text-gray-900 mt-0.5" x-text="selectedDoc.document_number || '-'"></div>
                                    </div>
                                </div>

                                <div>
                                    <div class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-1">Deskripsi Dokumen</div>
                                    <p class="text-sm text-gray-700 bg-white p-3 rounded-lg border border-gray-200" x-text="selectedDoc.description"></p>
                                </div>

                                <!-- File Preview Box -->
                                <div>
                                    <div class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-2">Pratinjau Berkas</div>
                                    <template x-if="selectedDoc.has_file">
                                        <div class="border border-gray-300 rounded-xl overflow-hidden bg-gray-100">
                                            <iframe :src="selectedDoc.preview_url" class="w-full h-96 border-none" title="Pratinjau Dokumen"></iframe>
                                        </div>
                                    </template>
                                    <template x-if="!selectedDoc.has_file">
                                        <div class="text-center py-8 bg-gray-50 rounded-xl border border-dashed border-gray-300 text-sm text-gray-500">
                                            Berkas fisik belum diunggah untuk dokumen ini.
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- Footer Modal with Download Button if Allowed (Diagram Section 5) -->
                            <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <template x-if="!selectedDoc.is_downloadable">
                                        <span class="inline-flex items-center text-xs text-amber-700 bg-amber-50 px-2.5 py-1 rounded-md border border-amber-200">
                                            <svg class="w-3.5 h-3.5 mr-1 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                            Hanya pratinjau (Unduhan dinonaktifkan oleh administrator)
                                        </span>
                                    </template>
                                </div>
                                <div class="flex items-center gap-2">
                                    <a :href="selectedDoc.detail_url" class="px-4 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                        Halaman Penuh
                                    </a>
                                    <template x-if="selectedDoc.has_file && selectedDoc.is_downloadable">
                                        <a :href="selectedDoc.download_url" class="inline-flex items-center px-4 py-2 text-xs font-semibold text-white bg-[#00875a] hover:bg-[#006c48] rounded-lg shadow-sm transition-colors">
                                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                            Unduh Dokumen
                                        </a>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</x-public-layout>
