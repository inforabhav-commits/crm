@extends('layouts.crm', ['title' => 'Create Team'])

@section('content')
    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <h2 class="h5 mb-3">Create Team</h2>
            <form method="post" action="{{ route('admin.teams.store') }}">
                @include('admin.teams.partials.form', ['submitLabel' => 'Create Team'])
            </form>
        </div>
    </section>
@endsection
