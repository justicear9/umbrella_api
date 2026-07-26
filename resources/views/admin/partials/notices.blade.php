<section class="panel" data-panel="notices">
    <div class="stack">
        <div class="card">
            <h2>Publish notice</h2>
            <p class="card-lede">Broadcast or target by group, region, or constituency. Publish sends in-app + email + push.</p>
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

                <label for="notice-audience">Audience</label>
                <select id="notice-audience" name="audience_mode" required>
                    <option value="all" @selected(old('audience_mode', 'all') === 'all')>All communicators</option>
                    <option value="group_national" @selected(old('audience_mode') === 'group_national')>National Comms</option>
                    <option value="group_constituency" @selected(old('audience_mode') === 'group_constituency')>Constituency Comms</option>
                    <option value="regions" @selected(old('audience_mode') === 'regions')>Selected region(s)</option>
                    <option value="constituencies" @selected(old('audience_mode') === 'constituencies')>Selected constituency(ies)</option>
                </select>

                <label for="notice-targets">Target IDs (regions or constituencies — hold Ctrl/Cmd for multi)</label>
                <select id="notice-targets" name="target_ids[]" multiple size="8">
                    @foreach ($regions as $region)
                        <optgroup label="{{ $region->name }} (region #{{ $region->id }})">
                            <option value="r:{{ $region->id }}">Region: {{ $region->name }}</option>
                            @foreach ($region->constituencies as $c)
                                <option value="c:{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <p class="muted">For region audience, pick “Region: …” options. For constituency audience, pick constituency rows.</p>

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
