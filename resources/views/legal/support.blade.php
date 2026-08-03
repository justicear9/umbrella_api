@extends('legal.layout')

@section('title', 'Support')

@section('content')
    <h1>Support</h1>
    <p class="lede">Help for Comrade AI communicators and reviewers.</p>
    <div class="card">
        <h2>What is Comrade AI?</h2>
        <p>
            Comrade AI is a private communication-coaching app for authorised NDC party communicators.
            Access is by Party ID issued by your organisation — there is no public self-signup and no in-app purchases.
        </p>

        <h2>Account help</h2>
        <p>
            If you cannot sign in, contact your communications administrator to reset your Party ID password
            or confirm your account is active.
        </p>

        <h2>Contact</h2>
        <p>
            Email support:
            <a href="mailto:support@fumbo.ai">support@fumbo.ai</a>
        </p>
        <p>
            Please include your Party ID (not your password) and a short description of the issue.
            We aim to respond within one business day.
        </p>

        <h2>Safety &amp; National Chat</h2>
        <p>
            National Chat is for professional communicator collaboration only. There is no tolerance for
            objectionable content or abusive behaviour. Use Report and Block in the app, or email
            <a href="mailto:support@fumbo.ai">support@fumbo.ai</a> with details. Moderators act on reports within 24 hours.
        </p>

        <h2>Legal</h2>
        <p>
            <a href="{{ url('/terms') }}">Terms of Use</a> ·
            <a href="{{ url('/privacy') }}">Privacy Policy</a>
        </p>
    </div>
@endsection
