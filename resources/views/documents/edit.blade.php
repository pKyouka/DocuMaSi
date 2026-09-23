<x-app-layout>
    <div class="max-w-5xl mx-auto">
        <div class="mb-6 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
            <div>
                <div class="flex items-center gap-2 mb-1.5">
                    <a href="{{ route('documents.show', $document) }}" class="text-xs font-bold text-[#002147] hover:text-[#f1b500] transition-colors flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                        <span>Batal &amp; Kembali ke Detail</span>
                    </a>
                </div>
                <h2 class="text-2xl font-black text-[#002147] tracking-tight">Edit Metadata Dokumen</h2>
                <p class="text-xs text-gray-500 mt-1">Perbarui informasi dan metadata untuk: <span class="font-bold text-gray-800">{{ $document->name }}</span></p>
            </div>
            
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-[#002147]/5 text-[#002147] border border-[#002147]/10 shrink-0 self-start sm:self-auto">
                <span class="w-1.5 h-1.5 rounded-full bg-[#f1b500] mr-1.5"></span>
                Status: {{ $document->status_label }}
            </span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl shadow-xs border border-gray-200 overflow-hidden">
                    <form action="{{ route('documents.update', $document) }}" method="POST" class="divide-y divide-gray-100">
                        @csrf
                        @method('PUT')
                        
                        <div class="p-6 sm:p-7 space-y-7">
                            <!-- Metadata Section -->
                            <div>
                                <h3 class="text-xs font-bold text-[#002147] uppercase tracking-wider mb-4 border-b border-gray-100 pb-2.5 flex items-center gap-2">
                                    <span class="w-1.5 h-3.5 bg-[#f1b500] rounded-full"></span>
                                    Informasi Utama
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-4">
                                    
                                    <!-- Folder Lokasi -->
                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-bold text-gray-700 mb-1">Folder Lokasi Dokumen</label>
                                        <select name="folder_id" class="w-full text-xs rounded-lg border-gray-300 shadow-xs focus:border-[#002147] focus:ring-[#002147] p-2.5">
                                            <option value="">Root Dokumen (Tanpa Folder)</option>
                                            @foreach($folders as $f)
                                                <option value="{{ $f->id }}" {{ (old('folder_id', $document->folder_id) == $f->id) ? 'selected' : '' }}>
                                                    {{ $f->department ? "[{$f->department}] " : "" }}{{ $f->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-bold text-gray-700 mb-1">Nama Dokumen <span class="text-red-500">*</span></label>
                                        <input type="text" name="name" value="{{ old('name', $document->name) }}" required class="w-full text-xs rounded-lg border-gray-300 shadow-xs focus:border-[#002147] focus:ring-[#002147] p-2.5">
                                        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 mb-1">Nomor Dokumen</label>
                                        <input type="text" name="document_number" value="{{ old('document_number', $document->document_number) }}" class="w-full text-xs rounded-lg border-gray-300 shadow-xs focus:border-[#002147] focus:ring-[#002147] p-2.5">
                                        @error('document_number') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 mb-1">Kategori <span class="text-red-500">*</span></label>
                                        <select name="category_id" required class="w-full text-xs rounded-lg border-gray-300 shadow-xs focus:border-[#002147] focus:ring-[#002147] p-2.5">
                                            <option value="">Pilih Kategori</option>
                                            @foreach($categories as $category)
                                                <option value="{{ $category->id }}" {{ old('category_id', $document->category_id) == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('category_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>

                                </div>
                            </div>

                            <!-- Academic Info Section -->
                            <div>
                                <h3 class="text-xs font-bold text-[#002147] uppercase tracking-wider mb-4 border-b border-gray-100 pb-2.5 flex items-center gap-2">
                                    <span class="w-1.5 h-3.5 bg-[#f1b500] rounded-full"></span>
                                    Informasi Akademik (Opsional)
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-4">
                                    
                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 mb-1">Tahun Akademik</label>
                                        <input type="text" name="academic_year" value="{{ old('academic_year', $document->academic_year) }}" class="w-full text-xs rounded-lg border-gray-300 shadow-xs focus:border-[#002147] focus:ring-[#002147] p-2.5" placeholder="Contoh: 2026/2027">
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 mb-1">Semester</label>
                                        <select name="semester" class="w-full text-xs rounded-lg border-gray-300 shadow-xs focus:border-[#002147] focus:ring-[#002147] p-2.5">
                                            <option value="">Pilih Semester</option>
                                            <option value="Ganjil" {{ old('semester', $document->semester) == 'Ganjil' ? 'selected' : '' }}>Ganjil</option>
                                            <option value="Genap" {{ old('semester', $document->semester) == 'Genap' ? 'selected' : '' }}>Genap</option>
                                            <option value="Antara" {{ old('semester', $document->semester) == 'Antara' ? 'selected' : '' }}>Antara</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 mb-1">Mata Kuliah</label>
                                        <input type="text" name="course_name" value="{{ old('course_name', $document->course_name) }}" class="w-full text-xs rounded-lg border-gray-300 shadow-xs focus:border-[#002147] focus:ring-[#002147] p-2.5">
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 mb-1">PIC / Penanggung Jawab</label>
                                        <input type="text" name="pic" value="{{ old('pic', $document->pic) }}" class="w-full text-xs rounded-lg border-gray-300 shadow-xs focus:border-[#002147] focus:ring-[#002147] p-2.5">
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 mb-1">Unit / Prodi</label>
                                        <input type="text" name="department" value="{{ old('department', $document->department) }}" class="w-full text-xs rounded-lg border-gray-300 shadow-xs focus:border-[#002147] focus:ring-[#002147] p-2.5">
                                    </div>

                                </div>
                            </div>

                            <!-- Date & Additional Section (Diagram Section 3 & 4) -->
                            <div>
                                <h3 class="text-xs font-bold text-[#002147] uppercase tracking-wider mb-4 border-b border-gray-100 pb-2.5 flex items-center gap-2">
                                    <span class="w-1.5 h-3.5 bg-[#f1b500] rounded-full"></span>
                                    Pengaturan Tampil &amp; Visibilitas
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-4">
                                    
                                    <!-- Tanggal Tampil -->
                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 mb-1">Tanggal Tampil (display_date) <span class="text-red-500">*</span></label>
                                        <input type="date" name="display_date" value="{{ old('display_date', $document->effective_display_date ? $document->effective_display_date->format('Y-m-d') : date('Y-m-d')) }}" required class="w-full text-xs rounded-lg border-gray-300 shadow-xs focus:border-[#002147] focus:ring-[#002147] p-2.5">
                                        <p class="mt-1 text-[11px] text-gray-500">Tanggal yang ditampilkan ke viewer/publik.</p>
                                        @error('display_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>

                                    <!-- Visibilitas -->
                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 mb-1">Visibilitas Dokumen <span class="text-red-500">*</span></label>
                                        <select name="visibility" required class="w-full text-xs rounded-lg border-gray-300 shadow-xs focus:border-[#002147] focus:ring-[#002147] p-2.5">
                                            <option value="viewer" {{ old('visibility', $document->visibility) === 'viewer' ? 'selected' : '' }}>Tampil ke Viewer</option>
                                            <option value="internal" {{ old('visibility', $document->visibility) === 'internal' ? 'selected' : '' }}>Internal</option>
                                            <option value="private" {{ old('visibility', $document->visibility) === 'private' ? 'selected' : '' }}>Private</option>
                                        </select>
                                    </div>

                                    <div class="md:col-span-2">
                                        <div class="flex items-start">
                                            <div class="flex items-center h-5">
                                                <input type="hidden" name="is_downloadable" value="0">
                                                <input id="is_downloadable" name="is_downloadable" value="1" type="checkbox" {{ old('is_downloadable', $document->is_downloadable) ? 'checked' : '' }} class="focus:ring-[#002147] h-4 w-4 text-[#002147] border-gray-300 rounded">
                                            </div>
                                            <div class="ml-3 text-xs">
                                                <label for="is_downloadable" class="font-bold text-gray-700">Izinkan Unduhan</label>
                                                <p class="text-[11px] text-gray-500">Bila tidak dicentang, pengguna viewer hanya dapat melihat (pratinjau) tanpa tombol unduh.</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-bold text-gray-700 mb-1">Deskripsi Singkat</label>
                                        <textarea name="description" rows="3" class="w-full text-xs rounded-lg border-gray-300 shadow-xs focus:border-[#002147] focus:ring-[#002147] p-2.5">{{ old('description', $document->description) }}</textarea>
                                    </div>

                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-bold text-gray-700 mb-1">Tag <span class="text-gray-400 font-normal">(Pisahkan dengan koma)</span></label>
                                        <input type="text" name="tags" value="{{ old('tags', is_array($document->tags) ? implode(', ', $document->tags) : '') }}" class="w-full text-xs rounded-lg border-gray-300 shadow-xs focus:border-[#002147] focus:ring-[#002147] p-2.5">
                                    </div>
                                    
                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-bold text-gray-700 mb-1">Status Dokumen</label>
                                        <select name="status" class="w-full text-xs rounded-lg border-gray-300 shadow-xs focus:border-[#002147] focus:ring-[#002147] p-2.5 {{ !auth()->user()->isAdmin() ? 'bg-gray-100 cursor-not-allowed' : '' }}" {{ !auth()->user()->isAdmin() ? 'disabled' : '' }}>
                                            @foreach(\App\Models\Document::STATUSES as $val => $label)
                                                <option value="{{ $val }}" {{ old('status', $document->status) == $val ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @if(!auth()->user()->isAdmin())
                                            <input type="hidden" name="status" value="{{ $document->status }}">
                                        @endif
                                    </div>

                                </div>
                            </div>

                        </div>

                        <div class="bg-slate-50 px-6 py-4 flex items-center justify-end gap-3 border-t border-gray-100">
                            <a href="{{ route('folders.index', ['folder_id' => $document->folder_id]) }}" class="px-4 py-2 border border-gray-300 shadow-xs text-xs font-semibold rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                                Batal
                            </a>
                            <button type="submit" class="px-5 py-2 border border-transparent shadow-xs text-xs font-bold rounded-lg text-white bg-[#002147] hover:bg-[#001733] transition-colors">
                                Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sidebar Audit Trail for Edit -->
            <div>
                <div class="bg-white rounded-2xl shadow-xs border border-gray-200 overflow-hidden sticky top-6">
                    <div class="px-5 py-3 border-b-2 border-[#f1b500] bg-[#002147] text-white flex items-center justify-between">
                        <h3 class="text-xs font-bold uppercase tracking-wider">Log Perubahan Terakhir</h3>
                        <span class="text-[10px] text-[#f1b500] font-semibold">Audit</span>
                    </div>
                    <div class="p-5">
                        @if($auditLogs->count() > 0)
                            <div class="flow-root">
                                <ul role="list" class="-mb-6">
                                    @foreach($auditLogs->take(6) as $log)
                                    <li>
                                        <div class="relative pb-6">
                                            @if(!$loop->last)
                                            <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                            @endif
                                            <div class="relative flex space-x-3">
                                                <div>
                                                    <span class="h-8 w-8 rounded-full bg-slate-100 text-[#f1b500] flex items-center justify-center ring-4 ring-white border border-gray-200">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                    </span>
                                                </div>
                                                <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1">
                                                    <div>
                                                        <p class="text-xs text-gray-600">
                                                            <span class="font-bold text-gray-900">
                                                            @if($log->action == 'document_created') Dibuat awal
                                                            @elseif($log->action == 'document_updated') Diupdate
                                                            @elseif($log->action == 'upload_date_changed') Tgl Upload diubah
                                                            @elseif($log->action == 'display_date_changed') Tgl Tampil diubah
                                                            @elseif($log->action == 'document_submitted') Diajukan
                                                            @elseif($log->action == 'document_approved') Disetujui
                                                            @elseif($log->action == 'version_uploaded') Versi baru
                                                            @else {{ $log->action }} @endif
                                                            </span><br>
                                                            oleh <span class="font-semibold text-[#002147]">{{ $log->user->name }}</span>
                                                        </p>
                                                    </div>
                                                    <div class="whitespace-nowrap text-right text-[10px] text-gray-400">
                                                        <time datetime="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->diffForHumans() }}</time>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>
                        @else
                            <p class="text-xs text-gray-500 text-center py-4">Belum ada riwayat perubahan.</p>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
