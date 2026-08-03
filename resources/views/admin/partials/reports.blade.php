<section class="panel" data-panel="reports">
    <div class="stack">
        <div class="card">
            <h2>National Chat reports</h2>
            <p class="card-lede">
                Act on objectionable content within 24 hours: remove the message and, when needed, suspend the communicator.
                Open reports: <strong>{{ $contentReports->where('status', 'open')->count() }}</strong>
            </p>
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
                            <td>
                                @if ($report->status === 'open')
                                    <form method="POST" action="{{ route('admin.reports.resolve', $report) }}" style="display:inline;">
                                        @csrf
                                        <button type="submit" name="action" value="remove">Remove</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.reports.resolve', $report) }}" style="display:inline;"
                                          onsubmit="return confirm('Remove message and suspend this communicator?');">
                                        @csrf
                                        <button type="submit" name="action" value="remove_and_suspend">Remove + suspend</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.reports.resolve', $report) }}" style="display:inline;">
                                        @csrf
                                        <button type="submit" name="action" value="dismiss" class="btn-muted">Dismiss</button>
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
