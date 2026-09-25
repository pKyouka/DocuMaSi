<x-app-layout>
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Panel Approval</h2>
        <p class="text-sm text-gray-500 mt-1">Review dan berikan persetujuan untuk dokumen yang diajukan.</p>
    </div>

    <!-- Tabs -->
    <div class="mb-6 border-b border-gray-200">
        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
            <a href="{{ route('approvals.index', ['tab' => 'pending']) }}" class="{{ $tab == 'pending' ? 'border-amber-500 text-amber-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-bold text-sm flex items-center">
                Menunggu Review &amp; ACC
                @if(($pendingCount ?? 0) > 0)
                <span class="ml-2 bg-amber-100 text-amber-800 py-0.5 px-2.5 rounded-full text-xs font-bold ring-1 ring-amber-300">{{ $pendingCount }}</span>
                @endif
            </a>
            <a href="{{ route('approvals.index', ['tab' => 'revision']) }}" class="{{ $tab == 'revision' ? 'border-red-500 text-red-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
                Perlu Revisi
                @if(($revisionCount ?? 0) > 0)
                <span class="ml-2 bg-red-100 text-red-600 py-0.5 px-2.5 rounded-full text-xs font-semibold">{{ $revisionCount }}</span>
                @endif
            </a>
            <a href="{{ route('approvals.index', ['tab' => 'approved']) }}" class="{{ $tab == 'approved' ? 'border-emerald-500 text-emerald-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Riwayat Disetujui (ACC)
            </a>
        </nav>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Dokumen</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Pengaju</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Waktu</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($documents as $doc)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-gray-400 mr-3 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                <div>
                                    <div class="text-sm font-semibold text-gray-900 line-clamp-2" title="{{ $doc->name }}">{{ $doc->name }}</div>
                                    <div class="text-xs text-gray-500 mt-1 font-mono">{{ $doc->document_number ?? 'Tanpa Nomor' }}</div>
                                    <div class="text-[11px] text-gray-400 mt-0.5">Kat: {{ $doc->category->name }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">{{ $doc->creator->name }}</div>
                            <div class="text-xs text-gray-500">{{ $doc->department ?? '-' }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php
                                $statusColor = $doc->getDisplayStatusColorForUser(auth()->user());
                                $statusLabel = $doc->getDisplayStatusLabelForUser(auth()->user());
                            @endphp
                            @if($statusColor === 'green')
                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300 shadow-xs">
                                    <svg class="w-3.5 h-3.5 mr-1 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    {{ $statusLabel }}
                                </span>
                            @elseif($statusColor === 'yellow')
                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold bg-amber-100 text-amber-900 border border-amber-300 shadow-xs animate-pulse">
                                    <svg class="w-3.5 h-3.5 mr-1 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    {{ $statusLabel }}
                                </span>
                            @elseif($statusColor === 'red')
                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold bg-red-100 text-red-800 border border-red-300 shadow-xs">
                                    <svg class="w-3.5 h-3.5 mr-1 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    {{ $statusLabel }}
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-700 border border-gray-300">
                                    {{ $statusLabel }}
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $doc->updated_at->diffForHumans() }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex justify-end space-x-2" x-data="{ open: false }">
                                <a href="{{ route('documents.show', $doc) }}" class="text-gray-500 hover:text-blue-600 bg-gray-50 hover:bg-blue-50 px-3 py-1.5 rounded transition-colors" title="Lihat & Download">
                                    Lihat Detail
                                </a>
                                
                                @if($tab == 'pending')
                                    <button @click="open = true" class="text-white bg-blue-600 hover:bg-blue-700 px-3 py-1.5 rounded transition-colors">
                                        Proses
                                    </button>
                                    
                                    <!-- Approval Modal -->
                                    <div x-show="open" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true" style="display: none;">
                                        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                                            <div x-show="open" x-transition.opacity class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="open = false" aria-hidden="true"></div>
                                            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                                            <div x-show="open" x-transition class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                                                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 text-left">
                                                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4" id="modal-title">Proses Approval Dokumen</h3>
                                                    <p class="text-sm text-gray-500 mb-4">Pilih tindakan untuk dokumen <strong>{{ $doc->name }}</strong>.</p>
                                                    
                                                    @if($doc->status == \App\Models\Document::STATUS_SUBMITTED)
                                                    <form action="{{ route('approvals.review', $doc) }}" method="POST" class="mb-4 pb-4 border-b border-gray-100">
                                                        @csrf
                                                        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 border border-blue-300 shadow-sm text-sm font-medium rounded-md text-blue-700 bg-blue-50 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                            Mulai Review (Tandai Sedang Direview)
                                                        </button>
                                                    </form>
                                                    @endif

                                                    <form action="{{ route('approvals.approve', $doc) }}" method="POST" class="mb-4">
                                                        @csrf
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">Catatan Persetujuan (Opsional)</label>
                                                        <textarea name="notes" rows="2" class="shadow-sm focus:ring-emerald-500 focus:border-emerald-500 mt-1 block w-full sm:text-sm border border-gray-300 rounded-md"></textarea>
                                                        <button type="submit" class="mt-2 w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-emerald-600 text-base font-bold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 sm:text-sm shadow-xs">
                                                            ✓ Setujui Dokumen (ACC)
                                                        </button>
                                                    </form>
                                                    
                                                    <form action="{{ route('approvals.revision', $doc) }}" method="POST" class="pt-4 border-t border-gray-100">
                                                        @csrf
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">Catatan Revisi <span class="text-red-500">*</span></label>
                                                        <textarea name="notes" rows="3" required class="shadow-sm focus:ring-red-500 focus:border-red-500 mt-1 block w-full sm:text-sm border border-gray-300 rounded-md" placeholder="Jelaskan apa yang perlu diperbaiki..."></textarea>
                                                        <button type="submit" class="mt-2 w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:text-sm">
                                                            Minta Revisi
                                                        </button>
                                                    </form>
                                                </div>
                                                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                                    <button @click="open = false" type="button" class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:w-auto sm:text-sm">
                                                        Batal
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                                <svg class="h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                            </div>
                            <h3 class="text-sm font-medium text-gray-900">Tidak ada dokumen</h3>
                            <p class="mt-1 text-sm text-gray-500">Tidak ada dokumen yang perlu direview saat ini.</p>
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

</x-app-layout>
