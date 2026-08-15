@extends('layouts.crm', ['title' => 'Edit Opportunity'])

@section('content')
    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <h2 class="h5 mb-3">Edit Opportunity</h2>
            <form method="post" action="{{ route('opportunities.update', $opportunity) }}">
                @method('put')
                @include('opportunities.partials.form', ['submitLabel' => 'Save Opportunity'])
            </form>
        </div>
    </section>
@endsection
