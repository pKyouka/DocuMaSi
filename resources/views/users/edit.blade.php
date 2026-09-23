<x-app-layout>
    <div class="max-w-3xl mx-auto">
        <div class="mb-6">
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('users.index') }}" class="text-sm font-medium text-blue-600 hover:text-blue-800 transition-colors">&larr; Kembali ke Daftar Pengguna</a>
            </div>
            <h2 class="text-2xl font-bold text-gray-800">Edit Pengguna: {{ $user->name }}</h2>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <form action="{{ route('users.update', $user) }}" method="POST">
                @csrf
                @method('PUT')
                
                <div class="p-6 md:p-8 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-y-6 gap-x-6">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Nama Lengkap <span class="text-red-500">*</span></label>
                            <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Email (Untuk Login) <span class="text-red-500">*</span></label>
                            <input type="email" name="email" value="{{ old('email', $user->email) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" autocomplete="new-email">
                            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Role / Hak Akses <span class="text-red-500">*</span></label>
                            <select name="role" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" {{ $user->id === auth()->id() && $user->isSuperAdmin() ? 'disabled' : '' }}>
                                @foreach(\App\Models\User::ROLES as $val => $label)
                                    <option value="{{ $val }}" {{ old('role', $user->role) == $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @if($user->id === auth()->id() && $user->isSuperAdmin())
                                <input type="hidden" name="role" value="{{ $user->role }}">
                                <p class="mt-1 text-[11px] text-gray-500">Anda tidak dapat mengubah role Anda sendiri.</p>
                            @endif
                            @error('role') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-2 pt-4 border-t border-gray-100">
                            <h4 class="text-sm font-semibold text-gray-800 mb-4">Ganti Password (Biarkan kosong jika tidak ingin mengubah)</h4>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Password Baru</label>
                            <input type="password" name="password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" autocomplete="new-password">
                            @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Konfirmasi Password Baru</label>
                            <input type="password" name="password_confirmation" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" autocomplete="new-password">
                        </div>

                        <div class="md:col-span-2 pt-4 border-t border-gray-100">
                            <label class="block text-sm font-medium text-gray-700">Unit (Biro / Jurusan / Prodi)</label>
                            <select name="department" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                <option value="">Pusat / Umum</option>
                                @foreach(\App\Models\User::UNITS as $unit)
                                    <option value="{{ $unit === 'Program Studi PSTI' ? 'PSTI' : $unit }}" {{ old('department', $user->department) == ($unit === 'Program Studi PSTI' ? 'PSTI' : $unit) ? 'selected' : '' }}>
                                        {{ $unit }}
                                    </option>
                                @endforeach
                            </select>
                            @error('department') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-2 pt-4 border-t border-gray-100">
                            <div class="flex items-center">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $user->is_active) ? 'checked' : '' }} class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" {{ $user->id === auth()->id() ? 'disabled' : '' }}>
                                @if($user->id === auth()->id())
                                    <input type="hidden" name="is_active" value="{{ $user->is_active }}">
                                @endif
                                <label for="is_active" class="ml-2 block text-sm font-medium text-gray-900">
                                    Status Aktif (Dapat Login)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 px-6 py-4 flex items-center justify-end gap-3 border-t border-gray-100">
                    <a href="{{ route('users.index') }}" class="px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Batal
                    </a>
                    <button type="submit" class="px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
