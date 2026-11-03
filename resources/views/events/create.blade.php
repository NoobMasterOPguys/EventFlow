@extends('layouts.app')

@section('title', 'Create event')

@section('content')
    <div class="page-heading-row">
        <div><a class="back-link" href="{{ route('events.index') }}">← All events</a><span class="eyebrow">A NEW IDEA TAKES SHAPE</span><h1>Create an event</h1><p class="page-lead">Start with the details. You can invite your team next.</p></div>
    </div>
    <section class="panel form-panel">
        <form method="POST" action="{{ route('events.store') }}">
            @csrf
            @include('events._form')
        </form>
    </section>
@endsection
