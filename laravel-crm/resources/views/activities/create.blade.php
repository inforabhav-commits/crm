@extends('layouts.crm', ['title' => 'Create Activity'])

@section('content')
    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <h2 class="h5 mb-3">Create Activity</h2>
            <form method="post" action="{{ route('activities.store') }}">
                @include('activities.partials.form', ['submitLabel' => 'Create Activity'])
            </form>
        </div>
    </section>
@endsection
