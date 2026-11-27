@extends('layouts.portal')

@section('subtitle', $organization->name)

@section('content')
    @include('portal.organization-moderators', ['showModeratorEditor' => true])
    <a class="btn secondary" href="/portal/leadership/organization">Back to Organization</a>
@endsection
