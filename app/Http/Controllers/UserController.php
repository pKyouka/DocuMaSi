<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $authUser = auth()->user();
        abort_unless($authUser && $authUser->canManageUsers(), 403, 'Akses ditolak.');

        $users = User::when($request->search, function ($q, $s) {
                $q->where(function ($sub) use ($s) {
                    $sub->where('name', 'like', "%{$s}%")
                        ->orWhere('email', 'like', "%{$s}%")
                        ->orWhere('department', 'like', "%{$s}%");
                });
            })
            ->when($request->role, fn($q, $r) => $q->where('role', $r))
            ->when(!$authUser->isSuperAdmin(), function ($q) use ($authUser) {
                $aliases = $authUser->getDepartmentAliases();
                $q->whereIn('department', $aliases);
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('users.index', compact('users'));
    }

    public function create()
    {
        $authUser = auth()->user();
        abort_unless($authUser && $authUser->canManageUsers(), 403, 'Akses ditolak.');

        return view('users.create');
    }

    public function store(Request $request)
    {
        $authUser = auth()->user();
        abort_unless($authUser && $authUser->canManageUsers(), 403, 'Akses ditolak.');

        $allowedRoles = $authUser->isSuperAdmin()
            ? array_keys(User::ROLES)
            : [User::ROLE_ADMIN, User::ROLE_USER];

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => ['required', Rule::in($allowedRoles)],
            'department' => $authUser->isSuperAdmin() ? 'nullable|string|max:255' : 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if (!$authUser->isSuperAdmin()) {
            $validated['department'] = $authUser->department;
        }

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        AuditLog::log(
            'user_created',
            "Pengguna '{$user->name}' berhasil dibuat di unit '{$user->department}'.",
            User::class,
            $user->id,
        );

        return redirect()->route('users.index')
            ->with('success', "Pengguna '{$user->name}' berhasil ditambahkan ke unit '{$user->department}'.");
    }

    public function edit(User $user)
    {
        $authUser = auth()->user();
        abort_unless($authUser && $authUser->canManageUsers(), 403, 'Akses ditolak.');

        if (!$authUser->isSuperAdmin()) {
            abort_unless($authUser->matchesDepartment($user->department), 403, 'Anda hanya dapat mengelola pengguna di unit Anda.');
            abort_if($user->isSuperAdmin(), 403, 'Anda tidak memiliki hak mengubah akun Superadmin.');
        }

        return view('users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $authUser = auth()->user();
        abort_unless($authUser && $authUser->canManageUsers(), 403, 'Akses ditolak.');

        if (!$authUser->isSuperAdmin()) {
            abort_unless($authUser->matchesDepartment($user->department), 403, 'Anda hanya dapat mengelola pengguna di unit Anda.');
            abort_if($user->isSuperAdmin(), 403, 'Anda tidak memiliki hak mengubah akun Superadmin.');
        }

        $allowedRoles = $authUser->isSuperAdmin()
            ? array_keys(User::ROLES)
            : [User::ROLE_ADMIN, User::ROLE_USER];

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'role' => ['required', Rule::in($allowedRoles)],
            'department' => $authUser->isSuperAdmin() ? 'nullable|string|max:255' : 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if (!$authUser->isSuperAdmin()) {
            $validated['department'] = $authUser->department;
        }

        if (empty($validated['password'])) {
            unset($validated['password']);
        } else {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        AuditLog::log(
            'user_updated',
            "Pengguna '{$user->name}' diperbarui.",
            User::class,
            $user->id,
        );

        return redirect()->route('users.index')
            ->with('success', 'Pengguna berhasil diperbarui.');
    }

    public function destroy(User $user)
    {
        $authUser = auth()->user();
        abort_unless($authUser && $authUser->canManageUsers(), 403, 'Akses ditolak.');

        if ($user->id === $authUser->id) {
            return back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        if (!$authUser->isSuperAdmin()) {
            abort_unless($authUser->matchesDepartment($user->department), 403, 'Anda hanya dapat menghapus pengguna di unit Anda.');
            abort_if($user->isSuperAdmin(), 403, 'Anda tidak memiliki hak menghapus akun Superadmin.');
        }

        AuditLog::log(
            'user_deleted',
            "Pengguna '{$user->name}' dihapus.",
            User::class,
            $user->id,
        );

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'Pengguna berhasil dihapus.');
    }
}
