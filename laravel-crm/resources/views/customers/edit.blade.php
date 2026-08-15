@extends('layouts.crm', ['title' => 'Edit Customer'])

@section('content')
    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <h2 class="h5 mb-3">Edit Customer</h2>
            <form method="post" action="{{ route('customers.update', $customer) }}">
                @method('put')
                @include('customers.partials.form', ['submitLabel' => 'Save Customer'])
            </form>
        </div>
    </section>
@endsection
