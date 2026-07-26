<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\PressPrepAssignedMail;
use App\Models\Briefing;
use App\Models\Document;
use App\Models\PressPrepSession;
use App\Models\User;
use App\Services\DocumentIngestService;
use App\Services\GeminiTtsService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function loginForm()
    {
        return view('admin.login');
    }

    public function login(Request $request)
    {
        $key = (string) $request->input('admin_key');
        if (! hash_equals((string) config('ndc.admin_key'), $key)) {
            return back()->withErrors(['admin_key' => 'Invalid admin key.']);
        }

        $request->session()->put('ndc_admin_ok', true);

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('ndc_admin_ok');

        return redirect()->route('admin.login');
    }

    public function dashboard(GeminiTtsService $tts)
    {
        $env = $this->readEnvMap();
        $communicators = User::query()
            ->where('role', User::ROLE_COMMUNICATOR)
            ->orderBy('name')
            ->get();
        $admins = User::query()->where('role', User::ROLE_ADMIN)->orderBy('name')->get();
        $documents = Document::latest()->get();
        $briefings = Briefing::with('document')->latest()->limit(50)->get();
        $prepScores = PressPrepSession::query()
            ->with('user:id,name,party_id,constituency')
            ->whereNotNull('user_id')
            ->whereNotNull('debrief')
            ->latest()
            ->limit(40)
            ->get();
        $geminiConfigured = $tts->isConfigured();

        return view('admin.dashboard', [
            'documents' => $documents,
            'briefings' => $briefings,
            'categories' => config('ndc.categories'),
            'outingTypes' => config('ndc.outing_types'),
            'difficulties' => config('ndc.difficulties'),
            'communicators' => $communicators,
            'admins' => $admins,
            'prepScores' => $prepScores,
            'geminiConfigured' => $geminiConfigured,
            'geminiKeyMasked' => $this->maskSecret($env['GEMINI_API_KEY'] ?? ''),
            'geminiModel' => $env['GEMINI_TTS_MODEL'] ?? config('services.gemini.tts_model'),
            'stats' => [
                'communicators' => $communicators->count(),
                'documents_ready' => $documents->where('status', 'ready')->count(),
                'documents_total' => $documents->count(),
                'briefings' => $briefings->count(),
                'gemini' => $geminiConfigured,
            ],
        ]);
    }

    private function dashboardRedirect(string $section, string $status)
    {
        return redirect()
            ->route('admin.dashboard', ['section' => $section])
            ->with('status', $status);
    }

    public function storeCommunicator(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'date_of_birth' => ['required', 'date'],
            'constituency' => ['required', 'string', 'max:120'],
            'occupation' => ['required', 'string', 'max:120'],
            'party_id' => ['required', 'string', 'max:80', 'unique:users,party_id'],
            'password' => ['required', 'string', 'min:6', 'max:80'],
            'email' => ['nullable', 'email', 'max:160', 'unique:users,email'],
        ]);

        $partyId = trim($data['party_id']);
        $email = isset($data['email']) && trim((string) $data['email']) !== ''
            ? strtolower(trim($data['email']))
            : strtolower($partyId).'@party.ndc.local';

        User::create([
            'role' => User::ROLE_COMMUNICATOR,
            'name' => $data['name'],
            'email' => $email,
            'party_id' => $partyId,
            'date_of_birth' => $data['date_of_birth'],
            'constituency' => $data['constituency'],
            'occupation' => $data['occupation'],
            'password' => $data['password'],
        ]);

        return $this->dashboardRedirect(
            'communicators',
            'Communicator created. Party ID: '.$partyId
        );
    }

    public function storeAdmin(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:80'],
        ]);

        User::create([
            'role' => User::ROLE_ADMIN,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        return $this->dashboardRedirect('communicators', 'Admin account created for '.$data['email']);
    }

    public function assignPressPrep(Request $request)
    {
        $categoryQueries = collect(config('ndc.categories'))->pluck('query')->filter()->values()->all();

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'outing_type' => ['required', Rule::in(array_keys(config('ndc.outing_types')))],
            'difficulty' => ['required', Rule::in(array_keys(config('ndc.difficulties')))],
            'interview_mode' => ['required', Rule::in(['text', 'voice'])],
            'topics' => ['required', 'array', 'min:1'],
            'topics.*' => ['string', Rule::in($categoryQueries)],
            'question_count' => ['required', Rule::in([5, 10, 15])],
            'assignment_note' => ['nullable', 'string', 'max:255'],
            'hot_issues' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = User::query()->where('role', User::ROLE_COMMUNICATOR)->findOrFail($data['user_id']);

        $session = PressPrepSession::create([
            'user_id' => $user->id,
            'assigned_at' => now(),
            'assignment_note' => $data['assignment_note'] ?? 'Assigned by admin',
            'outing_type' => $data['outing_type'],
            'difficulty' => $data['difficulty'],
            'interview_mode' => $data['interview_mode'],
            'voice_preset' => $data['interview_mode'] === 'voice' ? GeminiTtsService::VOICE_GHANAIAN : null,
            'topics' => $data['topics'],
            'hot_issues' => $data['hot_issues'] ?? null,
            'question_count' => (int) $data['question_count'],
            'status' => 'setup',
            'current_question' => 0,
        ]);

        $mailNote = '';
        if ($user->hasRealEmail()) {
            try {
                Mail::to($user->email)->send(new PressPrepAssignedMail($user, $session));
                $mailNote = ' Email sent to '.$user->email.'.';
            } catch (\Throwable $e) {
                report($e);
                $mailNote = ' Email could not be sent (check mail config).';
            }
        }

        return $this->dashboardRedirect(
            'press-prep',
            'Press Prep assigned to '.$user->name.' ('.$user->party_id.').'.$mailNote
        );
    }

    public function pressPrepTranscript(PressPrepSession $session)
    {
        $session->load(['user:id,name,party_id,constituency,email', 'turns']);

        if (! $session->debrief) {
            return response()->json(['success' => false, 'message' => 'No debrief on this session.'], 404);
        }

        return response()->json([
            'success' => true,
            'session' => $this->transcriptPayload($session),
        ]);
    }

    public function pressPrepTranscriptTxt(PressPrepSession $session)
    {
        $session->load(['user', 'turns']);
        $body = $this->buildTranscriptText($session);
        $party = $session->user?->party_id ?: 'user';
        $filename = 'press-prep-'.$session->id.'-'.$party.'.txt';

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function pressPrepTranscriptPdf(PressPrepSession $session)
    {
        $session->load(['user', 'turns']);
        $html = view('admin.press-prep-transcript-pdf', [
            'session' => $session,
            'payload' => $this->transcriptPayload($session),
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $party = $session->user?->party_id ?: 'user';
        $filename = 'press-prep-'.$session->id.'-'.$party.'.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transcriptPayload(PressPrepSession $session): array
    {
        $d = $session->debrief ?? [];
        $overall = $d['overall'] ?? null;
        $pct = is_numeric($overall) ? ($overall <= 10 ? (int) round($overall * 10) : (int) round($overall)) : null;

        return [
            'id' => $session->id,
            'outing_type' => $session->outing_type,
            'difficulty' => $session->difficulty,
            'interview_mode' => $session->interview_mode,
            'status' => $session->status,
            'ended_early' => (bool) ($d['ended_early'] ?? false),
            'readiness_pct' => $pct,
            'scores' => $d['scores'] ?? [],
            'summary' => $d['summary'] ?? null,
            'user' => [
                'name' => $session->user?->name,
                'party_id' => $session->user?->party_id,
                'constituency' => $session->user?->constituency,
            ],
            'turns' => $session->turns->map(fn ($t) => [
                'turn_index' => $t->turn_index,
                'question' => $t->question,
                'user_answer' => $t->user_answer,
                'model_answer' => $t->model_answer,
                'coach_note' => $t->coach_note,
                'hint_text' => $t->hint_text,
            ])->values()->all(),
        ];
    }

    private function buildTranscriptText(PressPrepSession $session): string
    {
        $p = $this->transcriptPayload($session);
        $lines = [];
        $lines[] = 'NDC Communicators — Press Prep transcript';
        $lines[] = 'Session #'.$p['id'];
        $lines[] = 'Communicator: '.($p['user']['name'] ?? '-').' ('.($p['user']['party_id'] ?? '-').')';
        $lines[] = 'Outing: '.$p['outing_type'].' · '.$p['difficulty'].' · '.$p['interview_mode'];
        $lines[] = 'Status: '.$p['status'].($p['ended_early'] ? ' (ended early)' : '');
        if ($p['readiness_pct'] !== null) {
            $lines[] = 'Readiness: '.$p['readiness_pct'].'%';
        }
        if (! empty($p['summary'])) {
            $lines[] = '';
            $lines[] = 'Summary: '.$p['summary'];
        }
        $lines[] = '';
        $lines[] = str_repeat('=', 48);
        foreach ($p['turns'] as $i => $turn) {
            $n = $i + 1;
            $lines[] = '';
            $lines[] = "Q{$n}. ".($turn['question'] ?? '');
            $lines[] = 'Answer: '.($turn['user_answer'] ?: '(no answer)');
            if (! empty($turn['model_answer'])) {
                $lines[] = 'Model: '.$turn['model_answer'];
            }
            if (! empty($turn['coach_note'])) {
                $lines[] = 'Coach: '.$turn['coach_note'];
            }
            $lines[] = str_repeat('-', 32);
        }

        return implode("\n", $lines)."\n";
    }

    public function updateAiKeys(Request $request)
    {
        $data = $request->validate([
            'gemini_api_key' => ['nullable', 'string', 'max:500'],
            'gemini_tts_model' => ['nullable', 'string', 'max:120'],
        ]);

        if (! empty($data['gemini_api_key'])) {
            $key = trim($data['gemini_api_key']);
            $this->writeEnvValue('GEMINI_API_KEY', $key);
            putenv('GEMINI_API_KEY='.$key);
            $_ENV['GEMINI_API_KEY'] = $key;
            $_SERVER['GEMINI_API_KEY'] = $key;
            config(['services.gemini.api_key' => $key]);
        }
        if (! empty($data['gemini_tts_model'])) {
            $model = trim($data['gemini_tts_model']);
            $this->writeEnvValue('GEMINI_TTS_MODEL', $model);
            putenv('GEMINI_TTS_MODEL='.$model);
            $_ENV['GEMINI_TTS_MODEL'] = $model;
            $_SERVER['GEMINI_TTS_MODEL'] = $model;
            config(['services.gemini.tts_model' => $model]);
        }

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        return $this->dashboardRedirect(
            'settings',
            'Google Gemini TTS settings saved. Restart `php artisan serve --host=0.0.0.0 --port=8002` so the new key loads.'
        );
    }

    public function upload(Request $request, DocumentIngestService $ingest)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'mimes:pdf', 'max:51200'],
            'digest_now' => ['nullable'],
        ]);

        $file = $request->file('file');
        $filename = Str::uuid().'.pdf';
        $path = $file->storeAs('documents', $filename);

        $document = Document::create([
            'title' => $data['title'],
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'status' => 'pending',
        ]);

        if ($request->boolean('digest_now')) {
            set_time_limit(600);
            $ingest->process($document);
        }

        return $this->dashboardRedirect(
            'documents',
            'Document uploaded'.($request->boolean('digest_now') ? ' and digested.' : '.')
        );
    }

    public function digest(Document $document, DocumentIngestService $ingest)
    {
        set_time_limit(600);
        $ingest->process($document);

        return $this->dashboardRedirect('documents', "Digested: {$document->title}");
    }

    public function updateBriefing(Request $request, Briefing $briefing)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'summary' => ['required', 'string'],
            'published' => ['nullable'],
        ]);

        $briefing->update([
            'title' => $data['title'],
            'category' => $data['category'],
            'summary' => $data['summary'],
            'published_at' => $request->boolean('published') ? ($briefing->published_at ?? now()) : null,
        ]);

        return $this->dashboardRedirect('documents', 'Briefing updated.');
    }

    public function destroyDocument(Document $document)
    {
        Storage::disk('local')->delete($document->file_path);
        $document->delete();

        return $this->dashboardRedirect('documents', 'Document deleted.');
    }

    private function maskSecret(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (strlen($value) <= 10) {
            return str_repeat('•', strlen($value));
        }

        return substr($value, 0, 6).str_repeat('•', max(6, strlen($value) - 10)).substr($value, -4);
    }

    /**
     * @return array<string, string>
     */
    private function readEnvMap(): array
    {
        $path = base_path('.env');
        if (! is_file($path)) {
            return [];
        }
        $map = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if ($line === '' || str_starts_with(trim($line), '#') || ! str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $map[trim($k)] = trim($v);
        }

        return $map;
    }

    private function writeEnvValue(string $key, string $value): void
    {
        $path = base_path('.env');
        $raw = is_file($path) ? (string) file_get_contents($path) : '';
        $escaped = $value;
        if (preg_match('/\s|#|"|\'/', $value)) {
            $escaped = '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        }
        $line = $key.'='.$escaped;
        if (preg_match('/^'.preg_quote($key, '/').'=.*$/m', $raw)) {
            $raw = preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $line, $raw) ?? $raw;
        } else {
            $raw = rtrim($raw)."\n".$line."\n";
        }
        file_put_contents($path, $raw);
    }
}
