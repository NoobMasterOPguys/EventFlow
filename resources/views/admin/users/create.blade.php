@extends('layouts.admin')

@section('title', 'Create user')
@section('heading', 'Create user')

@section('content')
    <a class="admin-back-link" href="{{ route('admin.users.index') }}">← All users</a>
    <section class="panel admin-panel admin-form-panel">
        <div class="admin-section-heading"><div><span class="eyebrow">NEW ACCOUNT</span><h2>Create a standard user account</h2></div></div>
        <p class="admin-muted">New accounts are always created with the user role. A password setup link will be sent to the supplied email address.</p>
        <form method="POST" action="{{ route('admin.users.store') }}" class="admin-form-grid">
            @csrf
            <div class="field"><label for="name">Name</label><input id="name" name="name" value="{{ old('name') }}" maxlength="255" autocomplete="name" required></div>
            <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="255" autocomplete="email" required></div>
            <div class="form-actions"><button class="button button-primary" type="submit">Create account</button><a class="button button-secondary" href="{{ route('admin.users.index') }}">Cancel</a></div>
        </form>
    </section>
@endsection
