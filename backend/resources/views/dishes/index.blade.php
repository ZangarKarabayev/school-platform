@extends('layouts.app')

@section('content')
    <style>
        .dishes-page { padding: 24px 0; display: grid; gap: 18px; }
        .dishes-grid { display: grid; grid-template-columns: 360px minmax(0, 1fr); gap: 18px; }
        .dishes-card { background: #fff; border: 1px solid #d1d8e5; border-radius: 20px; box-shadow: 0 12px 32px rgba(35, 64, 103, 0.08); }
        .dishes-card h1, .dishes-card h2 { margin: 0; }
        .dishes-card-header { padding: 24px 24px 0; }
        .dishes-card-body { padding: 24px; }
        .dishes-form, .dishes-filters { display: grid; gap: 12px; }
        .field { display: grid; gap: 6px; }
        .field label { font-size: 13px; font-weight: 700; color: #4e607d; }
        .field input, .field select, .field textarea { width: 100%; min-height: 44px; padding: 10px 12px; border: 1px solid #d1d8e5; border-radius: 12px; background: #fff; color: #16253d; }
        .field textarea { min-height: 110px; resize: vertical; }
        .inline-check { display: inline-flex; align-items: center; gap: 10px; font-weight: 700; color: #234067; }
        .inline-check input { width: 18px; height: 18px; }
        .dishes-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .notice { margin: 0 24px 24px; padding: 12px 14px; border-radius: 12px; background: #eaf6ea; color: #22653a; font-weight: 700; }
        .dishes-table-wrap { overflow: auto; border-top: 1px solid #e4e9f1; }
        .dishes-table { width: 100%; border-collapse: collapse; min-width: 760px; }
        .dishes-table th, .dishes-table td { padding: 14px 18px; border-bottom: 1px solid #e8edf5; text-align: left; vertical-align: top; }
        .dishes-table th { background: #f7f9fc; color: #4e607d; font-size: 12px; text-transform: uppercase; letter-spacing: 0.04em; }
        .dish-name { font-weight: 700; color: #1d3151; }
        .dish-description { margin-top: 4px; color: #71829a; font-size: 13px; }
        .dish-badge { display: inline-flex; padding: 6px 10px; border-radius: 999px; background: #eef5ff; color: #1f5cb8; font-size: 12px; font-weight: 700; }
        .dish-badge.inactive { background: #f0f2f6; color: #697991; }
        .pagination { padding: 18px 24px 24px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        .pagination-info { color: #71829a; font-size: 14px; }
        .pagination-links { display: flex; gap: 10px; }
        @media (max-width: 1080px) { .dishes-grid { grid-template-columns: 1fr; } }
    </style>

    <section class="dishes-page">
        <div class="dishes-grid">
            <div class="dishes-card">
                <div class="dishes-card-header">
                    <div class="muted">{{ __('ui.common.home') }}</div>
                    <h1>{{ __('ui.menu.dishes') }}</h1>
                </div>
                <div class="dishes-card-body">
                    <form method="POST" action="{{ route('dishes.store') }}" class="dishes-form">
                        @csrf
                        <div class="field">
                            <label for="name">{{ __('admin.labels.name') }}</label>
                            <input id="name" name="name" type="text" value="{{ old('name') }}" required>
                        </div>
                        <div class="field">
                            <label for="category">{{ __('admin.labels.category') }}</label>
                            <input id="category" name="category" type="text" value="{{ old('category') }}" required>
                        </div>
                        <div class="field">
                            <label for="calories">{{ __('admin.labels.calories') }}</label>
                            <input id="calories" name="calories" type="number" min="0" value="{{ old('calories') }}">
                        </div>
                        <div class="field">
                            <label for="description">{{ __('admin.labels.description') }}</label>
                            <textarea id="description" name="description">{{ old('description') }}</textarea>
                        </div>
                        <label class="inline-check" for="is_active">
                            <input id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', '1') === '1')>
                            <span>{{ __('admin.labels.active') }}</span>
                        </label>
                        <div class="dishes-actions">
                            <button class="btn" type="submit">{{ __('ui.common.save') }}</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="dishes-card">
                <div class="dishes-card-header">
                    <h2>{{ __('admin.labels.dishes') }}</h2>
                </div>
                @if (session('status'))
                    <div class="notice">{{ session('status') }}</div>
                @endif
                <div class="dishes-card-body">
                    <form method="GET" action="{{ route('dishes.index') }}" class="dishes-filters">
                        <div class="field">
                            <label for="search">{{ __('ui.common.search') }}</label>
                            <input id="search" name="search" type="text" value="{{ $filters['search'] }}" placeholder="{{ __('ui.common.name_or_description') }}">
                        </div>
                        <div class="field">
                            <label for="filter-category">{{ __('admin.labels.category') }}</label>
                            <select id="filter-category" name="category">
                                <option value="">{{ __('ui.common.all') }}</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category }}" @selected($filters['category'] === $category)>{{ $category }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="filter-status">{{ __('admin.labels.status') }}</label>
                            <select id="filter-status" name="is_active">
                                <option value="">{{ __('ui.common.all') }}</option>
                                <option value="1" @selected($filters['is_active'] === '1')>{{ __('ui.common.active') }}</option>
                                <option value="0" @selected($filters['is_active'] === '0')>{{ __('ui.common.inactive') }}</option>
                            </select>
                        </div>
                        <div class="dishes-actions">
                            <button class="btn" type="submit">{{ __('ui.common.filter') }}</button>
                            <a class="btn secondary" href="{{ route('dishes.index') }}">{{ __('ui.common.reset') }}</a>
                        </div>
                    </form>
                </div>
                @if ($dishes->isEmpty())
                    <div class="dishes-card-body muted">{{ __('ui.messages.no_dishes_found') }}</div>
                @else
                    <div class="dishes-table-wrap">
                        <table class="dishes-table">
                            <thead>
                                <tr>
                                    <th>{{ __('admin.labels.name') }}</th>
                                    <th>{{ __('admin.labels.category') }}</th>
                                    <th>{{ __('admin.labels.calories') }}</th>
                                    <th>{{ __('admin.labels.status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($dishes as $dish)
                                    <tr>
                                        <td>
                                            <div class="dish-name">{{ $dish->name }}</div>
                                            <div class="dish-description">{{ $dish->description ?: '-' }}</div>
                                        </td>
                                        <td>{{ $dish->category }}</td>
                                        <td>{{ $dish->calories ?? '-' }}</td>
                                        <td>
                                            <span class="dish-badge {{ $dish->is_active ? '' : 'inactive' }}">
                                                {{ $dish->is_active ? __('ui.common.active') : __('ui.common.inactive') }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="pagination">
                        <div class="pagination-info">{{ $dishes->firstItem() }}-{{ $dishes->lastItem() }} / {{ $dishes->total() }}</div>
                        <div class="pagination-links">
                            @if ($dishes->onFirstPage())
                                <span class="btn secondary" aria-disabled="true">{{ __('ui.common.previous') }}</span>
                            @else
                                <a class="btn secondary" href="{{ $dishes->previousPageUrl() }}">{{ __('ui.common.previous') }}</a>
                            @endif
                            @if ($dishes->hasMorePages())
                                <a class="btn" href="{{ $dishes->nextPageUrl() }}">{{ __('ui.common.next') }}</a>
                            @else
                                <span class="btn secondary" aria-disabled="true">{{ __('ui.common.next') }}</span>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
