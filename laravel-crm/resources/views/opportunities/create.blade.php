@extends('layouts.crm', ['title' => 'Create Opportunity'])

@section('content')
    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <h2 class="h5 mb-3">Create Opportunity</h2>
            <form method="post" action="{{ route('opportunities.store') }}">
                @include('opportunities.partials.form', ['submitLabel' => 'Create Opportunity'])
            </form>
        </div>
    </section>
@endsection
