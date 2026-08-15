@extends('layouts.crm', ['title' => 'Create Customer'])

@section('content')
    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <h2 class="h5 mb-3">Create Customer</h2>
            <form method="post" action="{{ route('customers.store') }}">
                @include('customers.partials.form', ['submitLabel' => 'Create Customer'])
            </form>
        </div>
    </section>
@endsection
