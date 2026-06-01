<?php

namespace App\Http\Controllers;

use App\Models\CorpusEntry;
use Illuminate\Http\Request;

class CorpusEntryController extends Controller
{
    public function index(Request $request)
    {
        $query = CorpusEntry::query();

        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($category = $request->get('category')) {
            $query->where('category', $category);
        }

        $entries = $query->orderBy('title')->paginate(20);
        $categories = CorpusEntry::select('category')->distinct()->pluck('category');

        return view('corpus.index', compact('entries', 'categories'));
    }

    public function show(CorpusEntry $corpusEntry)
    {
        return view('corpus.show', compact('corpusEntry'));
    }
}
