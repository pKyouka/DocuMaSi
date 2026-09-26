<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Document $document): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canUploadDocuments();
    }

    public function upload(User $user): bool
    {
        return $user->canUploadDocuments();
    }

    public function update(User $user, Document $document): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Dokumen milik unit lain: hanya read-only bagi unit penerima share
        if (!empty($document->department)) {
            if (!$user->matchesDepartment($document->department)) {
                return false;
            }
        } elseif ($document->created_by !== $user->id) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($document->status === Document::STATUS_DRAFT || $document->status === Document::STATUS_REVISION) {
            return $user->id === $document->created_by;
        }

        return false;
    }

    public function delete(User $user, Document $document): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Dokumen milik unit lain: tidak dapat dihapus oleh unit penerima share
        if (!empty($document->department)) {
            if (!$user->matchesDepartment($document->department)) {
                return false;
            }
        } elseif ($document->created_by !== $user->id) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($document->status === Document::STATUS_DRAFT || $document->status === Document::STATUS_REVISION) {
            return $user->id === $document->created_by;
        }

        return false;
    }
}
