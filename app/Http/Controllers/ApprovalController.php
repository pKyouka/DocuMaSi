<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentApproval;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        abort_unless($user && $user->canApproveDocuments(), 403, 'Akses ditolak.');

        $tab = $request->get('tab', 'pending');

        if ($user->isAdminProdi()) {
            $pendingFilter = function ($q) {
                $q->where(function ($pq) {
                    $pq->whereIn('department', ['Program Studi Teknologi Informasi', 'PSTI', 'Program Studi PSTI'])
                       ->whereIn('status', [Document::STATUS_SUBMITTED, Document::STATUS_REVIEW]);
                })->orWhere(function ($sq) {
                    $sq->whereNotIn('department', ['Program Studi Teknologi Informasi', 'PSTI', 'Program Studi PSTI'])
                       ->where('prodi_approval_status', 'pending');
                });
            };
            $revisionFilter = function ($q) {
                $q->where(function ($pq) {
                    $pq->whereIn('department', ['Program Studi Teknologi Informasi', 'PSTI', 'Program Studi PSTI'])
                       ->where('status', Document::STATUS_REVISION);
                })->orWhere(function ($sq) {
                    $sq->whereNotIn('department', ['Program Studi Teknologi Informasi', 'PSTI', 'Program Studi PSTI'])
                       ->where('prodi_approval_status', 'rejected');
                });
            };
            $approvedFilter = function ($q) {
                $q->where(function ($pq) {
                    $pq->whereIn('department', ['Program Studi Teknologi Informasi', 'PSTI', 'Program Studi PSTI'])
                       ->where('status', Document::STATUS_APPROVED);
                })->orWhere(function ($sq) {
                    $sq->whereNotIn('department', ['Program Studi Teknologi Informasi', 'PSTI', 'Program Studi PSTI'])
                       ->where('prodi_approval_status', 'approved');
                });
            };
        } elseif (!$user->isSuperAdmin() && $user->department) {
            $dept = $user->department;
            $pendingFilter = fn($q) => $q->where('department', $dept)->whereIn('status', [Document::STATUS_SUBMITTED, Document::STATUS_REVIEW]);
            $revisionFilter = fn($q) => $q->where('department', $dept)->where('status', Document::STATUS_REVISION);
            $approvedFilter = fn($q) => $q->where('department', $dept)->where('status', Document::STATUS_APPROVED);
        } else {
            $pendingFilter = fn($q) => $q->where(function($sq) {
                $sq->whereIn('status', [Document::STATUS_SUBMITTED, Document::STATUS_REVIEW])
                   ->orWhere('prodi_approval_status', 'pending');
            });
            $revisionFilter = fn($q) => $q->where(function($sq) {
                $sq->where('status', Document::STATUS_REVISION)
                   ->orWhere('prodi_approval_status', 'rejected');
            });
            $approvedFilter = fn($q) => $q->where('status', Document::STATUS_APPROVED);
        }

        $pendingCount = Document::query()->where($pendingFilter)->count();
        $revisionCount = Document::query()->where($revisionFilter)->count();

        $query = Document::with(['category', 'creator', 'latestVersion']);
        if ($tab === 'revision') {
            $query->where($revisionFilter);
        } elseif ($tab === 'approved') {
            $query->where($approvedFilter);
        } else {
            $query->where($pendingFilter);
        }

        $documents = $query->orderBy('updated_at', 'desc')->paginate(15)->withQueryString();

        return view('approvals.index', compact('documents', 'tab', 'pendingCount', 'revisionCount'));
    }

    public function approve(Request $request, $document)
    {
        $user = auth()->user();
        abort_unless($user && $user->canApproveDocuments(), 403, 'Akses ditolak.');

        $doc = $document instanceof Document ? $document : Document::where('uuid', $document)->orWhere('id', $document)->firstOrFail();

        $request->validate([
            'notes' => 'nullable|string|max:2000',
        ]);

        $isSharedToProdi = $doc->isFromOutsideProdi() && $doc->isSharedToProdi();

        if ($isSharedToProdi && $user->matchesDepartment('Program Studi Teknologi Informasi')) {
            $doc->update([
                'prodi_approval_status' => 'approved',
                'prodi_approved_by' => $user->id,
                'prodi_approved_at' => now(),
            ]);
            $actionMsg = "Dokumen sharing '{$doc->name}' berhasil di-ACC (disetujui) untuk Program Studi Teknologi Informasi.";
        } else {
            $doc->update([
                'status' => Document::STATUS_APPROVED,
                'approved_by' => $user->id,
                'approved_at' => now(),
                'prodi_approval_status' => 'approved',
            ]);
            $actionMsg = "Dokumen '{$doc->name}' berhasil di-ACC (disetujui).";
        }

        DocumentApproval::create([
            'document_id' => $doc->id,
            'user_id' => $user->id,
            'status' => 'approved',
            'notes' => $request->notes ?? ('Disetujui oleh ' . $user->name),
        ]);

        AuditLog::log(
            'document_approved',
            $actionMsg,
            Document::class,
            $doc->id,
        );

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $actionMsg,
                'status' => 'approved',
                'status_label' => 'Disetujui (ACC)',
                'status_color' => 'green',
            ]);
        }

        return back()->with('success', $actionMsg);
    }

    public function requestRevision(Request $request, $document)
    {
        $user = auth()->user();
        abort_unless($user && $user->canApproveDocuments(), 403, 'Akses ditolak.');

        $doc = $document instanceof Document ? $document : Document::where('uuid', $document)->orWhere('id', $document)->firstOrFail();

        $request->validate([
            'notes' => 'required|string|max:2000',
        ]);

        $isSharedToProdi = $doc->isFromOutsideProdi() && $doc->isSharedToProdi();

        if ($isSharedToProdi && $user->matchesDepartment('Program Studi Teknologi Informasi')) {
            $doc->update([
                'prodi_approval_status' => 'rejected',
            ]);
            $msg = "Dokumen sharing '{$doc->name}' ditolak / diminta revisi untuk Program Studi.";
        } else {
            $doc->update([
                'status' => Document::STATUS_REVISION,
            ]);
            $msg = "Dokumen '{$doc->name}' diminta revisi.";
        }

        DocumentApproval::create([
            'document_id' => $doc->id,
            'user_id' => $user->id,
            'status' => 'revision',
            'notes' => $request->notes,
        ]);

        AuditLog::log(
            'document_revision_requested',
            $msg . " Catatan: {$request->notes}",
            Document::class,
            $doc->id,
        );

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'status' => 'revision',
                'status_label' => 'Perlu Revisi',
                'status_color' => 'red',
            ]);
        }

        return back()->with('success', $msg);
    }

    public function review($document)
    {
        $user = auth()->user();
        abort_unless($user && $user->canApproveDocuments(), 403, 'Akses ditolak.');

        $doc = $document instanceof Document ? $document : Document::where('uuid', $document)->orWhere('id', $document)->firstOrFail();

        $doc->update(['status' => Document::STATUS_REVIEW]);

        DocumentApproval::create([
            'document_id' => $doc->id,
            'user_id' => $user->id,
            'status' => 'review',
            'notes' => 'Dokumen sedang direview oleh ' . $user->name,
        ]);

        AuditLog::log(
            'document_review_started',
            "Review dokumen '{$doc->name}' dimulai oleh " . $user->name . ".",
            Document::class,
            $doc->id,
        );

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Dokumen sedang direview.',
                'status' => 'review',
                'status_label' => 'Sedang Direview',
                'status_color' => 'yellow',
            ]);
        }

        return back()->with('success', 'Dokumen sedang direview.');
    }
}

