<section class="panel" data-panel="documents">
    <div class="stack">
        <div class="card">
            <h2>Upload policy document</h2>
            <p class="card-lede">PDF sources are digested into briefing cards for the mobile app.</p>
            <form method="POST" action="{{ route('admin.upload') }}" enctype="multipart/form-data">
                @csrf
                <label for="doc-title">Title</label>
                <input id="doc-title" type="text" name="title" required placeholder="2026 Mid-Year Fiscal Policy Review" value="{{ old('title') }}">
                <label for="doc-file">PDF file</label>
                <input id="doc-file" type="file" name="file" accept="application/pdf" required>
                <label class="check">
                    <input type="checkbox" name="digest_now" value="1" @checked(old('digest_now', true))>
                    Digest with AI immediately (can take several minutes)
                </label>
                <button type="submit">Upload</button>
            </form>
        </div>

        <div class="card">
            <h2>Documents</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr><th>Title</th><th>Status</th><th>Pages / Chunks</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    @forelse ($documents as $doc)
                        <tr>
                            <td>
                                <strong>{{ $doc->title }}</strong><br>
                                <span class="muted">{{ $doc->original_filename }}</span>
                            </td>
                            <td>
                                <span class="status {{ $doc->status }}">{{ $doc->status }}</span>
                                @if ($doc->error_message)
                                    <div class="muted">{{ \Illuminate\Support\Str::limit($doc->error_message, 120) }}</div>
                                @endif
                            </td>
                            <td class="muted">{{ $doc->page_count ?? '-' }} / {{ $doc->chunk_count }}</td>
                            <td class="row">
                                <form method="POST" action="{{ route('admin.digest', $doc) }}">
                                    @csrf
                                    <button type="submit">Re-digest</button>
                                </form>
                                <form method="POST" action="{{ route('admin.documents.destroy', $doc) }}" onsubmit="return confirm('Delete document and briefings?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn-danger" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4"><div class="empty">No documents yet. Upload a PDF above.</div></td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h2>Briefing cards</h2>
            <p class="card-lede">Edit titles, categories, summaries, and publish state.</p>
            @forelse ($briefings as $b)
                <form class="briefing-form" method="POST" action="{{ route('admin.briefings.update', $b) }}">
                    @csrf
                    @method('PUT')
                    <div class="muted">{{ $b->document?->title }} · #{{ $b->id }}</div>
                    <label for="brief-title-{{ $b->id }}">Title</label>
                    <input id="brief-title-{{ $b->id }}" type="text" name="title" value="{{ $b->title }}" required>
                    <label for="brief-cat-{{ $b->id }}">Category</label>
                    <select id="brief-cat-{{ $b->id }}" name="category">
                        @foreach ($categories as $cat)
                            @if ($cat['query'])
                                <option value="{{ $cat['query'] }}" @selected($b->category === $cat['query'])>{{ $cat['label'] }}</option>
                            @endif
                        @endforeach
                    </select>
                    <label for="brief-sum-{{ $b->id }}">Summary</label>
                    <textarea id="brief-sum-{{ $b->id }}" name="summary" rows="3" required>{{ $b->summary }}</textarea>
                    <label class="check">
                        <input type="checkbox" name="published" value="1" @checked($b->published_at)>
                        Published
                    </label>
                    <button type="submit">Save</button>
                </form>
            @empty
                <div class="empty">No briefings yet - upload and digest a document.</div>
            @endforelse
        </div>
    </div>
</section>
