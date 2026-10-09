<?php

namespace App\Http\Controllers;

use App\Models\AiSuggestion;
use App\Models\Demand;
use App\Models\DemandEvent;
use App\Models\Evidence;
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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    private const EVENTS = [
        'created' => 'Demanda registrada',
        'imported' => 'Importada do legado',
        'assign' => 'Atribuição registrada',
        'start' => 'Atendimento iniciado',
        'block' => 'Impedimento informado',
        'unblock' => 'Providência registrada',
        'complete' => 'Entrega informada pelo executor',
        'confirm' => 'Resolução confirmada',
        'reopen' => 'Pendência informada',
        'cancel' => 'Demanda cancelada',
        'evidence' => 'Evidência anexada',
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
            'users' => $this->visibleUsers($user),
            'sectors' => DB::table('sectors')->orderBy('name')->get(),
            'locations' => DB::table('municipal_locations')->orderBy('name')->get(),
            'teams' => DB::table('teams')->orderBy('name')->get(),
            'taskTypes' => DB::table('task_types')->orderBy('category')->orderBy('name')->get(),
            'statuses' => self::STATUSES,
            'metrics' => $this->metrics($user),
            'ranking' => $this->ranking(),
            'integration' => $this->integrationSummary(),
            'navigation' => $this->navigation($user),
            'push' => [
                'publicKey' => config('services.webpush.public_key'),
                'note' => 'Payload de push deve conter somente ID opaco da missao.',
            ],
        ]);
    }

    public function demands(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        $query = Demand::query()->with(['requester:id,name,email', 'executor:id,name', 'team:id,name', 'sector:id,name', 'events', 'evidences']);
        $this->scopeDemandQuery($query, $user);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json([
            'data' => $query->latest()->get()->map(fn (Demand $demand): array => $this->demandPayload($demand, $user)),
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
        if (! $user->hasRole('admin')) {
            $data['sector_id'] = $user->sector_id;
        }

        $demand = DB::transaction(function () use ($data, $user): Demand {
            $demand = Demand::create([...$data, 'uuid' => (string) Str::uuid(), 'attendant_id' => $user->id]);
            $this->event($demand, $user, 'created', null, 'registered', 'Demanda registrada pelo atendimento.', true);
            $this->outbox($demand, 'created');

            return $demand;
        });

        return response()->json($this->demandPayload($demand->load(['requester', 'executor', 'team', 'sector', 'events', 'evidences']), $user), 201);
    }

    public function transition(Request $request, Demand $demand): JsonResponse
    {
        $user = $this->currentUser($request);
        abort_unless($this->canViewDemand($user, $demand), 403);
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

        return response()->json($this->demandPayload($updated->load(['requester', 'executor', 'team', 'sector', 'events', 'evidences']), $user));
    }

    public function legacyCreate(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        abort_unless($user->hasRole('admin') || $user->hasRole('atendente'), 403);

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

        return response()->json($this->demandPayload($demand->load(['requester', 'executor', 'team', 'sector', 'events', 'evidences']), $user));
    }

    public function legacyToggleFailure(Request $request, LegacyTicket $ticket): JsonResponse
    {
        abort_unless($this->currentUser($request)->hasRole('admin'), 403);
        $ticket->update(['fail_next_sync' => ! $ticket->fail_next_sync]);

        return response()->json($ticket);
    }

    public function integration(Request $request): JsonResponse
    {
        abort_unless($this->currentUser($request)->hasRole('admin'), 403);

        return response()->json([
            'tickets' => LegacyTicket::latest()->get(),
            'outbox' => OutboxEvent::latest()->limit(30)->get(),
        ]);
    }

    public function aiSuggest(Request $request): JsonResponse
    {
        $data = $request->validate(['description' => ['required', 'string', 'max:4000'], 'demand_id' => ['nullable', 'exists:demands,id']]);
        $user = $this->currentUser($request);
        abort_unless($user->hasRole('admin') || $user->hasRole('atendente') || $user->hasRole('gestor'), 403);
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

    public function storeEvidence(Request $request, Demand $demand): JsonResponse
    {
        $user = $this->currentUser($request);
        abort_unless($this->canViewDemand($user, $demand), 403);
        abort_unless($user->hasRole('admin') || $user->hasRole('gestor') || ($user->hasRole('executor') && $this->isExecutorAssigned($user, $demand)), 403);

        $data = $request->validate([
            'evidence' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf,txt'],
            'visible_to_requester' => ['nullable', 'boolean'],
        ]);

        $file = $data['evidence'];
        $path = $file->store("evidences/{$demand->uuid}", 'local');

        $evidence = Evidence::create([
            'demand_id' => $demand->id,
            'uploaded_by' => $user->id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize() ?: 0,
            'visible_to_requester' => $request->boolean('visible_to_requester'),
        ]);

        $this->event($demand, $user, 'evidence', $demand->status, $demand->status, 'Evidência privada anexada à demanda.', false, ['evidence_id' => $evidence->id]);

        return response()->json($this->evidencePayload($evidence, $user), 201);
    }

    public function downloadEvidence(Request $request, Evidence $evidence): StreamedResponse
    {
        $user = $this->currentUser($request);
        $demand = $evidence->demand()->firstOrFail();
        abort_unless($this->canViewDemand($user, $demand), 403);
        abort_if($user->hasRole('demandante') && (! $evidence->visible_to_requester || $demand->requester_id !== $user->id), 403);

        return Storage::disk('local')->download($evidence->path, $evidence->original_name);
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
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function userPayload(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'roles' => $user->roles ?? [], 'sector_id' => $user->sector_id];
    }

    private function demandPayload(Demand $demand, User $user): array
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
            'actions' => $this->availableActions($user, $demand),
            'evidences' => $demand->evidences->filter(fn (Evidence $evidence): bool => ! $user->hasRole('demandante') || $evidence->visible_to_requester)->values()->map(fn (Evidence $evidence): array => $this->evidencePayload($evidence, $user)),
            'events' => $demand->events
                ->filter(fn (DemandEvent $event): bool => ! $user->hasRole('demandante') || $event->visible_to_requester)
                ->sortByDesc('occurred_at')->values()->map(fn (DemandEvent $event): array => [
                    'type' => $event->type,
                    'label' => self::EVENTS[$event->type] ?? $event->type,
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
            'assign', 'unblock', 'cancel' => ($user->hasRole('gestor') || $user->hasRole('admin')) && $this->canViewDemand($user, $demand),
            'start', 'block', 'complete' => $user->hasRole('executor') && $this->isExecutorAssigned($user, $demand),
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
        $this->scopeDemandQuery($query, $user);

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

    private function visibleUsers(User $user)
    {
        if ($user->hasRole('demandante') || $user->hasRole('executor')) {
            return collect();
        }

        $query = User::query()->orderBy('name');
        if (! $user->hasRole('admin')) {
            $query->where('sector_id', $user->sector_id);
        }

        return $query->get(['id', 'name', 'email', 'roles', 'sector_id']);
    }

    private function scopeDemandQuery($query, User $user): void
    {
        if ($user->hasRole('admin')) {
            return;
        }

        if ($user->hasRole('demandante')) {
            $query->where('requester_id', $user->id);

            return;
        }

        if ($user->hasRole('executor') && ! $user->hasRole('gestor') && ! $user->hasRole('atendente')) {
            $teamIds = $user->teams()->pluck('teams.id');
            $query->where(function ($scope) use ($user, $teamIds): void {
                $scope->where('executor_id', $user->id)->orWhereIn('team_id', $teamIds);
            });

            return;
        }

        $query->where('sector_id', $user->sector_id);
    }

    private function canViewDemand(User $user, Demand $demand): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('demandante')) {
            return $demand->requester_id === $user->id;
        }

        if ($user->hasRole('executor') && $this->isExecutorAssigned($user, $demand)) {
            return true;
        }

        return ($user->hasRole('atendente') || $user->hasRole('gestor')) && $demand->sector_id === $user->sector_id;
    }

    private function isExecutorAssigned(User $user, Demand $demand): bool
    {
        if ($demand->executor_id === $user->id) {
            return true;
        }

        return $demand->team_id !== null && $user->teams()->whereKey($demand->team_id)->exists();
    }

    private function availableActions(User $user, Demand $demand): array
    {
        $actions = [];
        if (($user->hasRole('admin') || $user->hasRole('gestor')) && in_array($demand->status, ['registered', 'pending_analysis'], true) && $this->canViewDemand($user, $demand)) {
            $actions[] = 'assign';
        }
        if ($user->hasRole('executor') && $this->isExecutorAssigned($user, $demand)) {
            if ($demand->status === 'assigned') {
                $actions[] = 'start';
            }
            if ($demand->status === 'in_progress') {
                array_push($actions, 'block', 'complete', 'evidence');
            }
        }
        if (($user->hasRole('admin') || $user->hasRole('gestor')) && $demand->status === 'blocked' && $this->canViewDemand($user, $demand)) {
            $actions[] = 'unblock';
        }
        if ($user->hasRole('demandante') && $demand->requester_id === $user->id && $demand->status === 'awaiting_validation') {
            array_push($actions, 'confirm', 'reopen');
        }

        return $actions;
    }

    private function evidencePayload(Evidence $evidence, User $user): array
    {
        return [
            'id' => $evidence->id,
            'name' => $evidence->original_name,
            'mime_type' => $evidence->mime_type,
            'size' => $evidence->size,
            'visible_to_requester' => $evidence->visible_to_requester,
            'uploaded_at' => $evidence->created_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i'),
            'download_url' => url("/api/evidences/{$evidence->id}"),
        ];
    }

    private function navigation(User $user): array
    {
        $items = [
            ['href' => '/visao-geral', 'label' => 'Visão geral', 'roles' => ['admin', 'gestor', 'atendente', 'executor', 'demandante']],
            ['href' => '/demandas', 'label' => 'Demandas', 'roles' => ['admin', 'gestor', 'atendente']],
            ['href' => '/demandas/nova', 'label' => 'Registrar demanda', 'roles' => ['admin', 'atendente']],
            ['href' => '/minhas-missoes', 'label' => 'Minhas missões', 'roles' => ['executor']],
            ['href' => '/minhas-solicitacoes', 'label' => 'Minhas solicitações', 'roles' => ['demandante']],
            ['href' => '/equipes', 'label' => 'Equipes', 'roles' => ['admin', 'gestor']],
            ['href' => '/indicadores', 'label' => 'Indicadores', 'roles' => ['admin', 'gestor']],
            ['href' => '/reconhecimento', 'label' => 'Reconhecimento', 'roles' => ['admin', 'gestor', 'executor']],
            ['href' => '/integracoes', 'label' => 'Integrações', 'roles' => ['admin']],
            ['href' => '/administracao', 'label' => 'Administração', 'roles' => ['admin']],
        ];

        return array_values(array_filter($items, fn (array $item): bool => collect($item['roles'])->contains(fn (string $role): bool => $user->hasRole($role))));
    }
}
