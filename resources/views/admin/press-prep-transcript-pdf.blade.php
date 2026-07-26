<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Press Prep #{{ $session->id }} transcript</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #14241c; line-height: 1.45; }
        h1 { font-size: 18px; margin: 0 0 8px; }
        h2 { font-size: 14px; margin: 18px 0 8px; }
        .meta { color: #5f7469; margin-bottom: 12px; }
        .turn { margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid #d5e2db; }
        .label { font-weight: bold; color: #006b3f; }
        p { margin: 4px 0; }
    </style>
</head>
<body>
    <h1>Comrade AI — Press Prep transcript</h1>
    <div class="meta">
        Session #{{ $payload['id'] }} ·
        {{ $payload['user']['name'] ?? '-' }} ({{ $payload['user']['party_id'] ?? '-' }}) ·
        {{ $payload['outing_type'] }} · {{ $payload['difficulty'] }} · {{ $payload['interview_mode'] }}
        @if ($payload['readiness_pct'] !== null)
            · Readiness {{ $payload['readiness_pct'] }}%
        @endif
        @if ($payload['ended_early'])
            · Ended early
        @endif
    </div>
    @if (!empty($payload['summary']))
        <p><span class="label">Summary:</span> {{ $payload['summary'] }}</p>
    @endif
    <h2>Interview</h2>
    @forelse ($payload['turns'] as $i => $turn)
        <div class="turn">
            <p><span class="label">Q{{ $i + 1 }}.</span> {{ $turn['question'] }}</p>
            <p><span class="label">Answer:</span> {{ $turn['user_answer'] ?: '(no answer)' }}</p>
            @if (!empty($turn['model_answer']))
                <p><span class="label">Model:</span> {{ $turn['model_answer'] }}</p>
            @endif
            @if (!empty($turn['coach_note']))
                <p><span class="label">Coach:</span> {{ $turn['coach_note'] }}</p>
            @endif
        </div>
    @empty
        <p>No turns recorded.</p>
    @endforelse
</body>
</html>
