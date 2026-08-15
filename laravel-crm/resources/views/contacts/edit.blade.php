@extends('layouts.crm', ['title' => 'Edit Contact'])

@section('content')
    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <h2 class="h5 mb-3">Edit Contact</h2>
            <form method="post" action="{{ route('contacts.update', $contact) }}">
                @method('put')
                @include('contacts.partials.form', ['submitLabel' => 'Save Contact'])
            </form>
        </div>
    </section>
@endsection
