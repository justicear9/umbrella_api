<section class="panel" data-panel="settings">
    <div class="stack">
        <div class="card">
            <h2>Google Gemini TTS</h2>
            <p class="card-lede">
                Ghanaian Male/Lady voices need a Gemini API key. Status:
                <strong style="color:{{ $geminiConfigured ? 'var(--accent)' : '#c05621' }}">
                    {{ $geminiConfigured ? 'configured' : 'missing' }}
                </strong>
                @if ($geminiKeyMasked)
                    · current key {{ $geminiKeyMasked }}
                @endif
            </p>
            <form method="POST" action="{{ route('admin.ai-keys') }}">
                @csrf
                <label for="gemini-key">Gemini API key (leave blank to keep current)</label>
                <input id="gemini-key" type="password" name="gemini_api_key" placeholder="Paste Google AI Studio / Gemini API key" autocomplete="off">
                <label for="gemini-model">TTS model</label>
                <input id="gemini-model" type="text" name="gemini_tts_model" value="{{ $geminiModel }}" placeholder="gemini-2.5-flash-preview-tts">
                <p class="muted" style="margin-top:-0.35rem;margin-bottom:1rem;">
                    Or edit <code>api/.env</code> → <code>GEMINI_API_KEY=...</code> then restart artisan serve.
                </p>
                <button type="submit">Save Gemini settings</button>
            </form>
        </div>
    </div>
</section>
