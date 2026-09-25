<x-dynamic-component :component="auth()->check() ? 'app-layout' : 'public-layout'">
    <div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ $document->folder_id ? route('folders.index', ['folder_id' => $document->folder_id]) : route('folders.index') }}" class="text-sm font-medium text-blue-600 hover:text-blue-800 transition-colors">&larr; Kembali ke File Explorer</a>
            </div>
            <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-3">
                {{ $document->name }}
                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-{{ $document->status_color }}-50 text-{{ $document->status_color }}-700 ring-1 ring-inset ring-{{ $document->status_color }}-600/20">
                    {{ $document->status_label }}
                </span>
            </h2>
            <div class="text-sm text-gray-500 mt-1 flex items-center gap-4">
                <span><i class="opacity-75">No:</i> <span class="font-mono text-gray-700">{{ $document->document_number ?? 'Tanpa Nomor' }}</span></span>
                <span>&bull;</span>
                <span><i class="opacity-75">Kategori:</i> <span class="text-gray-700">{{ $document->category->name }}</span></span>
            </div>
        </div>
        
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('documents.download', $document) }}" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                Download
            </a>
            
            @auth
            @can('update', $document)
                <a href="{{ route('documents.edit', $document) }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    Edit Data
                </a>
            @endcan
            @endauth
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            
            <!-- Details Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                    <h3 class="text-lg font-bold text-gray-800">Detail Dokumen</h3>
                </div>
                <div class="p-6">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-6">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Tahun Akademik</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $document->academic_year ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Semester</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $document->semester ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Mata Kuliah</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $document->course_name ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">PIC</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $document->pic ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Unit / Prodi</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $document->department ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Tag</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                @if(!empty($document->tags))
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($document->tags as $tag)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">{{ $tag }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    -
                                @endif
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-sm font-medium text-gray-500">Deskripsi</dt>
                            <dd class="mt-1 text-sm text-gray-900 whitespace-pre-wrap">{{ $document->description ?? '-' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Version History -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden" x-data="{ uploadModal: false }">
                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                    <h3 class="text-lg font-bold text-gray-800">Riwayat Versi ({{ $document->versions->count() }})</h3>
                    @auth
                    @can('update', $document)
                        @if($document->isEditable())
                        <button @click="uploadModal = true" type="button" class="text-sm font-medium text-blue-600 hover:text-blue-800 transition-colors">
                            + Upload Versi Baru
                        </button>
                        @endif
                    @endcan
                    @endauth
                </div>
                
                <div class="divide-y divide-gray-100">
                    @foreach($document->versions as $version)
                    <div class="p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4 {{ $loop->first ? 'bg-blue-50/30' : '' }}">
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 rounded-lg {{ $loop->first ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-500' }} flex flex-col items-center justify-center shrink-0">
                                <span class="text-xs font-bold leading-none">v{{ $version->version_number }}</span>
                            </div>
                            <div>
                                <h4 class="text-sm font-semibold text-gray-900">{{ $version->original_filename }}</h4>
                                <div class="text-xs text-gray-500 mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <span>{{ $version->file_size_formatted }}</span>
                                    <span>&bull;</span>
                                    <span>{{ $version->created_at->format('d M Y, H:i') }}</span>
                                    <span>&bull;</span>
                                    <span>Oleh: {{ $version->uploader->name }}</span>
                                </div>
                                @if($version->notes)
                                    <p class="text-sm text-gray-700 mt-2 bg-white px-3 py-2 border border-gray-100 rounded-md shadow-sm">{{ $version->notes }}</p>
                                @endif
                            </div>
                        </div>
                        <a href="{{ route('documents.download', [$document, $version]) }}" class="shrink-0 inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none">
                            Unduh
                        </a>
                    </div>
                    @endforeach
                </div>

                <!-- Upload Version Modal -->
                <div x-show="uploadModal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true" style="display: none;">
                    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div x-show="uploadModal" x-transition.opacity class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="uploadModal = false" aria-hidden="true"></div>
                        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                        <div x-show="uploadModal" x-transition class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <form action="{{ route('documents.version', $document) }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4" id="modal-title">Upload Versi Baru (v{{ $document->current_version + 1 }})</h3>
                                    
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">File Dokumen</label>
                                            <input type="file" name="file" required class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                            <p class="mt-1 text-xs text-gray-500">Format: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, JPG, PNG, ZIP (Max: 20MB)</p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Catatan Revisi</label>
                                            <textarea name="notes" rows="3" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 mt-1 block w-full sm:text-sm border border-gray-300 rounded-md" placeholder="Apa yang berubah di versi ini?"></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                                        Upload
                                    </button>
                                    <button @click="uploadModal = false" type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                        Batal
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Approval History -->
            @if($document->approvals->count() > 0)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                    <h3 class="text-lg font-bold text-gray-800">Riwayat Persetujuan</h3>
                </div>
                <div class="p-6">
                    <div class="flow-root">
                        <ul role="list" class="-mb-8">
                            @foreach($document->approvals as $approval)
                            <li>
                                <div class="relative pb-8">
                                    @if(!$loop->last)
                                    <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                    @endif
                                    <div class="relative flex space-x-3">
                                        <div>
                                            <span class="h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white 
                                                @if($approval->status == 'approved') bg-green-500 
                                                @elseif($approval->status == 'revision') bg-red-500
                                                @elseif($approval->status == 'review') bg-blue-500
                                                @else bg-gray-400 @endif">
                                                <svg class="h-5 w-5 text-white" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    @if($approval->status == 'approved')
                                                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                                                    @elseif($approval->status == 'revision')
                                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                    @else
                                                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                                    @endif
                                                </svg>
                                            </span>
                                        </div>
                                        <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                                            <div>
                                                <p class="text-sm text-gray-500">
                                                    <span class="font-medium text-gray-900">{{ $approval->user->name }}</span>
                                                    @if($approval->status == 'approved') menyetujui dokumen
                                                    @elseif($approval->status == 'revision') meminta revisi
                                                    @elseif($approval->status == 'review') mulai mereview
                                                    @else memperbarui status @endif
                                                </p>
                                                @if($approval->notes)
                                                    <div class="mt-2 text-sm text-gray-700 bg-gray-50 p-3 rounded-md border border-gray-100">
                                                        {{ $approval->notes }}
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="whitespace-nowrap text-right text-sm text-gray-500">
                                                <time datetime="{{ $approval->created_at->toIso8601String() }}">{{ $approval->created_at->format('d M Y, H:i') }}</time>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
            @endif

        </div>

        <!-- Sidebar Details & Actions -->
        <div class="space-y-6">
            
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                    <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider">Informasi Waktu</h3>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <div class="text-xs text-gray-500 font-medium">Tanggal Dokumen</div>
                        <div class="text-sm font-medium text-gray-900 mt-1">{{ $document->document_date->format('d F Y') }}</div>
                    </div>
                    <div class="border-t border-gray-100 pt-4">
                        <div class="text-xs text-gray-500 font-medium">Tanggal Upload (Tampilan)</div>
                        <div class="text-sm font-medium text-gray-900 mt-1">{{ $document->upload_date->format('d F Y') }}</div>
                    </div>
                    <div class="border-t border-gray-100 pt-4">
                        <div class="text-xs text-gray-500 font-medium">Original Upload Timestamp</div>
                        <div class="text-xs font-mono bg-gray-100 text-gray-600 px-2 py-1 rounded inline-block mt-1">
                            {{ $document->original_uploaded_at->format('d-m-Y H:i:s') }}
                        </div>
                        <p class="text-[10px] text-gray-400 mt-1 leading-tight">Waktu asli file pertama kali diunggah ke server (audit trail).</p>
                    </div>
                    <div class="border-t border-gray-100 pt-4">
                        <div class="text-xs text-gray-500 font-medium">Diunggah Oleh</div>
                        <div class="text-sm font-medium text-gray-900 mt-1 flex items-center">
                            <div class="w-5 h-5 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-[10px] font-bold mr-2">
                                {{ strtoupper(substr($document->creator->name, 0, 1)) }}
                            </div>
                            {{ $document->creator->name }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                    <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider">Aksi Workflow</h3>
                </div>
                <div class="p-6 space-y-3">
                    
                    @if(auth()->check() && $document->isSubmittable() && auth()->id() === $document->created_by)
                    <form action="{{ route('documents.submit', $document) }}" method="POST">
                        @csrf
                        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Ajukan Untuk Review
                        </button>
                    </form>
                    @endif

                    @if(auth()->check() && auth()->user()->canApproveDocuments() && in_array($document->status, [\App\Models\Document::STATUS_SUBMITTED, \App\Models\Document::STATUS_REVIEW, \App\Models\Document::STATUS_REVISION]))
                        <a href="{{ route('approvals.index') }}" class="w-full inline-flex justify-center items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Buka di Panel Approval
                        </a>
                    @endif

                    @if(auth()->check() && auth()->user()->isAdmin() && $document->isArchivable())
                    <form action="{{ route('documents.archive', $document) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin mengarsipkan dokumen ini?');">
                        @csrf
                        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                            Arsipkan Dokumen
                        </button>
                    </form>
                    @endif

                    @auth
                    @can('delete', $document)
                    <div class="pt-4 mt-2 border-t border-gray-100">
                        <form action="{{ route('documents.destroy', $document) }}" method="POST" onsubmit="return confirm('PERINGATAN: Dokumen ini akan dihapus secara permanen (Soft Delete). Lanjutkan?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 border border-red-300 shadow-sm text-sm font-medium rounded-md text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                Hapus Dokumen
                            </button>
                        </form>
                    </div>
                    @endcan
                    @endauth
                </div>
            </div>

        </div>
    </div>
</x-dynamic-component>
