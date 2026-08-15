@extends('layouts.crm', ['title' => 'Edit Activity'])

@section('content')
    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <h2 class="h5 mb-3">Edit Activity</h2>
            <form method="post" action="{{ route('activities.update', $activity) }}">
                @method('put')
                @include('activities.partials.form', ['submitLabel' => 'Save Activity'])
            </form>
        </div>
    </section>
@endsection
