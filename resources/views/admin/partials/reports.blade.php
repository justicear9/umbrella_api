<section class="panel" data-panel="reports">
    <div class="stack">
        <div class="card">
            <h2>National Chat reports</h2>
            <p class="card-lede">
                Review flagged messages within 24 hours.
                Open reports: <strong>{{ $contentReports->where('status', 'open')->count() }}</strong>
            </p>
            <ul class="muted" style="margin:0 0 1rem 1.1rem;line-height:1.5;">
                <li><strong>Remove message</strong> — deletes the chat message for everyone; closes related open reports.</li>
                <li><strong>Remove &amp; suspend author</strong> — same as above, and blocks that communicator from signing in.</li>
                <li><strong>Keep message / close report</strong> — leaves the message visible; marks this report resolved (false alarm).</li>
            </ul>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>When</th>
                        <th>Reporter</th>
                        <th>Author</th>
                        <th>Message</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($contentReports as $report)
                        <tr>
                            <td>{{ $report->created_at?->format('Y-m-d H:i') }}</td>
                            <td>
                                {{ $report->reporter?->name ?? '—' }}
                                <div class="muted">{{ $report->reporter?->party_id }}</div>
                            </td>
                            <td>
                                {{ $report->reportedUser?->name ?? '—' }}
                                <div class="muted">{{ $report->reportedUser?->party_id }}</div>
                                @if ($report->reportedUser?->suspended_at)
                                    <div class="muted">Suspended</div>
                                @endif
                            </td>
                            <td style="max-width:280px;white-space:normal;">
                                @if ($report->message?->trashed())
                                    <em class="muted">(removed)</em>
                                @endif
                                {{ \Illuminate\Support\Str::limit($report->message?->body ?? '—', 160) }}
                            </td>
                            <td>{{ \App\Models\ContentReport::reasonLabel($report->reason) }}</td>
                            <td>{{ $report->status }}</td>
                            <td style="white-space:normal;min-width:11rem;">
                                @if ($report->status === 'open')
                                    <form method="POST" action="{{ route('admin.reports.resolve', $report) }}" style="display:inline-block;margin:2px 0;">
                                        @csrf
                                        <input type="hidden" name="decision" value="remove">
                                        <button type="submit" title="Delete this message from National Chat for all users">Remove message</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.reports.resolve', $report) }}" style="display:inline-block;margin:2px 0;"
                                          onsubmit="return confirm('Delete the message for everyone AND suspend the author so they cannot sign in?');">
                                        @csrf
                                        <input type="hidden" name="decision" value="remove_and_suspend">
                                        <button type="submit" title="Delete message and suspend the author">Remove &amp; suspend author</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.reports.resolve', $report) }}" style="display:inline-block;margin:2px 0;"
                                          onsubmit="return confirm('Close this report and keep the message visible in chat?');">
                                        @csrf
                                        <input type="hidden" name="decision" value="dismiss">
                                        <button type="submit" class="btn-muted" title="False alarm — keep the message, close the report">Keep message / close report</button>
                                    </form>
                                @else
                                    <span class="muted">Resolved {{ $report->resolved_at?->format('Y-m-d H:i') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="muted">No content reports yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
