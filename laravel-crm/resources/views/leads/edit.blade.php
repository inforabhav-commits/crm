@extends('layouts.crm', ['title' => 'Edit Lead'])

@section('content')
    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <h2 class="h5 mb-3">Edit Lead</h2>
            <form method="post" action="{{ route('leads.update', $lead) }}">
                @method('PUT')
                @include('leads.partials.form', ['submitLabel' => 'Save Changes'])
            </form>
        </div>
    </section>
@endsection
