@extends('layouts.crm', ['title' => 'Edit Team'])

@section('content')
    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <h2 class="h5 mb-3">Edit Team</h2>
            <form method="post" action="{{ route('admin.teams.update', $team) }}">
                @method('PUT')
                @include('admin.teams.partials.form', ['submitLabel' => 'Save Changes'])
            </form>
        </div>
    </section>
@endsection
