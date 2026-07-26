<section class="panel" data-panel="media">
    <div class="stack">
        <div class="card">
            <h2>Upload media</h2>
            <p class="card-lede">Documents, video, audio, photos (max 100 MB). Same audience targeting as Notices.</p>
            <form method="POST" action="{{ route('admin.media.store') }}" enctype="multipart/form-data">
                @csrf
                <label for="media-title">Title</label>
                <input id="media-title" type="text" name="title" required maxlength="255" value="{{ old('title') }}">

                <label for="media-desc">Description</label>
                <textarea id="media-desc" name="description" rows="3">{{ old('description') }}</textarea>

                <label for="media-file">File</label>
                <input id="media-file" type="file" name="file" required accept=".pdf,.xlsx,.xls,.doc,.docx,.mp4,.mov,.mp3,.wav,.jpg,.jpeg,.heic,.png,application/pdf,image/*,audio/*,video/*">

                <label for="media-kind">Kind (optional override)</label>
                <select id="media-kind" name="kind">
                    <option value="">Auto-detect</option>
                    <option value="document">Document</option>
                    <option value="video">Video</option>
                    <option value="audio">Audio</option>
                    <option value="photo">Photo</option>
                </select>

                <label for="media-audience">Audience</label>
                <select id="media-audience" name="audience_mode" required>
                    <option value="all" @selected(old('audience_mode', 'all') === 'all')>All communicators</option>
                    <option value="group_national" @selected(old('audience_mode') === 'group_national')>National Comms</option>
                    <option value="group_constituency" @selected(old('audience_mode') === 'group_constituency')>Constituency Comms</option>
                    <option value="regions" @selected(old('audience_mode') === 'regions')>Selected region(s)</option>
                    <option value="constituencies" @selected(old('audience_mode') === 'constituencies')>Selected constituency(ies)</option>
                </select>

                <label for="media-targets">Targets</label>
                <select id="media-targets" name="target_ids[]" multiple size="8">
                    @foreach ($regions as $region)
                        <optgroup label="{{ $region->name }}">
                            <option value="r:{{ $region->id }}">Region: {{ $region->name }}</option>
                            @foreach ($region->constituencies as $c)
                                <option value="c:{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>

                <div class="row">
                    <button type="submit" name="action" value="draft">Upload draft</button>
                    <button type="submit" name="action" value="publish">Upload & publish</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h2>Media library</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr><th>Title</th><th>Kind</th><th>Status</th><th>Size</th><th>Audience</th><th>Downloads</th><th></th></tr>
                    </thead>
                    <tbody>
                    @forelse ($mediaAssets as $m)
                        <tr>
                            <td><strong>{{ $m->title }}</strong><br><span class="muted">{{ $m->original_filename }}</span></td>
                            <td>{{ $m->kind }}</td>
                            <td><span class="status {{ $m->status === 'published' ? 'ready' : 'pending' }}">{{ $m->status }}</span></td>
                            <td class="muted">{{ number_format($m->byte_size / 1048576, 2) }} MB</td>
                            <td class="muted">{{ $m->audience_mode }}</td>
                            <td>{{ $m->download_count }}</td>
                            <td class="row">
                                @if ($m->status !== 'published')
                                    <form method="POST" action="{{ route('admin.media.publish', $m) }}">
                                        @csrf
                                        <button type="submit">Publish</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.media.unpublish', $m) }}">
                                        @csrf
                                        <button type="submit">Unpublish</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('admin.media.destroy', $m) }}" onsubmit="return confirm('Delete media file?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn-danger" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty">No media yet.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
