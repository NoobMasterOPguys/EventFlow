@extends('layouts.app')

@section('title', 'Edit '.$event->title)

@section('content')
    <div class="page-heading-row">
        <div><a class="back-link" href="{{ route('events.show', $event) }}">← Back to workspace</a><span class="eyebrow">MAKE IT YOURS</span><h1>Edit event</h1><p class="page-lead">Keep the whole team on the same page.</p></div>
    </div>
    <section class="panel form-panel">
        <form method="POST" action="{{ route('events.update', $event) }}">
            @csrf
            @method('PUT')
            @include('events._form', ['event' => $event])
        </form>
    </section>
@endsection
