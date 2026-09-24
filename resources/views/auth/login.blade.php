<x-guest-layout>
    <div class="bg-white rounded-2xl shadow-md border border-gray-200/90 p-7 sm:p-9"
         x-data="{ showPassword: false }">

        <!-- Header & Logo -->
        <div class="text-center mb-6">
            <h1 class="text-2xl font-black text-[#002147] tracking-tight">SMART</h1>
            <p class="text-xs text-gray-500 font-medium mt-1">Sistem Manajemen Arsip Repository Teknologi Informasi</p>
            <div class="mt-2 inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-slate-100 text-[11px] font-semibold text-slate-700 border border-slate-200">
                <span class="w-1.5 h-1.5 rounded-full bg-[#f1b500]"></span>
                Program Studi Teknologi Informasi
            </div>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="mb-4 text-xs" :status="session('status')" />

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf

            <!-- Email Address -->
            <div>
                <label for="email" class="block text-xs font-bold text-gray-700 mb-1.5">Alamat Email</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path></svg>
                    </div>
                    <input id="email"
                           type="email"
                           name="email"
                           value="{{ old('email') }}"
                           required
                           autofocus
                           autocomplete="username"
                           placeholder="nama@unisayogya.ac.id"
                           class="w-full pl-9 pr-3 py-2 text-xs bg-gray-50/50 border border-gray-300 rounded-lg focus:bg-white focus:ring-1 focus:ring-[#002147] focus:border-[#002147] transition-all text-gray-900 placeholder:text-gray-400">
                </div>
                <x-input-error :messages="$errors->get('email')" class="mt-1.5 text-xs text-red-600" />
            </div>

            <!-- Password -->
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label for="password" class="block text-xs font-bold text-gray-700">Kata Sandi</label>
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="text-[11px] text-[#002147] hover:underline font-semibold">
                            Lupa sandi?
                        </a>
                    @endif
                </div>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    </div>
                    <input id="password"
                           :type="showPassword ? 'text' : 'password'"
                           name="password"
                           required
                           autocomplete="current-password"
                           placeholder="Masukkan kata sandi"
                           class="w-full pl-9 pr-9 py-2 text-xs bg-gray-50/50 border border-gray-300 rounded-lg focus:bg-white focus:ring-1 focus:ring-[#002147] focus:border-[#002147] transition-all text-gray-900 placeholder:text-gray-400">
                    <button type="button"
                            @click="showPassword = !showPassword"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 transition-colors">
                        <template x-if="!showPassword">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                        </template>
                        <template x-if="showPassword">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"></path></svg>
                        </template>
                    </button>
                </div>
                <x-input-error :messages="$errors->get('password')" class="mt-1.5 text-xs text-red-600" />
            </div>

            <!-- Remember Me -->
            <div class="flex items-center justify-between pt-1">
                <label for="remember_me" class="inline-flex items-center cursor-pointer">
                    <input id="remember_me"
                           type="checkbox"
                           name="remember"
                           class="rounded text-[#002147] focus:ring-[#002147] border-gray-300 h-4 w-4">
                    <span class="ms-2 text-xs text-gray-600">Ingat Saya</span>
                </label>
            </div>

            <!-- Submit Button -->
            <div class="pt-2">
                <button type="submit"
                        class="w-full py-2.5 px-4 rounded-lg text-xs font-bold text-white bg-[#002147] hover:bg-[#001733] shadow-sm transition-colors flex items-center justify-center gap-2">
                    <span>Masuk ke Akun</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                </button>
            </div>
        </form>
    </div>
</x-guest-layout>
