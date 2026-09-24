<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    const ROLE_SUPERADMIN = 'superadmin';
    const ROLE_ADMIN = 'admin';
    const ROLE_USER = 'user';

    // Aliases for backward compatibility
    const ROLE_SUPER_ADMIN = self::ROLE_SUPERADMIN;

    const ROLES = [
        self::ROLE_SUPERADMIN => 'Superadmin',
        self::ROLE_ADMIN => 'Admin (Biro / Jurusan)',
        self::ROLE_USER => 'User (Pengguna Biasa)',
    ];

    const BIROS = [
        'Biro Akademik',
        'Biro Penjaminan Mutu',
        'Biro Kemahasiswaan dan Alumni',
        'Biro Aset dan Umum',
        'Lembaga Pengkajian dan Pengamalan Islam',
        'UPT Perpustakaan',
        'Badan Perencanaan dan Pengembangan (BPP)',
        'Lembaga Penelitian dan Pengabdian kepada Masyarakat',
        'Badan Pengembangan Teknologi dan Sistem Informasi',
        'UPT Laboratiorium',
        'Biro Humas dan Protokol',
        'Biro Kerjasama dan Urusan Internasional',
    ];

    const UNITS = [
        'Biro Akademik',
        'Biro Penjaminan Mutu',
        'Biro Kemahasiswaan dan Alumni',
        'Biro Aset dan Umum',
        'Lembaga Pengkajian dan Pengamalan Islam',
        'UPT Perpustakaan',
        'Badan Perencanaan dan Pengembangan (BPP)',
        'Lembaga Penelitian dan Pengabdian kepada Masyarakat',
        'Badan Pengembangan Teknologi dan Sistem Informasi',
        'UPT Laboratiorium',
        'Biro Humas dan Protokol',
        'Biro Kerjasama dan Urusan Internasional',
        'Program Studi PSTI',
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'department',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_SUPERADMIN, 'super_admin']);
    }

    public function isAdmin(): bool
    {
        return $this->isSuperAdmin() || in_array($this->role, [self::ROLE_ADMIN, 'biro', 'reviewer']);
    }

    public function isReviewer(): bool
    {
        return $this->isAdmin();
    }

    public function isUser(): bool
    {
        return $this->role === self::ROLE_USER;
    }

    public function canEditUploadDate(): bool
    {
        return $this->isAdmin();
    }

    public function canManageUsers(): bool
    {
        return $this->isSuperAdmin();
    }

    public function getDepartmentAliases(): array
    {
        if (!$this->department) {
            return [];
        }
        $aliases = [$this->department];
        if ($this->department === 'PSTI') {
            $aliases[] = 'Program Studi PSTI';
        } elseif ($this->department === 'Program Studi PSTI') {
            $aliases[] = 'PSTI';
        }
        return array_unique($aliases);
    }

    public function matchesDepartment(?string $dept): bool
    {
        if (!$dept || !$this->department) {
            return false;
        }
        return in_array($dept, $this->getDepartmentAliases());
    }

    public function isDepartmentSharedWith(?array $sharedDepts): bool
    {
        if (empty($sharedDepts)) {
            return false;
        }
        return !empty(array_intersect($this->getDepartmentAliases(), $sharedDepts));
    }

    public function canApproveDocuments(): bool
    {
        return $this->isAdmin();
    }

    public function canUploadDocuments(): bool
    {
        return (bool) $this->is_active;
    }

    public function canDeleteDocuments(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canManageCategories(): bool
    {
        return $this->isAdmin();
    }

    public function canViewAuditTrail(): bool
    {
        return $this->isSuperAdmin();
    }

    public function getRoleLabelAttribute(): string
    {
        if (isset(self::ROLES[$this->role])) {
            return self::ROLES[$this->role];
        }
        if ($this->role === 'super_admin') {
            return 'Superadmin';
        }
        if (in_array($this->role, ['biro', 'reviewer'])) {
            return 'Admin (Biro / Jurusan)';
        }
        return ucfirst($this->role);
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'created_by');
    }

    public function approvals()
    {
        return $this->hasMany(DocumentApproval::class);
    }
}
