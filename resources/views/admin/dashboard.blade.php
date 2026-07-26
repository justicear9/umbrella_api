@extends('admin.layout')

@section('title', 'NDC Comms Admin')

@section('content')
    @include('admin.partials.overview')
    @include('admin.partials.communicators')
    @include('admin.partials.press-prep')
    @include('admin.partials.notices')
    @include('admin.partials.media')
    @include('admin.partials.documents')
    @include('admin.partials.settings')
@endsection
