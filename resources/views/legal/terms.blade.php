@extends('legal.layout')

@section('title', 'Terms of Use')

@section('content')
    <h1>Terms of Use</h1>
    <p class="lede">Last updated: 3 August 2026</p>
    <div class="card">
        <h2>1. Acceptance</h2>
        <p>
            By signing in to Comrade AI you agree to these Terms. If you do not agree, do not use the app.
            Access is limited to authorised communicators provisioned by your organisation with a Party ID.
        </p>

        <h2>2. Not a government app</h2>
        <p>
            Comrade AI is a private coaching tool for political party communicators. It is not an official
            Government of Ghana application and does not represent any government entity or provide government services.
        </p>

        <h2>3. User-generated content — zero tolerance</h2>
        <p>
            Features such as National Chat allow communicators to post messages visible to other authorised users.
            <strong>There is no tolerance for objectionable content or abusive users.</strong>
            Prohibited content includes harassment, hate speech, threats, sexual content involving minors,
            illegal activity, spam, and doxxing.
        </p>
        <p>You must:</p>
        <ul>
            <li>Post only professional, on-message communications appropriate for party communicators.</li>
            <li>Use in-app Report to flag objectionable content.</li>
            <li>Use Block to hide abusive users from your feed immediately.</li>
        </ul>
        <p>
            We review reports and remove violating content and may suspend or eject offending accounts,
            typically within 24 hours of a valid report.
        </p>

        <h2>4. Accounts</h2>
        <p>
            Accounts are created by administrators. There is no fee to create an account through the app,
            and the app does not sell subscriptions or digital goods via In-App Purchase.
        </p>

        <h2>5. Contact</h2>
        <p>
            Support: <a href="{{ url('/support') }}">Support page</a> or
            <a href="mailto:support@fumbo.ai">support@fumbo.ai</a>
        </p>
    </div>
@endsection
