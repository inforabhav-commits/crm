@extends('layouts.crm', ['title' => 'Edit User'])

@section('content')
    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <h2 class="h5 mb-3">Edit User</h2>
            <form method="post" action="{{ route('admin.users.update', $user) }}">
                @method('PUT')
                @include('admin.users.partials.form', ['submitLabel' => 'Save Changes'])
            </form>
        </div>
    </section>
@endsection
