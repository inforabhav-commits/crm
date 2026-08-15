@extends('layouts.crm', ['title' => 'Create User'])

@section('content')
    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <h2 class="h5 mb-3">Create User</h2>
            <form method="post" action="{{ route('admin.users.store') }}">
                @include('admin.users.partials.form', ['submitLabel' => 'Create User'])
            </form>
        </div>
    </section>
@endsection
