<?php

namespace Tests\Feature;

use App\Models\Demand;
use App\Models\OutboxEvent;
use App\Models\ScoreLedger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MissionPublicaFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_journey_scores_once_and_uses_outbox(): void
    {
        $this->seed();
        $demand = Demand::where('status', 'assigned')->firstOrFail();
        $executor = User::where('email', 'executor@missao.local')->firstOrFail();
        $manager = User::where('email', 'gestor@missao.local')->firstOrFail();
        $requester = User::where('email', 'demandante@missao.local')->firstOrFail();

        $this->withSession(['demo_user_id' => $executor->id])->postJson("/api/demands/{$demand->id}/transition", [
            'action' => 'start',
            'lock_version' => $demand->lock_version,
        ])->assertOk()->assertJsonPath('status', 'in_progress');

        $demand->refresh();
        $this->withSession(['demo_user_id' => $executor->id])->postJson("/api/demands/{$demand->id}/transition", [
            'action' => 'block',
            'description' => 'Sem conector RJ45 no almoxarifado.',
            'lock_version' => $demand->lock_version,
        ])->assertOk()->assertJsonPath('status', 'blocked');

        $demand->refresh();
        $this->withSession(['demo_user_id' => $manager->id])->postJson("/api/demands/{$demand->id}/transition", [
            'action' => 'unblock',
            'description' => 'Material entregue ao tecnico.',
            'lock_version' => $demand->lock_version,
        ])->assertOk()->assertJsonPath('status', 'in_progress');

        $demand->refresh();
        $this->withSession(['demo_user_id' => $executor->id])->postJson("/api/demands/{$demand->id}/transition", [
            'action' => 'complete',
            'description' => 'Ponto refeito, identificado e testado.',
            'lock_version' => $demand->lock_version,
        ])->assertOk()->assertJsonPath('status', 'awaiting_validation');

        $demand->refresh();
        $this->withSession(['demo_user_id' => $requester->id])->postJson("/api/demands/{$demand->id}/transition", [
            'action' => 'confirm',
            'description' => 'Funcionando corretamente.',
            'rating' => 5,
            'lock_version' => $demand->lock_version,
        ])->assertOk()->assertJsonPath('status', 'resolved');

        $this->assertSame(1, ScoreLedger::where('demand_id', $demand->id)->count());
        $this->assertGreaterThanOrEqual(5, OutboxEvent::where('demand_id', $demand->id)->count());
    }

    public function test_requester_cannot_assign_and_stale_lock_is_rejected(): void
    {
        $this->seed();
        $demand = Demand::where('status', 'assigned')->firstOrFail();
        $requester = User::where('email', 'demandante@missao.local')->firstOrFail();
        $executor = User::where('email', 'executor@missao.local')->firstOrFail();

        $this->withSession(['demo_user_id' => $requester->id])->postJson("/api/demands/{$demand->id}/transition", [
            'action' => 'assign',
            'lock_version' => $demand->lock_version,
        ])->assertForbidden();

        $this->withSession(['demo_user_id' => $executor->id])->postJson("/api/demands/{$demand->id}/transition", [
            'action' => 'start',
            'lock_version' => 999,
        ])->assertStatus(409);
    }
}
