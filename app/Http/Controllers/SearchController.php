<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        // Viewer only sees published documents that are not archived
        $query = Document::with(['category', 'creator', 'latestVersion'])
            ->forViewer();

        // 1. Search keyword
        if ($request->filled('q')) {
            $searchTerm = $request->q;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('document_number', 'like', "%{$searchTerm}%")
                  ->orWhere('description', 'like', "%{$searchTerm}%")
                  ->orWhere('department', 'like', "%{$searchTerm}%")
                  ->orWhereHas('category', function ($cq) use ($searchTerm) {
                      $cq->where('name', 'like', "%{$searchTerm}%");
                  });
            });
        }

        // 2. Filter Kategori
        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }

        // 3. Filter Biro / Unit
        if ($request->filled('biro')) {
            $query->where('department', $request->biro);
        }

        // 4. Filter Tahun (berdasarkan display_date)
        if ($request->filled('year')) {
            $query->whereYear('display_date', $request->year);
        }

        // 5. Filter Jenis Dokumen (extension)
        if ($request->filled('type')) {
            $type = strtolower($request->type);
            $query->whereHas('versions', function ($vq) use ($type) {
                $vq->where('file_path', 'like', "%.{$type}");
            });
        }

        // Ordered by display_date (tanggal yang dilihat user)
        $documents = $query->orderBy('display_date', 'desc')
            ->paginate(15)
            ->withQueryString();

        // Data filter untuk dropdown
        $categories = Category::where('is_active', true)->orderBy('name')->get();
        $biros = array_values(array_unique(array_merge(
            User::BIROS,
            ['Program Studi PSTI'],
            Document::distinct()->whereNotNull('department')->pluck('department')->toArray()
        )));
        sort($biros);

        $years = Document::forViewer()
            ->select(DB::raw('YEAR(display_date) as year'))
            ->whereNotNull('display_date')
            ->distinct()
            ->pluck('year')
            ->sortDesc()
            ->values();

        $types = ['pdf' => 'PDF', 'docx' => 'DOCX / DOC', 'xlsx' => 'XLSX / XLS', 'pptx' => 'PPTX / PPT', 'png' => 'Gambar / PNG', 'zip' => 'ZIP'];

        return view('search.index', compact('documents', 'categories', 'biros', 'years', 'types'));
    }
}
