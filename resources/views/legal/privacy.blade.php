@extends('legal.layout')

@section('title', 'Privacy Policy')

@section('content')
    <h1>Privacy Policy</h1>
    <p class="lede">Last updated: 3 August 2026</p>
    <div class="card">
        <h2>Overview</h2>
        <p>
            Comrade AI is operated for authorised political party communicators. We collect account and
            usage data needed to provide coaching features (Ask, Press Prep, National Chat, Notices, Media).
        </p>

        <h2>Data we process</h2>
        <ul>
            <li>Account details provisioned by your admin (name, Party ID, constituency/region tags, credentials).</li>
            <li>App content you create (Ask threads, Press Prep answers, National Chat messages).</li>
            <li>Device push tokens if you enable notices alerts.</li>
            <li>Content reports and block lists used for safety moderation.</li>
        </ul>

        <h2>Tracking &amp; advertising</h2>
        <p>
            Comrade AI does <strong>not</strong> use advertising identifiers to track you across other companies’ apps or websites.
            We do not sell personal data to data brokers and do not use third-party ad SDKs for tracking.
        </p>

        <h2>Sharing</h2>
        <p>
            National Chat messages are visible to other authorised communicators. Admins may access reports and
            coaching transcripts as needed to operate the service. We use infrastructure providers to host the API.
        </p>

        <h2>Contact</h2>
        <p>
            Privacy questions: <a href="mailto:support@fumbo.ai">support@fumbo.ai</a> ·
            <a href="{{ url('/support') }}">Support</a>
        </p>
    </div>
@endsection
