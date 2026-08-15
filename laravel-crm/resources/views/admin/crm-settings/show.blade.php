@extends('layouts.crm', ['title' => $type['label']])

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="row g-4">
        @can('crm_settings.manage')
            <div class="col-lg-4">
                <section class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="h5 mb-3">Add {{ Str::singular($type['label']) }}</h2>
                        <form method="post" action="{{ route('admin.crm-settings.store', $typeKey) }}">
                            @include('admin.crm-settings.partials.form', ['submitLabel' => 'Add Value'])
                        </form>
                    </div>
                </section>
            </div>
        @endcan

        <div class="@can('crm_settings.manage') col-lg-8 @else col-12 @endcan">
            <section class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h2 class="h5 mb-1">{{ $type['label'] }}</h2>
                            <p class="text-muted mb-0">Values are available for future CRM modules.</p>
                        </div>
                        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.crm-settings.index') }}">Back</a>
                    </div>

                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                            <tr>
                                <th>Name</th>
                                <th>Slug</th>
                                <th>Order</th>
                                <th>Default</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse ($values as $item)
                                <tr>
                                    <td>
                                        @if ($item->color)
                                            <span class="d-inline-block rounded-circle me-1" style="width: 10px; height: 10px; background: {{ $item->color }}"></span>
                                        @endif
                                        {{ $item->name }}
                                    </td>
                                    <td><code>{{ $item->slug }}</code></td>
                                    <td>{{ $item->sort_order }}</td>
                                    <td>{{ $item->is_default ? 'Yes' : 'No' }}</td>
                                    <td>
                                        <span class="badge {{ $item->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                            {{ $item->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        @can('crm_settings.manage')
                                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.crm-settings.edit', [$typeKey, $item]) }}">Edit</a>
                                            <form class="d-inline" method="post" action="{{ route('admin.crm-settings.status', [$typeKey, $item]) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button class="btn btn-sm btn-outline-secondary" type="submit">
                                                    {{ $item->is_active ? 'Deactivate' : 'Activate' }}
                                                </button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="text-muted" colspan="6">No values configured.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $values->links() }}
                </div>
            </section>
        </div>
    </div>
@endsection
