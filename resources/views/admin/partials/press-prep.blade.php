<section class="panel" data-panel="press-prep">
    <div class="stack">
        <div class="card">
            <h2>Assign Press Prep</h2>
            <p class="card-lede">Send a communicator a prep assignment with outing type, difficulty, and topics.</p>
            <form method="POST" action="{{ route('admin.press-prep.assign') }}">
                @csrf
                <div class="form-grid">
                    <div>
                        <label for="prep-user">Communicator</label>
                        <select id="prep-user" name="user_id" required>
                            <option value="">Select…</option>
                            @foreach (($communicators ?? []) as $c)
                                <option value="{{ $c->id }}" @selected(old('user_id') == $c->id)>{{ $c->name }} ({{ $c->party_id }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="prep-outing">Outing</label>
                        <select id="prep-outing" name="outing_type" required>
                            @foreach (($outingTypes ?? []) as $key => $label)
                                <option value="{{ $key }}" @selected(old('outing_type') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="prep-difficulty">Difficulty</label>
                        <select id="prep-difficulty" name="difficulty" required>
                            @foreach (($difficulties ?? []) as $key => $label)
                                <option value="{{ $key }}" @selected(old('difficulty') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="prep-mode">Mode</label>
                        <select id="prep-mode" name="interview_mode" required>
                            <option value="voice" @selected(old('interview_mode', 'voice') === 'voice')>Voice</option>
                            <option value="text" @selected(old('interview_mode') === 'text')>Text</option>
                        </select>
                    </div>
                    <div class="full">
                        <label>Topics</label>
                        @foreach (($categories ?? []) as $cat)
                            @if ($cat['query'])
                                <label class="check">
                                    <input type="checkbox" name="topics[]" value="{{ $cat['query'] }}" @checked(collect(old('topics', []))->contains($cat['query']))>
                                    {{ $cat['label'] }}
                                </label>
                            @endif
                        @endforeach
                    </div>
                    <div>
                        <label for="prep-count">Question count</label>
                        <select id="prep-count" name="question_count">
                            <option value="5" @selected(old('question_count', '5') == '5')>5</option>
                            <option value="10" @selected(old('question_count') == '10')>10</option>
                            <option value="15" @selected(old('question_count') == '15')>15</option>
                        </select>
                    </div>
                    <div>
                        <label for="prep-note">Note (optional)</label>
                        <input id="prep-note" type="text" name="assignment_note" placeholder="Mid-year radio prep" value="{{ old('assignment_note') }}">
                    </div>
                    <div class="full">
                        <label for="prep-hot">Hot issues (optional)</label>
                        <input id="prep-hot" type="text" name="hot_issues" placeholder="Fuel, cedi…" value="{{ old('hot_issues') }}">
                    </div>
                </div>
                <button type="submit">Assign prep</button>
            </form>
        </div>

        <div class="card">
            <h2>Communicator Press Prep scores</h2>
            <p class="card-lede">Scored sessions with readiness and pillar breakdown.</p>
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
                    @forelse (($prepScores ?? []) as $s)
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
                            <td colspan="5"><div class="empty">No scored sessions yet.</div></td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
