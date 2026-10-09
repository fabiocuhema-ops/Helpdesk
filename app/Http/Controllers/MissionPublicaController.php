<?php

namespace App\Http\Controllers;

use App\Models\AiSuggestion;
use App\Models\Demand;
use App\Models\DemandEvent;
use App\Models\LegacyTicket;
use App\Models\OutboxEvent;
use App\Models\PushSubscription;
use App\Models\ScoreLedger;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MissionPublicaController extends Controller
{
    private const STATUSES = [
        'registered' => 'Registrada',
        'assigned' => 'Atribuida',
        'in_progress' => 'Em execucao',
        'blocked' => 'Bloqueada',
        'pending_analysis' => 'Pendencia em analise',
        'awaiting_validation' => 'Aguardando validacao',
        'resolved' => 'Resolvida',
        'cancelled' => 'Cancelada',
    ];

    public function app(): View
    {
        return view('welcome');
    }

    public function bootstrap(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);

        return response()->json([
            'user' => $this->userPayload($user),
            'users' => User::orderBy('name')->get(['id', 'name', 'email', 'roles', 'sector_id']),
            'sectors' => DB::table('sectors')->orderBy('name')->get(),
            'locations' => DB::table('municipal_locations')->orderBy('name')->get(),
            'teams' => DB::table('teams')->orderBy('name')->get(),
            'taskTypes' => DB::table('task_types')->orderBy('category')->orderBy('name')->get(),
            'statuses' => self::STATUSES,
            'metrics' => $this->metrics($user),
            'ranking' => $this->ranking(),
            'integration' => $this->integrationSummary(),
            'push' => [
                'publicKey' => config('services.webpush.public_key'),
                'note' => 'Payload de push deve conter somente ID opaco da missao.',
            ],
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['user_id' => ['required', 'exists:users,id']]);
        $request->session()->put('demo_user_id', $data['user_id']);

        return $this->bootstrap($request);
    }

    public function demands(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        $query = Demand::query()->with(['requester:id,name,email', 'executor:id,name', 'team:id,name', 'sector:id,name', 'events']);

        if (! $user->hasRole('admin') && ! $user->hasRole('atendente')) {
            $query->where(function ($scope) use ($user): void {
                $scope->where('requester_id', $user->id)
                    ->orWhere('executor_id', $user->id)
                    ->orWhere('sector_id', $user->sector_id);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json([
            'data' => $query->latest()->get()->map(fn (Demand $demand): array => $this->demandPayload($demand)),
        ]);
    }

    public function storeDemand(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        abort_unless($user->hasRole('admin') || $user->hasRole('atendente'), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:4000'],
            'sector_id' => ['required', 'exists:sectors,id'],
            'location_id' => ['nullable', 'exists:municipal_locations,id'],
            'task_type_id' => ['nullable', 'exists:task_types,id'],
            'requester_id' => ['required', 'exists:users,id'],
            'priority' => ['required', 'in:baixa,media,alta,critica'],
            'complexity' => ['required', 'in:baixa,media,alta'],
        ]);

        $demand = DB::transaction(function () use ($data, $user): Demand {
            $demand = Demand::create([...$data, 'uuid' => (string) Str::uuid(), 'attendant_id' => $user->id]);
            $this->event($demand, $user, 'created', null, 'registered', 'Demanda registrada pelo atendimento.', true);
            $this->outbox($demand, 'created');

            return $demand;
        });

        return response()->json($this->demandPayload($demand->load(['requester', 'executor', 'team', 'sector', 'events'])), 201);
    }

    public function transition(Request $request, Demand $demand): JsonResponse
    {
        $user = $this->currentUser($request);
        $data = $request->validate([
            'action' => ['required', 'string'],
            'description' => ['nullable', 'string', 'max:3000'],
            'executor_id' => ['nullable', 'exists:users,id'],
            'team_id' => ['nullable', 'exists:teams,id'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'lock_version' => ['required', 'integer'],
        ]);

        $updated = DB::transaction(function () use ($demand, $data, $user): Demand {
            $locked = Demand::query()->whereKey($demand->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->lock_version !== (int) $data['lock_version'], 409, 'A demanda foi alterada por outro usuario.');
            $from = $locked->status;
            $to = $this->authorizeTransition($user, $locked, $data);
            $eventType = $data['action'];

            if ($eventType === 'assign') {
                $locked->executor_id = $data['executor_id'] ?? null;
                $locked->team_id = $data['team_id'] ?? null;
                $locked->manager_id = $user->id;
            }

            if ($eventType === 'start') {
                $locked->started_at ??= now();
            }

            if ($eventType === 'complete') {
                abort_unless(filled($data['description'] ?? null), 422, 'Conclusao exige descricao da entrega.');
                $locked->executor_delivered_at = now();
            }

            if ($eventType === 'confirm') {
                $locked->resolved_at = now();
            }

            $locked->status = $to;
            $locked->lock_version++;
            $locked->save();

            $event = $this->event(
                $locked,
                $user,
                $eventType,
                $from,
                $to,
                $data['description'] ?? $this->defaultDescription($eventType),
                in_array($eventType, ['created', 'complete', 'confirm', 'reopen'], true),
                ['rating' => $data['rating'] ?? null]
            );

            if ($eventType === 'confirm') {
                $this->score($locked, $event, (int) ($data['rating'] ?? 3));
            }

            $this->outbox($locked, $eventType, $event->event_uuid);

            return $locked;
        });

        return response()->json($this->demandPayload($updated->load(['requester', 'executor', 'team', 'sector', 'events'])));
    }

    public function legacyCreate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string'],
            'requester_name' => ['required', 'string', 'max:120'],
            'requester_email' => ['nullable', 'email'],
            'sector_hint' => ['nullable', 'string', 'max:120'],
        ]);

        $ticket = LegacyTicket::create([...$data, 'external_id' => (string) Str::uuid(), 'received_events' => []]);

        return response()->json($ticket, 201);
    }

    public function importLegacy(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        abort_unless($user->hasRole('admin') || $user->hasRole('atendente'), 403);
        $ticket = LegacyTicket::where('status', 'novo')->oldest()->firstOrFail();
        $sector = DB::table('sectors')->where('name', 'like', '%TI%')->first();
        $requester = User::where('email', $ticket->requester_email)->first() ?? User::whereJsonContains('roles', 'demandante')->firstOrFail();

        $demand = DB::transaction(function () use ($ticket, $sector, $requester, $user): Demand {
            $demand = Demand::firstOrCreate(
                ['origin_system' => 'legacy_demo', 'origin_reference' => $ticket->external_id],
                [
                    'uuid' => (string) Str::uuid(),
                    'title' => $ticket->title,
                    'description' => $ticket->description,
                    'sector_id' => $sector->id,
                    'requester_id' => $requester->id,
                    'attendant_id' => $user->id,
                    'priority' => 'alta',
                    'complexity' => 'media',
                ]
            );
            $this->event($demand, $user, 'imported', null, $demand->status, 'Chamado importado do legado simulado.', true, ['legacy' => $ticket->external_id]);
            $ticket->update(['status' => 'importado']);

            return $demand;
        });

        return response()->json($this->demandPayload($demand->load(['requester', 'executor', 'team', 'sector', 'events'])));
    }

    public function legacyToggleFailure(LegacyTicket $ticket): JsonResponse
    {
        $ticket->update(['fail_next_sync' => ! $ticket->fail_next_sync]);

        return response()->json($ticket);
    }

    public function integration(): JsonResponse
    {
        return response()->json([
            'tickets' => LegacyTicket::latest()->get(),
            'outbox' => OutboxEvent::latest()->limit(30)->get(),
        ]);
    }

    public function aiSuggest(Request $request): JsonResponse
    {
        $data = $request->validate(['description' => ['required', 'string', 'max:4000'], 'demand_id' => ['nullable', 'exists:demands,id']]);
        $model = env('OLLAMA_MODEL', 'llama3.1');
        $baseUrl = rtrim((string) env('OLLAMA_URL', 'http://127.0.0.1:11434'), '/');
        $suggestion = AiSuggestion::create([
            'demand_id' => $data['demand_id'] ?? null,
            'model' => $model,
            'input_digest' => ['chars' => strlen($data['description']), 'preview' => Str::limit($data['description'], 120)],
        ]);

        try {
            $response = Http::timeout(8)->post($baseUrl.'/api/generate', [
                'model' => $model,
                'stream' => false,
                'format' => 'json',
                'prompt' => 'Responda em JSON com categoria, encaminhamento, resumo e informacoes_faltantes para esta demanda municipal: '.$data['description'],
            ]);

            throw_if(! $response->successful(), new \RuntimeException('Ollama indisponivel.'));
            $payload = json_decode((string) $response->json('response'), true, 512, JSON_THROW_ON_ERROR);
            $suggestion->update(['status' => 'generated', 'suggestion' => $payload]);
        } catch (\Throwable $exception) {
            $fallback = [
                'modo' => 'demonstrativo',
                'categoria' => str_contains(strtolower($data['description']), 'rede') ? 'Infraestrutura de TI' : 'Triagem geral',
                'encaminhamento' => 'Revisar manualmente antes de aceitar',
                'resumo' => Str::limit($data['description'], 180),
                'informacoes_faltantes' => ['local exato', 'impacto no atendimento'],
            ];
            $suggestion->update(['status' => 'demo_fallback', 'suggestion' => $fallback, 'error' => $exception->getMessage()]);
        }

        return response()->json($suggestion);
    }

    public function pushSubscribe(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        $data = $request->validate(['endpoint' => ['required', 'url'], 'keys' => ['required', 'array']]);
        PushSubscription::updateOrCreate(['endpoint' => $data['endpoint']], ['user_id' => $user->id, 'keys' => $data['keys'], 'expired_at' => null]);

        return response()->json(['ok' => true]);
    }

    private function currentUser(Request $request): User
    {
        $id = $request->session()->get('demo_user_id');

        return User::find($id) ?? User::whereJsonContains('roles', 'admin')->firstOrFail();
    }

    private function userPayload(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'roles' => $user->roles ?? [], 'sector_id' => $user->sector_id];
    }

    private function demandPayload(Demand $demand): array
    {
        return [
            'id' => $demand->id,
            'uuid' => $demand->uuid,
            'title' => $demand->title,
            'description' => $demand->description,
            'status' => $demand->status,
            'status_label' => self::STATUSES[$demand->status] ?? $demand->status,
            'priority' => $demand->priority,
            'complexity' => $demand->complexity,
            'lock_version' => $demand->lock_version,
            'sector' => $demand->sector?->name,
            'requester' => $demand->requester?->name,
            'executor' => $demand->executor?->name,
            'team' => $demand->team?->name,
            'origin' => $demand->origin_system,
            'events' => $demand->events->sortByDesc('occurred_at')->values()->map(fn (DemandEvent $event): array => [
                'type' => $event->type,
                'from' => $event->from_status,
                'to' => $event->to_status,
                'description' => $event->description,
                'visible_to_requester' => $event->visible_to_requester,
                'occurred_at' => $event->occurred_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i'),
            ]),
        ];
    }

    private function authorizeTransition(User $user, Demand $demand, array $data): string
    {
        $action = $data['action'];
        $roleOk = match ($action) {
            'assign', 'unblock', 'cancel' => $user->hasRole('gestor') || $user->hasRole('admin'),
            'start', 'block', 'complete' => $user->hasRole('executor') && ($demand->executor_id === $user->id || $demand->team_id !== null),
            'confirm', 'reopen' => $user->hasRole('demandante') && $demand->requester_id === $user->id,
            default => false,
        };
        abort_unless($roleOk, 403);

        return match ($action) {
            'assign' => $demand->status === 'registered' || $demand->status === 'pending_analysis' ? 'assigned' : abort(422),
            'start' => $demand->status === 'assigned' ? 'in_progress' : abort(422),
            'block' => filled($data['description'] ?? null) && $demand->status === 'in_progress' ? 'blocked' : abort(422),
            'unblock' => filled($data['description'] ?? null) && $demand->status === 'blocked' ? 'in_progress' : abort(422),
            'complete' => $demand->status === 'in_progress' ? 'awaiting_validation' : abort(422),
            'confirm' => $demand->status === 'awaiting_validation' ? 'resolved' : abort(422),
            'reopen' => filled($data['description'] ?? null) && $demand->status === 'awaiting_validation' ? 'pending_analysis' : abort(422),
            'cancel' => filled($data['description'] ?? null) ? 'cancelled' : abort(422),
            default => abort(422),
        };
    }

    private function event(Demand $demand, ?User $user, string $type, ?string $from, ?string $to, ?string $description, bool $visible, array $payload = []): DemandEvent
    {
        return DemandEvent::create([
            'event_uuid' => (string) Str::uuid(),
            'demand_id' => $demand->id,
            'author_id' => $user?->id,
            'type' => $type,
            'from_status' => $from,
            'to_status' => $to,
            'description' => $description,
            'visible_to_requester' => $visible,
            'payload' => $payload,
            'occurred_at' => Carbon::now('UTC'),
        ]);
    }

    private function outbox(Demand $demand, string $type, ?string $sourceEvent = null): void
    {
        OutboxEvent::firstOrCreate(
            ['event_uuid' => $sourceEvent ?? (string) Str::uuid()],
            ['demand_id' => $demand->id, 'payload' => ['type' => $type, 'demand_uuid' => $demand->uuid, 'status' => $demand->status, 'origin_reference' => $demand->origin_reference]]
        );
    }

    private function score(Demand $demand, DemandEvent $event, int $rating): void
    {
        $base = ['baixa' => 10, 'media' => 20, 'alta' => 35][$demand->complexity] ?? 20;
        $quality = max(0, $rating - 3) * 5;
        $points = $base + $quality;

        ScoreLedger::firstOrCreate(
            ['demand_id' => $demand->id, 'source_event_uuid' => $event->event_uuid, 'user_id' => $demand->executor_id, 'team_id' => null],
            ['rule_version' => 'demo-v1', 'points' => $points, 'justification' => "Base {$base} por complexidade + bonus {$quality} por avaliacao {$rating}."]
        );
    }

    private function metrics(User $user): array
    {
        $query = Demand::query();
        if (! $user->hasRole('admin')) {
            $query->where('sector_id', $user->sector_id);
        }

        $demands = $query->get();

        return [
            'received' => $demands->count(),
            'in_progress' => $demands->where('status', 'in_progress')->count(),
            'blocked' => $demands->where('status', 'blocked')->count(),
            'awaiting_validation' => $demands->where('status', 'awaiting_validation')->count(),
            'resolved' => $demands->where('status', 'resolved')->count(),
            'avg_first_start_hours' => round($demands->filter->started_at->avg(fn (Demand $demand): float => $demand->created_at->diffInMinutes($demand->started_at) / 60) ?? 0, 1),
        ];
    }

    private function ranking(): array
    {
        return ScoreLedger::query()
            ->selectRaw('users.name as name, sum(points) as points')
            ->join('users', 'users.id', '=', 'score_ledgers.user_id')
            ->groupBy('users.name')
            ->orderByDesc('points')
            ->get()
            ->toArray();
    }

    private function integrationSummary(): array
    {
        return [
            'pending' => OutboxEvent::where('status', 'pending')->count(),
            'sent' => OutboxEvent::where('status', 'sent')->count(),
            'failed' => OutboxEvent::where('status', 'failed')->count(),
        ];
    }

    private function defaultDescription(string $eventType): string
    {
        return match ($eventType) {
            'assign' => 'Missao atribuida.',
            'start' => 'Atendimento iniciado.',
            'unblock' => 'Bloqueio tratado e atendimento retomado.',
            'confirm' => 'Demandante confirmou a resolucao.',
            default => 'Evento registrado.',
        };
    }
}
