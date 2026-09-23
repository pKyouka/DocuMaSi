<x-app-layout>
    <div class="max-w-4xl mx-auto">
        <div class="mb-6">
            <div class="flex items-center gap-2 mb-1.5">
                <a href="{{ route('folders.index') }}" class="text-xs font-bold text-[#002147] hover:text-[#f1b500] transition-colors flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    <span>Kembali ke File Explorer</span>
                </a>
            </div>
            <h2 class="text-2xl font-black text-[#002147] tracking-tight">Upload Dokumen Baru</h2>
            <p class="text-xs text-gray-500 mt-1">Lengkapi form berikut untuk mengunggah dokumen baru ke folder.</p>
        </div>

        <div class="bg-white rounded-2xl shadow-xs border border-gray-200 overflow-hidden">
            <form action="{{ route('documents.store') }}" method="POST" enctype="multipart/form-data" class="divide-y divide-gray-100" x-data="{ fileName: '', fileSize: '' }">
                @csrf
                
                <div class="p-6 md:p-8 space-y-8">
                    <!-- File Upload Section -->
                    <div>
                        <h3 class="text-sm font-bold text-[#002147] uppercase tracking-wider mb-4 border-b border-gray-100 pb-2.5 flex items-center">
                            <span class="w-6 h-6 rounded-full bg-[#002147] text-white text-xs font-bold inline-flex items-center justify-center mr-2 shadow-xs">1</span>
                            File Dokumen <span class="text-red-500 ml-1">*</span>
                        </h3>
                        <div @click="$refs.fileInput.click()" class="mt-1 flex flex-col items-center justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-xl hover:border-[#002147] hover:bg-[#002147]/5 transition-colors bg-gray-50/60 cursor-pointer group">
                            <div class="space-y-2 text-center">
                                <svg class="mx-auto h-10 w-10 text-gray-400 group-hover:text-[#002147] transition-colors" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>

                                <template x-if="!fileName">
                                    <div>
                                        <p class="text-xs font-bold text-[#002147] hover:underline">Klik untuk memilih berkas dari komputer</p>
                                        <p class="text-[11px] text-gray-400 mt-0.5">PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, JPG, PNG, ZIP hingga 20MB</p>
                                    </div>
                                </template>

                                <template x-if="fileName">
                                    <div class="bg-emerald-50 border border-emerald-200 rounded-lg px-4 py-2 text-xs text-emerald-800 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span class="font-bold" x-text="fileName"></span>
                                        <span class="text-emerald-600" x-text="'(' + fileSize + ')'"></span>
                                    </div>
                                </template>

                                <input x-ref="fileInput" id="file-upload" name="file" type="file" class="hidden" required @change="if ($event.target.files.length) { fileName = $event.target.files[0].name; fileSize = ($event.target.files[0].size / 1024).toFixed(1) + ' KB'; }">
                            </div>
                        </div>
                        @error('file') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <!-- Metadata Section (Diagram Section 3) -->
                    <div>
                        <h3 class="text-sm font-bold text-[#002147] uppercase tracking-wider mb-4 border-b border-gray-100 pb-2.5 flex items-center">
                            <span class="w-6 h-6 rounded-full bg-[#002147] text-white text-xs font-bold inline-flex items-center justify-center mr-2 shadow-xs">2</span>
                            Metadata Dokumen
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-4">
                            
                            <!-- Folder Tujuan -->
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-gray-700 mb-1">Folder Lokasi Dokumen</label>
                                <select name="folder_id" class="w-full text-xs rounded-lg border-gray-300 shadow-xs focus:border-[#002147] focus:ring-[#002147] p-2.5">
                                    <option value="">Root Dokumen (Tanpa Folder)</option>
                                    @foreach($folders as $f)
                                        <option value="{{ $f->id }}" {{ (old('folder_id', $selectedFolderId) == $f->id) ? 'selected' : '' }}>
                                            {{ $f->department ? "[{$f->department}] " : "" }}{{ $f->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-[11px] text-gray-400">Pilih folder/subfolder tempat dokumen akan diletakkan.</p>
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-gray-700 mb-1">Nama Dokumen <span class="text-red-500">*</span></label>
                                <input type="text" name="name" value="{{ old('name') }}" required class="w-full text-xs rounded-lg border-gray-300 shadow-xs focus:border-[#002147] focus:ring-[#002147] p-2.5" placeholder="Misal: Pedoman Akademik 2026">
                                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">Kategori <span class="text-red-500">*</span></label>
                                <select name="category_id" required class="w-full text-xs rounded-lg border-gray-300 shadow-xs focus:border-[#002147] focus:ring-[#002147] p-2.5">
                                    <option value="">Pilih Kategori</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                @error('category_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">Nomor Dokumen (Opsional)</label>
                                <input type="text" name="document_number" value="{{ old('document_number') }}" class="w-full text-xs rounded-lg border-gray-300 shadow-xs focus:border-[#002147] focus:ring-[#002147] p-2.5" placeholder="Contoh: SK/001/PSTI/2026">
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-gray-700 mb-1">Deskripsi Dokumen</label>
                                <textarea name="description" rows="3" class="w-full text-xs rounded-lg border-gray-300 shadow-xs focus:border-[#002147] focus:ring-[#002147] p-2.5" placeholder="Ringkasan atau keterangan isi dokumen...">{{ old('description') }}</textarea>
                            </div>

                        </div>
                    </div>

                    <!-- 3. Atur Visibilitas & Tanggal Tampil (Diagram Section 3 & 4) -->
                    <div>
                        <h3 class="text-sm font-bold text-[#002147] uppercase tracking-wider mb-4 border-b border-gray-100 pb-2.5 flex items-center">
                            <span class="w-6 h-6 rounded-full bg-[#002147] text-white text-xs font-bold inline-flex items-center justify-center mr-2 shadow-xs">3</span>
                            Pengaturan Tampil &amp; Visibilitas
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-4">
                            
                            <!-- Visibilitas (Diagram Section 3: Internal, Tampil ke Viewer, Private) -->
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">Visibilitas Dokumen <span class="text-red-500">*</span></label>
                                <div class="space-y-2 mt-2">
                                    <label class="flex items-center p-3 border rounded-xl cursor-pointer hover:bg-slate-50 transition-colors {{ old('visibility', 'viewer') === 'viewer' ? 'border-[#002147] bg-[#002147]/5' : 'border-gray-200' }}">
                                        <input type="radio" name="visibility" value="viewer" {{ old('visibility', 'viewer') === 'viewer' ? 'checked' : '' }} class="h-4 w-4 text-[#002147] focus:ring-[#002147] border-gray-300">
                                        <div class="ml-3">
                                            <span class="block text-xs font-bold text-gray-900">Tampil ke Viewer</span>
                                            <span class="block text-[11px] text-gray-500">Dapat diakses oleh publik sesuai izin biro.</span>
                                        </div>
                                    </label>

                                    <label class="flex items-center p-3 border rounded-xl cursor-pointer hover:bg-slate-50 transition-colors {{ old('visibility') === 'internal' ? 'border-[#002147] bg-[#002147]/5' : 'border-gray-200' }}">
                                        <input type="radio" name="visibility" value="internal" {{ old('visibility') === 'internal' ? 'checked' : '' }} class="h-4 w-4 text-[#002147] focus:ring-[#002147] border-gray-300">
                                        <div class="ml-3">
                                            <span class="block text-xs font-bold text-gray-900">Internal</span>
                                            <span class="block text-[11px] text-gray-500">Hanya dapat diakses oleh pengguna yang login.</span>
                                        </div>
                                    </label>

                                    <label class="flex items-center p-3 border rounded-xl cursor-pointer hover:bg-slate-50 transition-colors {{ old('visibility') === 'private' ? 'border-[#002147] bg-[#002147]/5' : 'border-gray-200' }}">
                                        <input type="radio" name="visibility" value="private" {{ old('visibility') === 'private' ? 'checked' : '' }} class="h-4 w-4 text-[#002147] focus:ring-[#002147] border-gray-300">
                                        <div class="ml-3">
                                            <span class="block text-xs font-bold text-gray-900">Private</span>
                                            <span class="block text-[11px] text-gray-500">Hanya pengelola unit dan administrator.</span>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Tanggal Tampil (Diagram Section 4) -->
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Tanggal Tampil (display_date) <span class="text-red-500">*</span></label>
                                    <input type="date" name="display_date" value="{{ old('display_date', date('Y-m-d')) }}" required class="w-full text-xs rounded-lg border-gray-300 shadow-xs focus:border-[#002147] focus:ring-[#002147] p-2.5">
                                    <p class="mt-1 text-[11px] text-gray-400">Tanggal inilah yang akan dilihat user di halaman viewer.</p>
                                </div>

                                <div class="pt-4 border-t border-gray-100">
                                    <div class="flex items-start">
                                        <div class="flex items-center h-5">
                                            <input type="hidden" name="is_downloadable" value="0">
                                            <input id="is_downloadable" name="is_downloadable" value="1" type="checkbox" {{ old('is_downloadable', true) ? 'checked' : '' }} class="focus:ring-[#002147] h-4 w-4 text-[#002147] border-gray-300 rounded">
                                        </div>
                                        <div class="ml-3 text-xs">
                                            <label for="is_downloadable" class="font-bold text-gray-700">Izinkan Unduhan</label>
                                            <p class="text-[11px] text-gray-400">Bila tidak dicentang, berkas hanya dapat dibaca (pratinjau) tanpa tombol unduh.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                </div>

                <div class="bg-slate-50 px-6 py-4 flex items-center justify-end gap-3 border-t border-gray-100">
                    <a href="{{ route('folders.index') }}" class="px-4 py-2 border border-gray-300 shadow-xs text-xs font-semibold rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                        Batal
                    </a>
                    <button type="submit" class="px-5 py-2 border border-transparent shadow-xs text-xs font-bold rounded-lg text-white bg-[#002147] hover:bg-[#001733] transition-colors">
                        Simpan &amp; Publikasikan
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
            </form>
        </div>
    </div>
</x-app-layout>
