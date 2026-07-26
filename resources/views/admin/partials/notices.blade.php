<section class="panel" data-panel="notices">
    <div class="stack">
        <div class="card">
            <h2>Publish notice</h2>
            <p class="card-lede">Broadcast or target by group. Optional tags narrow Constituency or Region. Publish sends in-app + email + push.</p>
            <form method="POST" action="{{ route('admin.notices.store') }}">
                @csrf
                <label for="notice-title">Title</label>
                <input id="notice-title" type="text" name="title" required maxlength="255" value="{{ old('title') }}">

                <label for="notice-body">Body</label>
                <textarea id="notice-body" name="body" rows="5" required>{{ old('body') }}</textarea>

                <label for="notice-link">Optional link URL</label>
                <input id="notice-link" type="url" name="link_url" value="{{ old('link_url') }}" placeholder="https://…">

                <label for="notice-priority">Priority</label>
                <select id="notice-priority" name="priority">
                    <option value="normal" @selected(old('priority', 'normal') === 'normal')>Normal</option>
                    <option value="urgent" @selected(old('priority') === 'urgent')>Urgent</option>
                </select>

                @include('admin.partials.audience-targeting', ['prefix' => 'notice', 'regions' => $regions])

                <div class="row">
                    <button type="submit" name="action" value="draft">Save draft</button>
                    <button type="submit" name="action" value="publish">Publish now</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h2>Notices</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr><th>Title</th><th>Status</th><th>Audience</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    @forelse ($notices as $n)
                        <tr>
                            <td>
                                <strong>{{ $n->title }}</strong>
                                @if ($n->priority === 'urgent')
                                    <span class="status failed">urgent</span>
                                @endif
                            </td>
                            <td><span class="status {{ $n->status === 'published' ? 'ready' : 'pending' }}">{{ $n->status }}</span></td>
                            <td class="muted">{{ $n->audience_mode }}</td>
                            <td class="row">
                                @if ($n->status !== 'published')
                                    <form method="POST" action="{{ route('admin.notices.publish', $n) }}">
                                        @csrf
                                        <button type="submit">Publish</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.notices.unpublish', $n) }}">
                                        @csrf
                                        <button type="submit">Unpublish</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('admin.notices.destroy', $n) }}" onsubmit="return confirm('Delete notice?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn-danger" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="empty">No notices yet.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
