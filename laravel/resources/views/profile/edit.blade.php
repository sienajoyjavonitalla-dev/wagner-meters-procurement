@extends('layouts.dark')

@section('title', 'Profile')

@section('content')
<div class="app-main-inner" style="padding: 1.5rem 2rem;">
    <h1 style="font-size: 1.5rem; margin-bottom: 1.5rem; color: #e6edf3;">Profile</h1>
    <div style="max-width: 42rem; display: flex; flex-direction: column; gap: 1.5rem;">
        <div style="padding: 1.5rem; background: #161b22; border: 1px solid #30363d; border-radius: 8px;">
            @include('profile.partials.update-profile-information-form')
        </div>
        <div style="padding: 1.5rem; background: #161b22; border: 1px solid #30363d; border-radius: 8px;">
            @include('profile.partials.update-password-form')
        </div>
        <div style="padding: 1.5rem; background: #161b22; border: 1px solid #30363d; border-radius: 8px;">
            @include('profile.partials.delete-user-form')
        </div>
    </div>
</div>
@endsection
