<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Document extends Model
{
    use SoftDeletes;

    const STATUS_DRAFT = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_REVIEW = 'review';
    const STATUS_REVISION = 'revision';
    const STATUS_APPROVED = 'approved';
    const STATUS_ARCHIVED = 'archived';

    const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_SUBMITTED => 'Diajukan',
        self::STATUS_REVIEW => 'Direview',
        self::STATUS_REVISION => 'Revisi',
        self::STATUS_APPROVED => 'Disetujui',
        self::STATUS_ARCHIVED => 'Arsip',
    ];

    const STATUS_COLORS = [
        self::STATUS_DRAFT => 'gray',
        self::STATUS_SUBMITTED => 'yellow',
        self::STATUS_REVIEW => 'blue',
        self::STATUS_REVISION => 'red',
        self::STATUS_APPROVED => 'green',
        self::STATUS_ARCHIVED => 'purple',
    ];

    const VISIBILITY_VIEWER = 'viewer';
    const VISIBILITY_INTERNAL = 'internal';
    const VISIBILITY_PRIVATE = 'private';

    const VISIBILITIES = [
        self::VISIBILITY_VIEWER => 'Tampil ke Viewer',
        self::VISIBILITY_INTERNAL => 'Internal',
        self::VISIBILITY_PRIVATE => 'Private',
    ];

    const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'zip'];

    const ALLOWED_MIMES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'image/jpeg',
        'image/png',
        'application/zip',
    ];

    protected $fillable = [
        'uuid',
        'name',
        'document_number',
        'category_id',
        'folder_id',
        'department',
        'academic_year',
        'semester',
        'course_name',
        'pic',
        'description',
        'tags',
        'document_date',
        'display_date',
        'upload_date',
        'original_uploaded_at',
        'actual_uploaded_at',
        'status',
        'visibility',
        'shared_departments',
        'is_downloadable',
        'current_version',
        'created_by',
        'approved_by',
        'approved_at',
        'archived_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'document_date' => 'date',
        'display_date' => 'date',
        'upload_date' => 'date',
        'original_uploaded_at' => 'datetime',
        'actual_uploaded_at' => 'datetime',
        'approved_at' => 'datetime',
        'archived_at' => 'datetime',
        'is_downloadable' => 'boolean',
        'shared_departments' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($document) {
            if (empty($document->uuid)) {
                $document->uuid = (string) Str::uuid();
            }
            if (empty($document->original_uploaded_at)) {
                $document->original_uploaded_at = now();
            }
            if (empty($document->actual_uploaded_at)) {
                $document->actual_uploaded_at = $document->original_uploaded_at;
            }
            if (empty($document->display_date)) {
                $document->display_date = $document->document_date ?? now()->toDateString();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function folder()
    {
        return $this->belongsTo(Folder::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function versions()
    {
        return $this->hasMany(DocumentVersion::class)->orderBy('version_number', 'desc');
    }

    public function currentVersionFile()
    {
        return $this->hasOne(DocumentVersion::class)->where('version_number', $this->current_version);
    }

    public function latestVersion()
    {
        return $this->hasOne(DocumentVersion::class)->latestOfMany('version_number');
    }

    public function approvals()
    {
        return $this->hasMany(DocumentApproval::class)->orderBy('created_at', 'desc');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'model_id')
            ->where('model_type', self::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'gray';
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REVISION]);
    }

    public function isSubmittable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REVISION]);
    }

    public function isReviewable(): bool
    {
        return in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_REVIEW]);
    }

    public function isArchivable(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function getVisibilityLabelAttribute(): string
    {
        return self::VISIBILITIES[$this->visibility] ?? 'Tampil ke Viewer';
    }

    public function getEffectiveDisplayDateAttribute()
    {
        return $this->display_date ?? $this->document_date ?? $this->upload_date;
    }

    public function getEffectiveSharedDepartments(): array
    {
        if (!is_null($this->shared_departments)) {
            return $this->shared_departments;
        }

        if ($this->folder) {
            return $this->folder->getEffectiveSharedDepartments();
        }

        return [];
    }

    public function isSharedFromOtherDepartment(?User $user): bool
    {
        if (!$user || $user->isSuperAdmin()) {
            return false;
        }
        return $this->department && !$user->matchesDepartment($this->department);
    }

    public function canAccess(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        // Creator and owner department can always access
        if ($this->created_by === $user->id) {
            return true;
        }

        if ($this->department && $user->matchesDepartment($this->department)) {
            return true;
        }

        // Check explicit shared departments
        $shared = $this->getEffectiveSharedDepartments();
        if (!empty($shared)) {
            return $user->isDepartmentSharedWith($shared);
        }

        // If from another department and not shared to user's department, deny access
        if ($this->department && !$user->matchesDepartment($this->department)) {
            return false;
        }

        // If visibility is viewer, all users can view
        if ($this->visibility === self::VISIBILITY_VIEWER) {
            return true;
        }

        return false;
    }

    public function scopeForViewer($query)
    {
        return $query->where('visibility', self::VISIBILITY_VIEWER)
            ->where('status', '!=', self::STATUS_ARCHIVED);
    }

    public function scopeActive($query)
    {
        return $query->where('status', '!=', self::STATUS_ARCHIVED);
    }

    public function scopeArchived($query)
    {
        return $query->where('status', self::STATUS_ARCHIVED);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('document_number', 'like', "%{$search}%")
              ->orWhere('pic', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%")
              ->orWhereHas('category', function ($cq) use ($search) {
                  $cq->where('name', 'like', "%{$search}%");
              });
        });
    }

    public function scopeFilter($query, array $filters)
    {
        return $query
            ->when($filters['category_id'] ?? null, fn($q, $v) => $q->where('category_id', $v))
            ->when($filters['academic_year'] ?? null, fn($q, $v) => $q->where('academic_year', $v))
            ->when($filters['semester'] ?? null, fn($q, $v) => $q->where('semester', $v))
            ->when($filters['status'] ?? null, fn($q, $v) => $q->where('status', $v))
            ->when($filters['pic'] ?? null, fn($q, $v) => $q->where('pic', 'like', "%{$v}%"))
            ->when($filters['department'] ?? null, fn($q, $v) => $q->where('department', $v))
            ->when($filters['date_from'] ?? null, fn($q, $v) => $q->where('document_date', '>=', $v))
            ->when($filters['date_to'] ?? null, fn($q, $v) => $q->where('document_date', '<=', $v));
    }
}
