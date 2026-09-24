<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Folder extends Model
{
    protected $fillable = [
        'name',
        'parent_id',
        'department',
        'description',
        'shared_departments',
        'created_by',
    ];

    protected $casts = [
        'shared_departments' => 'array',
    ];

    public function parent()
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Folder::class, 'parent_id')->orderBy('name');
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getBreadcrumbs(): array
    {
        $breadcrumbs = [];
        $current = $this;

        while ($current) {
            array_unshift($breadcrumbs, [
                'id' => $current->id,
                'name' => $current->name,
                'department' => $current->department,
            ]);
            $current = $current->parent;
        }

        return $breadcrumbs;
    }

    public function getEffectiveDepartment(): ?string
    {
        if (!empty($this->department)) {
            return $this->department;
        }

        $current = $this->parent;
        while ($current) {
            if (!empty($current->department)) {
                return $current->department;
            }
            $current = $current->parent;
        }

        return null;
    }

    public function getEffectiveSharedDepartments(): array
    {
        if (!is_null($this->shared_departments)) {
            return $this->shared_departments;
        }

        $current = $this->parent;
        while ($current) {
            if (!is_null($current->shared_departments)) {
                return $current->shared_departments;
            }
            $current = $current->parent;
        }

        return [];
    }

    public function isDirectlyAccessibleBy(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $effectiveDept = $this->getEffectiveDepartment();
        if ($effectiveDept && $user->matchesDepartment($effectiveDept)) {
            return true;
        }

        $sharedDepts = $this->getEffectiveSharedDepartments();
        if (!empty($sharedDepts) && $user->isDepartmentSharedWith($sharedDepts)) {
            return true;
        }

        if (empty($effectiveDept) && empty($sharedDepts)) {
            return true;
        }

        return false;
    }

    public function hasDirectSharedDocumentsFor(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        $aliases = $user->getDepartmentAliases();
        if (empty($aliases)) {
            return false;
        }

        return $this->documents()
            ->where(function ($q) use ($aliases, $user) {
                $q->where('created_by', $user->id);
                foreach ($aliases as $alias) {
                    $q->orWhere('shared_departments', 'like', '%"' . $alias . '"%');
                }
            })
            ->exists();
    }

    public function containsSharedItemsFor(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($this->hasDirectSharedDocumentsFor($user)) {
            return true;
        }

        foreach ($this->children as $child) {
            if ($child->isDirectlyAccessibleBy($user) || $child->containsSharedItemsFor($user)) {
                return true;
            }
        }

        return false;
    }

    public function canAccess(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($this->isDirectlyAccessibleBy($user)) {
            return true;
        }

        return $this->containsSharedItemsFor($user);
    }

    public function isSharedFromOtherDepartment(?User $user): bool
    {
        if (!$user || $user->isSuperAdmin()) {
            return false;
        }
        $effectiveDept = $this->getEffectiveDepartment();
        return $effectiveDept && !$user->matchesDepartment($effectiveDept);
    }

    public function getAccessibleChildren(?User $user)
    {
        if (!$user || $user->isSuperAdmin()) {
            return $this->children;
        }

        if ($user->matchesDepartment($this->getEffectiveDepartment())) {
            return $this->children;
        }

        return $this->children->filter(function ($child) use ($user) {
            return $child->canAccess($user);
        })->values();
    }

    public function canManage(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($this->created_by === $user->id) {
            return true;
        }

        if ($user->isAdmin()) {
            $effectiveDept = $this->getEffectiveDepartment();
            return $effectiveDept && $user->matchesDepartment($effectiveDept);
        }

        return false;
    }

    public function scopeAccessible($query, ?User $user)
    {
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isAdmin() && $user->department) {
            return $query->where(function ($q) use ($user) {
                $q->where('department', $user->department)
                  ->orWhereHas('parent', function ($pq) use ($user) {
                      $pq->where('department', $user->department);
                  });
            });
        }

        return $query->whereRaw('1 = 0');
    }
}
