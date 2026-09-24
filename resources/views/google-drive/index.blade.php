<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Impor Google Drive</h2>
                <p class="text-sm text-gray-500 mt-1">File yang diimpor disimpan di server lokal dan mengikuti sharing biro SMART.</p>
            </div>
            @if(auth()->user()->google_drive_access_token)
                <span class="text-sm text-green-700 bg-green-50 px-3 py-2 rounded-lg">Terhubung: {{ auth()->user()->google_drive_account_email }}</span>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            @if(!auth()->user()->google_drive_access_token)
                <div class="bg-white border border-gray-200 rounded-xl p-8 text-center">
                    <h3 class="text-lg font-semibold text-gray-900">Hubungkan akun Google Drive</h3>
                    <p class="text-sm text-gray-500 mt-2">SMART hanya meminta izin baca. File baru masuk ke sistem setelah Anda memilih impor.</p>
                    <a href="{{ route('google-drive.connect') }}" class="inline-flex mt-5 px-4 py-2 rounded-lg bg-blue-600 text-white font-medium hover:bg-blue-700">Login dengan Google</a>
                </div>
            @else
                <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                        <div>
                            <h3 class="font-semibold text-gray-900">Isi Google Drive</h3>
                            <p class="text-xs text-gray-500 mt-1">Folder terdeteksi: {{ collect($items)->where('is_folder', true)->count() }} · File: {{ collect($items)->where('is_folder', false)->count() }}</p>
                        </div>
                        <a href="{{ route('google-drive.index') }}" class="text-sm text-blue-600 hover:underline">Muat ulang</a>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @forelse($items as $item)
                            <div class="px-6 py-4 flex items-center justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="font-medium text-gray-900 truncate">{{ $item['is_folder'] ? 'Folder: ' : '' }}{{ $item['name'] }}</div>
                                    <div class="text-xs text-gray-500 mt-1">{{ $item['mime_type'] }} @if(!$item['is_folder']) · {{ number_format($item['size'] / 1024, 1) }} KB @endif</div>
                                </div>
                                @if(!$item['is_folder'] && $item['importable'])
                                    <details class="shrink-0">
                                        <summary class="cursor-pointer text-sm text-blue-600 hover:underline">Impor</summary>
                                        <form method="POST" action="{{ route('google-drive.import') }}" class="mt-3 p-4 bg-gray-50 rounded-lg space-y-3 w-72">
                                            @csrf
                                            <input type="hidden" name="drive_file_id" value="{{ $item['id'] }}">
                                            <select name="category_id" required class="w-full rounded-md border-gray-300 text-sm">
                                                <option value="">Pilih kategori</option>
                                                @foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
                                            </select>
                                            <select name="folder_id" class="w-full rounded-md border-gray-300 text-sm">
                                                <option value="">Root lokal</option>
                                                @foreach($folders as $folder)<option value="{{ $folder->id }}">{{ $folder->name }}</option>@endforeach
                                            </select>
                                            <select name="visibility" required class="w-full rounded-md border-gray-300 text-sm">
                                                <option value="internal">Internal</option><option value="private">Private</option><option value="viewer">Viewer</option>
                                            </select>
                                            <fieldset class="text-xs text-gray-600">
                                                <legend class="font-medium text-gray-700 mb-1">Bagikan ke biro</legend>
                                                <div class="max-h-28 overflow-y-auto space-y-1">
                                                    @foreach($units as $unit)
                                                        <label class="flex items-start gap-2"><input type="checkbox" name="shared_departments[]" value="{{ $unit }}" class="mt-0.5 rounded border-gray-300"> <span>{{ $unit }}</span></label>
                                                    @endforeach
                                                </div>
                                            </fieldset>
                                            <button class="w-full px-3 py-2 rounded-md bg-blue-600 text-white text-sm hover:bg-blue-700">Download &amp; Simpan Lokal</button>
                                        </form>
                                    </details>
                                @elseif(!$item['is_folder'])
                                    <span class="text-xs text-gray-400">Format belum didukung</span>
                                @endif
                            </div>
                        @empty
                            <div class="px-6 py-10 text-center text-sm text-gray-500">Tidak ada folder atau file yang bisa dibaca.</div>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
