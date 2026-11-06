@extends('layouts.admin')

@section('title', 'Event categories')
@section('heading', 'Event categories')

@section('content')
    <div class="admin-dashboard-grid category-admin-grid">
        <section class="panel admin-panel">
            <div class="admin-section-heading"><div><span class="eyebrow">CENTRAL CATALOG</span><h2>Manage categories</h2></div></div>
            <p class="admin-muted">Active categories appear in event creation and filtering. Renaming a category updates its existing events.</p>
            <form method="POST" action="{{ route('admin.categories.store') }}" class="admin-category-create">
                @csrf
                <div class="field"><label for="name">Category name</label><input id="name" name="name" value="{{ old('name') }}" maxlength="100" required></div>
                <div class="field"><label for="description">Description</label><textarea id="description" name="description" maxlength="1000" rows="2">{{ old('description') }}</textarea></div>
                <div class="field"><label for="color">Color</label><input id="color" name="color" type="color" value="{{ old('color', '#6c4cf1') }}" required></div>
                <div class="field"><label for="icon">Icon label</label><input id="icon" name="icon" value="{{ old('icon') }}" maxlength="50" placeholder="Optional"></div>
                <button class="button button-primary button-small" type="submit">Add category</button>
            </form>
        </section>
        <section class="panel admin-panel">
            <div class="admin-section-heading"><div><span class="eyebrow">IN USE</span><h2>Category catalog</h2></div></div>
            <div class="admin-category-list">
                @forelse ($categories as $category)
                    <article class="admin-category-row">
                        <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="admin-category-edit">
                            @csrf @method('PATCH')
                            <span class="category-color-swatch" style="--category-color: {{ $category->color }}"></span>
                            <div class="field"><label class="sr-only" for="category-name-{{ $category->id }}">Category name</label><input id="category-name-{{ $category->id }}" name="name" value="{{ $category->name }}" maxlength="100" required></div>
                            <div class="field"><label class="sr-only" for="category-description-{{ $category->id }}">Category description</label><textarea id="category-description-{{ $category->id }}" name="description" maxlength="1000" rows="2" placeholder="Description">{{ $category->description }}</textarea></div>
                            <div class="field"><label class="sr-only" for="category-color-{{ $category->id }}">Category color</label><input id="category-color-{{ $category->id }}" name="color" type="color" value="{{ $category->color }}" required></div>
                            <div class="field"><label class="sr-only" for="category-icon-{{ $category->id }}">Category icon</label><input id="category-icon-{{ $category->id }}" name="icon" value="{{ $category->icon }}" maxlength="50" placeholder="Icon"></div>
                            <button class="button button-secondary button-small" type="submit">Save</button>
                        </form>
                        <span class="admin-category-count">{{ $category->events_count }} events</span>
                        <form method="POST" action="{{ route('admin.categories.status', $category) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="is_active" value="{{ $category->is_active ? 0 : 1 }}">
                            <button class="button button-quiet button-small" type="submit">{{ $category->is_active ? 'Deactivate' : 'Activate' }}</button>
                        </form>
                        @if ($category->events_count === 0)
                            <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" data-confirm="Delete this unused category?">@csrf @method('DELETE')<button class="icon-button text-danger" type="submit" aria-label="Delete {{ $category->name }}">×</button></form>
                        @endif
                    </article>
                @empty
                    <p class="admin-empty">No categories have been created.</p>
                @endforelse
            </div>
            <div class="pagination-wrap">{{ $categories->links() }}</div>
        </section>
    </div>
@endsection
