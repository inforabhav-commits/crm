@extends('layouts.crm', ['title' => 'Create Contact'])

@section('content')
    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <h2 class="h5 mb-3">Create Contact</h2>
            <form method="post" action="{{ route('contacts.store') }}">
                @include('contacts.partials.form', ['submitLabel' => 'Create Contact'])
            </form>
        </div>
    </section>
@endsection
