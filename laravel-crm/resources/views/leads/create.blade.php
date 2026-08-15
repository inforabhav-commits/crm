@extends('layouts.crm', ['title' => 'Create Lead'])

@section('content')
    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <h2 class="h5 mb-3">Create Lead</h2>
            <form method="post" action="{{ route('leads.store') }}">
                @include('leads.partials.form', ['submitLabel' => 'Create Lead'])
            </form>
        </div>
    </section>
@endsection
