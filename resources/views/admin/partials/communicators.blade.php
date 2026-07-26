<section class="panel" data-panel="communicators">
    <div class="stack">
        <div class="card">
            <h2>Create communicator</h2>
            <p class="card-lede">Name, DOB, constituency, occupation, Party ID. They sign in on mobile with Party ID + password.</p>
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
                        <label for="comm-constituency">Constituency</label>
                        <input id="comm-constituency" type="text" name="constituency" required placeholder="Ablekuma West" value="{{ old('constituency') }}">
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
                        <tr><th>Name</th><th>Party ID</th><th>Constituency</th><th>Occupation</th></tr>
                        </thead>
                        <tbody>
                        @foreach ($communicators as $c)
                            <tr>
                                <td>{{ $c->name }}</td>
                                <td><code>{{ $c->party_id }}</code></td>
                                <td>{{ $c->constituency }}</td>
                                <td>{{ $c->occupation }}</td>
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
