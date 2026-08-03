@extends('admin.layout')

@section('title', 'Comrade AI Admin')

@section('content')
    @include('admin.partials.overview')
    @include('admin.partials.communicators')
    @include('admin.partials.press-prep')
    @include('admin.partials.notices')
    @include('admin.partials.reports')
    @include('admin.partials.media')
    @include('admin.partials.documents')
    @include('admin.partials.settings')
@endsection
