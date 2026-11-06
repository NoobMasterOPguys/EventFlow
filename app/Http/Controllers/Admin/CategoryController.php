<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.categories.index', [
            'categories' => EventCategory::query()->withCount('events')->orderBy('name')->paginate(30),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:event_categories,name'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['nullable', 'string', 'max:50'],
        ]);
        $category = EventCategory::query()->create($validated);

        AuditLog::record($request->user(), 'category.created', $category, [
            'name' => $category->name,
        ], $request->ip());

        return back()->with('success', 'Category created.');
    }

    public function update(Request $request, EventCategory $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('event_categories', 'name')->ignore($category->getKey())],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['nullable', 'string', 'max:50'],
        ]);
        $before = $category->only(['name', 'description', 'color', 'icon']);
        DB::transaction(function () use ($category, $validated, $before): void {
            $category->update($validated);

            if ($before['name'] !== $category->name) {
                Event::query()->where('category', $before['name'])->update(['category' => $category->name]);
            }
        });

        AuditLog::record($request->user(), 'category.updated', $category, [
            'before' => $before,
            'after' => $category->only(['name', 'description', 'color', 'icon']),
        ], $request->ip());

        return back()->with('success', 'Category updated.');
    }

    public function updateStatus(Request $request, EventCategory $category): RedirectResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);
        $category->update(['is_active' => $validated['is_active']]);

        AuditLog::record($request->user(), 'category.status_changed', $category, [
            'is_active' => $category->is_active,
        ], $request->ip());

        return back()->with('success', $category->is_active ? 'Category activated.' : 'Category deactivated.');
    }

    public function destroy(Request $request, EventCategory $category): RedirectResponse
    {
        if ($category->events()->exists()) {
            return back()->with('error', 'This category is used by existing events and cannot be deleted.');
        }

        AuditLog::record($request->user(), 'category.deleted', $category, [
            'name' => $category->name,
        ], $request->ip());
        $category->delete();

        return back()->with('success', 'Category deleted.');
    }
}
