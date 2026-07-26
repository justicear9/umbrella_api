<section class="panel" data-panel="overview" aria-labelledby="section-title">
    <div class="stack">
        <div class="stats">
            <div class="stat">
                <div class="stat-label">Communicators</div>
                <div class="stat-value">{{ $stats['communicators'] ?? 0 }}</div>
            </div>
            <div class="stat">
                <div class="stat-label">Documents ready</div>
                <div class="stat-value">{{ $stats['documents_ready'] ?? 0 }}<span class="muted" style="font-size:.85rem;font-weight:500"> / {{ $stats['documents_total'] ?? 0 }}</span></div>
            </div>
            <div class="stat">
                <div class="stat-label">Briefings</div>
                <div class="stat-value">{{ $stats['briefings'] ?? 0 }}</div>
            </div>
            <div class="stat">
                <div class="stat-label">Gemini TTS</div>
                <div class="stat-value {{ ($stats['gemini'] ?? false) ? '' : 'is-warn' }}">
                    {{ ($stats['gemini'] ?? false) ? 'OK' : 'Missing' }}
                </div>
            </div>
        </div>

        <div class="card">
            <h2>Recent Press Prep scores</h2>
            <p class="card-lede">Latest scored sessions. Full list lives under Press Prep.</p>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Communicator</th>
                        <th>Session</th>
                        <th>Status</th>
                        <th>Readiness</th>
                        <th>Pillars</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse (($prepScores ?? collect())->take(10) as $s)
                        @php
                            $d = $s->debrief ?? [];
                            $overall = $d['overall'] ?? null;
                            $pct = is_numeric($overall) ? ($overall <= 10 ? round($overall * 10) : round($overall)) : null;
                            $scores = $d['scores'] ?? [];
                        @endphp
                        <tr class="score-row" tabindex="0" role="button" data-session-id="{{ $s->id }}" title="View transcript">
                            <td>
                                <strong>{{ $s->user?->name ?? '-' }}</strong><br>
                                <span class="muted">{{ $s->user?->party_id }} · {{ $s->user?->constituency }}</span>
                            </td>
                            <td class="muted">#{{ $s->id }} · {{ $s->outing_type }} · {{ $s->difficulty }}</td>
                            <td>
                                <span class="status">{{ $s->status }}</span>
                                @if($s->ended_early ?? ($d['ended_early'] ?? false))
                                    <span class="muted"> early</span>
                                @endif
                            </td>
                            <td><strong style="font-variant-numeric:tabular-nums">{{ $pct !== null ? $pct.'%' : '-' }}</strong></td>
                            <td class="muted">
                                A {{ $scores['accuracy'] ?? '-' }} ·
                                D {{ $scores['message_discipline'] ?? '-' }} ·
                                C {{ $scores['composure'] ?? '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty">No scored sessions yet. Assign Press Prep from that section.</div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
