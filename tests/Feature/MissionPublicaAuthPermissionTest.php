<?php

namespace Tests\Feature;

use App\Models\Demand;
use App\Models\Evidence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MissionPublicaAuthPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_logout_and_protected_routes(): void
    {
        $this->seed();

        $this->get('/')->assertRedirect('/login');

        $this->post('/login', [
            'email' => 'admin@missao.local',
            'password' => 'errada',
        ])->assertSessionHasErrors('email');

        $this->post('/login', [
            'email' => 'admin@missao.local',
            'password' => 'demo1234',
        ])->assertRedirect('/');

        $this->assertAuthenticated();

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_login_attempts_are_limited(): void
    {
        User::factory()->create([
            'email' => 'limite@missao.local',
            'password' => Hash::make('correta'),
            'roles' => ['admin'],
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['email' => 'limite@missao.local', 'password' => 'incorreta']);
        }

        $this->post('/login', ['email' => 'limite@missao.local', 'password' => 'incorreta'])->assertStatus(429);
    }

    public function test_demandant_cannot_view_other_requesters_demand(): void
    {
        $this->seed();
        $requester = User::where('email', 'demandante@missao.local')->firstOrFail();
        $other = User::factory()->create([
            'roles' => ['demandante'],
            'sector_id' => $requester->sector_id,
        ]);
        $demand = Demand::firstOrFail();
        $demand->update(['requester_id' => $other->id]);

        $this->actingAs($requester)->getJson('/api/demands')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($requester)->postJson("/api/demands/{$demand->id}/transition", [
            'action' => 'confirm',
            'lock_version' => $demand->lock_version,
        ])->assertForbidden();
    }

    public function test_executor_cannot_change_unassigned_demand(): void
    {
        $this->seed();
        $executor = User::where('email', 'executor@missao.local')->firstOrFail();
        $demand = Demand::firstOrFail();
        $demand->update(['executor_id' => null, 'team_id' => null]);

        $this->actingAs($executor)->postJson("/api/demands/{$demand->id}/transition", [
            'action' => 'start',
            'lock_version' => $demand->lock_version,
        ])->assertForbidden();
    }

    public function test_private_evidence_requires_authorized_access(): void
    {
        Storage::fake('local');
        $this->seed();
        $executor = User::where('email', 'executor@missao.local')->firstOrFail();
        $requester = User::where('email', 'demandante@missao.local')->firstOrFail();
        $demand = Demand::firstOrFail();

        $this->actingAs($executor)->post("/api/demands/{$demand->id}/evidences", [
            'evidence' => UploadedFile::fake()->image('rede.jpg'),
        ])->assertCreated();

        $evidence = Evidence::firstOrFail();
        $this->actingAs($requester)->get("/api/evidences/{$evidence->id}")->assertForbidden();

        $evidence->update(['visible_to_requester' => true]);
        $this->actingAs($requester)->get("/api/evidences/{$evidence->id}")->assertOk();
    }
}
