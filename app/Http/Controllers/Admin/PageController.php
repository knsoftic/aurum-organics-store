<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PageController extends Controller
{
    public function index()
    {
        return view('admin.pages.index', ['pages' => Page::orderBy('title')->get()]);
    }

    public function create()
    {
        return view('admin.pages.form', ['page' => new Page(['is_active' => true, 'show_in_footer' => true])]);
    }

    public function store(Request $request)
    {
        Page::create($this->validated($request));

        return redirect()->route('admin.pages.index')->with('success', 'Page created.');
    }

    public function edit(Page $page)
    {
        return view('admin.pages.form', compact('page'));
    }

    public function update(Request $request, Page $page)
    {
        $page->update($this->validated($request, $page));

        return redirect()->route('admin.pages.index')->with('success', 'Page updated.');
    }

    public function destroy(Page $page)
    {
        $page->delete();

        return back()->with('success', 'Page deleted.');
    }

    protected function validated(Request $request, ?Page $page = null): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:150',
            'slug' => ['nullable', 'string', 'max:160', Rule::unique('pages', 'slug')->ignore($page)],
            'content' => 'nullable|string|max:60000',
            'meta_title' => 'nullable|string|max:160',
            'meta_description' => 'nullable|string|max:500',
            'focus_keyword' => 'nullable|string|max:120',
        ]);

        $data['slug'] = Str::slug($data['slug'] ?: $data['title']);
        $data['is_active'] = $request->boolean('is_active');
        $data['show_in_footer'] = $request->boolean('show_in_footer');

        return $data;
    }
}
