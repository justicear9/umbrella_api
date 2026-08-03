<section class="panel" data-panel="communicators">
    <div class="stack">
        <div class="card">
            <h2>Create communicator</h2>
            <p class="card-lede">Name, DOB, National/Constituency level, region &amp; constituency, Party ID. Sign-in is Party ID + password.</p>
            <form method="POST" action="{{ route('admin.communicators.store') }}">
                @csrf
                <div class="form-grid">
                    <div class="full">
                        <label for="comm-name">Full name</label>
                        <input id="comm-name" type="text" name="name" required placeholder="Jake Mensah" value="{{ old('name') }}">
                    </div>
                    <div>
                        <label for="comm-dob">Date of birth</label>
                        <input id="comm-dob" type="date" name="date_of_birth" required value="{{ old('date_of_birth') }}">
                    </div>
                    <div>
                        <label for="comm-level">Comms level</label>
                        <select id="comm-level" name="comms_level" required>
                            <option value="national" @selected(old('comms_level') === 'national')>National Comms</option>
                            <option value="constituency" @selected(old('comms_level', 'constituency') === 'constituency')>Constituency Comms</option>
                        </select>
                    </div>
                    <div>
                        <label for="comm-region">Region</label>
                        <select id="comm-region" name="region_id">
                            <option value="">— Select region —</option>
                            @foreach ($regions as $region)
                                <option value="{{ $region->id }}" @selected((string) old('region_id') === (string) $region->id)>{{ $region->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="comm-constituency-id">Constituency</label>
                        <select id="comm-constituency-id" name="constituency_id">
                            <option value="">— Select constituency —</option>
                            @foreach ($regions as $region)
                                <optgroup label="{{ $region->name }}">
                                    @foreach ($region->constituencies as $c)
                                        <option value="{{ $c->id }}" data-region="{{ $region->id }}" @selected((string) old('constituency_id') === (string) $c->id)>{{ $c->name }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="comm-occupation">Occupation</label>
                        <input id="comm-occupation" type="text" name="occupation" required placeholder="Communications Officer" value="{{ old('occupation') }}">
                    </div>
                    <div>
                        <label for="comm-party">Party ID</label>
                        <input id="comm-party" type="text" name="party_id" required placeholder="NDC-ACC-2041" value="{{ old('party_id') }}">
                    </div>
                    <div>
                        <label for="comm-email">Email (optional)</label>
                        <input id="comm-email" type="email" name="email" placeholder="jake@example.com" value="{{ old('email') }}">
                    </div>
                    <div class="full">
                        <label for="comm-password">Password</label>
                        <input id="comm-password" type="text" name="password" required placeholder="Temporary password">
                    </div>
                </div>
                <button type="submit">Create communicator</button>
            </form>

            @if (($communicators ?? collect())->isNotEmpty())
                <div class="table-wrap" style="margin-top:1.25rem">
                    <table>
                        <thead>
                        <tr><th>Name</th><th>Party ID</th><th>Level</th><th>Region / Constituency</th><th>Occupation</th><th>Terms</th><th></th></tr>
                        </thead>
                        <tbody>
                        @foreach ($communicators as $c)
                            <tr>
                                <td>{{ $c->name }}</td>
                                <td><code>{{ $c->party_id }}</code></td>
                                <td>{{ $c->comms_level ?: '—' }}</td>
                                <td>{{ $c->region?->name ?: '—' }} / {{ $c->constituencyRef?->name ?: ($c->constituency ?: '—') }}</td>
                                <td>{{ $c->occupation }}</td>
                                <td>
                                    @if ($c->terms_accepted_at)
                                        <span class="muted">Accepted</span>
                                    @else
                                        <span class="muted">Not accepted</span>
                                    @endif
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.communicators.reset-terms', $c) }}" style="display:inline;"
                                          onsubmit="return confirm('Require {{ $c->party_id }} to accept Terms again?');">
                                        @csrf
                                        <button type="submit" class="btn-muted">Reset terms</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty" style="margin-top:1.25rem">No communicators yet.</div>
            @endif
        </div>

        <div class="card">
            <h2>Create admin account</h2>
            <p class="card-lede">Email + password accounts for additional admins.</p>
            <form method="POST" action="{{ route('admin.admins.store') }}">
                @csrf
                <div class="form-grid">
                    <div>
                        <label for="admin-name">Name</label>
                        <input id="admin-name" type="text" name="name" required value="{{ old('name') }}">
                    </div>
                    <div>
                        <label for="admin-email">Email</label>
                        <input id="admin-email" type="email" name="email" required placeholder="admin@ndccomms.gh" value="{{ old('email') }}">
                    </div>
                    <div class="full">
                        <label for="admin-password">Password</label>
                        <input id="admin-password" type="password" name="password" required minlength="8">
                    </div>
                </div>
                <button type="submit">Create admin</button>
            </form>

            @if (($admins ?? collect())->isNotEmpty())
                <p class="muted" style="margin-top:1rem">Admins on file:</p>
                <ul class="muted">
                    @foreach ($admins as $a)
                        <li>{{ $a->name }} - {{ $a->email }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</section>
