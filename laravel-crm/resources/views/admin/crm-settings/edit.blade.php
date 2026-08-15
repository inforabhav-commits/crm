@extends('layouts.crm', ['title' => 'Edit ' . Str::singular($type['label'])])

@section('content')
    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <h2 class="h5 mb-3">Edit {{ Str::singular($type['label']) }}</h2>
            <form method="post" action="{{ route('admin.crm-settings.update', [$typeKey, $value]) }}">
                @method('PUT')
                @include('admin.crm-settings.partials.form', ['submitLabel' => 'Save Changes'])
            </form>
        </div>
    </section>
@endsection
