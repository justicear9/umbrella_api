<section class="panel" data-panel="media">
    <div class="stack">
        <div class="card">
            <h2>Upload media</h2>
            <p class="card-lede">Documents, video, audio, photos (max 100 MB). Pick audience; optional target tags narrow Constituency or Region.</p>
            <form method="POST" action="{{ route('admin.media.store') }}" enctype="multipart/form-data" data-media-upload data-max-bytes="104857600">
                @csrf
                <label for="media-title">Title</label>
                <input id="media-title" type="text" name="title" required maxlength="255" value="{{ old('title') }}">

                <label for="media-desc">Description</label>
                <textarea id="media-desc" name="description" rows="3">{{ old('description') }}</textarea>

                <label for="media-file">File <span class="muted">(max 100 MB)</span></label>
                <input id="media-file" type="file" name="file" required accept=".pdf,.xlsx,.xls,.doc,.docx,.mp4,.mov,.mp3,.wav,.jpg,.jpeg,.heic,.png,application/pdf,image/*,audio/*,video/*">
                <p class="muted" data-media-file-hint></p>

                <label for="media-kind">Kind (optional override)</label>
                <select id="media-kind" name="kind">
                    <option value="">Auto-detect</option>
                    <option value="document">Document</option>
                    <option value="video">Video</option>
                    <option value="audio">Audio</option>
                    <option value="photo">Photo</option>
                </select>

                @include('admin.partials.audience-targeting', ['prefix' => 'media', 'regions' => $regions])

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
                        @php
                            $formMode = $m->audience_mode === 'constituencies' ? 'group_constituency' : $m->audience_mode;
                            $formTargets = $m->targets->map(function ($t) use ($m) {
                                if ($m->audience_mode === 'regions') {
                                    return 'r:'.$t->target_id;
                                }
                                if ($m->audience_mode === 'constituencies') {
                                    return 'c:'.$t->target_id;
                                }

                                return null;
                            })->filter()->values()->all();
                        @endphp
                        <tr>
                            <td><strong>{{ $m->title }}</strong><br><span class="muted">{{ $m->original_filename }}</span></td>
                            <td>{{ $m->kind }}</td>
                            <td><span class="status {{ $m->status === 'published' ? 'ready' : 'pending' }}">{{ $m->status }}</span></td>
                            <td class="muted">{{ number_format($m->byte_size / 1048576, 2) }} MB</td>
                            <td class="muted">{{ $m->audience_mode }}</td>
                            <td>{{ $m->download_count }}</td>
                            <td class="row">
                                <button type="button" class="btn-muted" data-toggle-media-edit="media-edit-{{ $m->id }}">Edit</button>
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
                        <tr id="media-edit-{{ $m->id }}" class="media-edit-row" hidden>
                            <td colspan="7">
                                <form
                                    method="POST"
                                    action="{{ route('admin.media.update', $m) }}"
                                    enctype="multipart/form-data"
                                    class="media-edit-form"
                                    data-media-upload
                                    data-max-bytes="104857600"
                                >
                                    @csrf
                                    @method('PUT')
                                    <p class="muted" style="margin-top:0">Edit #{{ $m->id }} — leave file empty to keep <code>{{ $m->original_filename }}</code>. Saving does not re-notify; use Publish for delivery.</p>
                                    <label for="media-edit-title-{{ $m->id }}">Title</label>
                                    <input id="media-edit-title-{{ $m->id }}" type="text" name="title" required maxlength="255" value="{{ $m->title }}">

                                    <label for="media-edit-desc-{{ $m->id }}">Description</label>
                                    <textarea id="media-edit-desc-{{ $m->id }}" name="description" rows="3">{{ $m->description }}</textarea>

                                    <label for="media-edit-file-{{ $m->id }}">Replace file <span class="muted">(optional, max 100 MB)</span></label>
                                    <input id="media-edit-file-{{ $m->id }}" type="file" name="file" accept=".pdf,.xlsx,.xls,.doc,.docx,.mp4,.mov,.mp3,.wav,.jpg,.jpeg,.heic,.png,application/pdf,image/*,audio/*,video/*">
                                    <p class="muted" data-media-file-hint></p>

                                    <label for="media-edit-kind-{{ $m->id }}">Kind</label>
                                    <select id="media-edit-kind-{{ $m->id }}" name="kind">
                                        <option value="">Keep / auto from new file</option>
                                        <option value="document" @selected($m->kind === 'document')>Document</option>
                                        <option value="video" @selected($m->kind === 'video')>Video</option>
                                        <option value="audio" @selected($m->kind === 'audio')>Audio</option>
                                        <option value="photo" @selected($m->kind === 'photo')>Photo</option>
                                    </select>

                                    @include('admin.partials.audience-targeting', [
                                        'prefix' => 'media-edit-'.$m->id,
                                        'regions' => $regions,
                                        'defaultMode' => $formMode,
                                        'defaultTargets' => $formTargets,
                                    ])

                                    <div class="row">
                                        <button type="submit">Save changes</button>
                                        <button type="button" class="btn-muted" data-toggle-media-edit="media-edit-{{ $m->id }}">Cancel</button>
                                    </div>
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
