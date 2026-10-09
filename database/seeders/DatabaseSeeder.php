<?php

namespace Database\Seeders;

use App\Models\Demand;
use App\Models\DemandEvent;
use App\Models\LegacyTicket;
use App\Models\OutboxEvent;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $ti = DB::table('sectors')->updateOrInsert(['name' => 'Tecnologia da Informacao'], ['updated_at' => now(), 'created_at' => now()]);
        $manutencao = DB::table('sectors')->updateOrInsert(['name' => 'Manutencao Predial'], ['updated_at' => now(), 'created_at' => now()]);
        $tiId = DB::table('sectors')->where('name', 'Tecnologia da Informacao')->value('id');
        $manutencaoId = DB::table('sectors')->where('name', 'Manutencao Predial')->value('id');

        DB::table('municipal_locations')->updateOrInsert(['name' => 'Secretaria de Saude'], ['sector_id' => $tiId, 'address' => 'Rua Central, 100', 'updated_at' => now(), 'created_at' => now()]);
        DB::table('municipal_locations')->updateOrInsert(['name' => 'Escola Municipal Aurora'], ['sector_id' => $manutencaoId, 'address' => 'Av. das Flores, 55', 'updated_at' => now(), 'created_at' => now()]);

        DB::table('teams')->updateOrInsert(['name' => 'Equipe de Campo TI'], ['sector_id' => $tiId, 'updated_at' => now(), 'created_at' => now()]);
        DB::table('teams')->updateOrInsert(['name' => 'Equipe Predial Norte'], ['sector_id' => $manutencaoId, 'updated_at' => now(), 'created_at' => now()]);
        $teamTi = DB::table('teams')->where('name', 'Equipe de Campo TI')->value('id');
        $teamPredial = DB::table('teams')->where('name', 'Equipe Predial Norte')->value('id');

        DB::table('task_types')->updateOrInsert(['name' => 'Ponto de rede sem funcionamento'], ['sector_id' => $tiId, 'category' => 'Infraestrutura de TI', 'updated_at' => now(), 'created_at' => now()]);
        DB::table('task_types')->updateOrInsert(['name' => 'Reparo eletrico predial'], ['sector_id' => $manutencaoId, 'category' => 'Manutencao predial', 'updated_at' => now(), 'created_at' => now()]);

        DB::table('gamification_rules')->updateOrInsert(['version' => 'demo-v1'], [
            'rules' => json_encode(['baixa' => 10, 'media' => 20, 'alta' => 35, 'bonus_avaliacao_4_5' => '5 pontos por estrela acima de 3', 'colaboracao' => 'ajuste manual auditado']),
            'active' => true,
            'updated_at' => now(),
            'created_at' => now(),
        ]);

        $users = [
            ['Administrador Demo', 'admin@missao.local', ['admin'], $tiId],
            ['Ana Atendente', 'atendente@missao.local', ['atendente'], $tiId],
            ['Bruno Gestor', 'gestor@missao.local', ['gestor'], $tiId],
            ['Carlos Tecnico', 'executor@missao.local', ['executor'], $tiId],
            ['Daniela Demandante', 'demandante@missao.local', ['demandante'], $tiId],
            ['Eva Predial', 'predial@missao.local', ['gestor', 'executor'], $manutencaoId],
        ];

        foreach ($users as [$name, $email, $roles, $sectorId]) {
            User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => Hash::make('demo1234'),
                'roles' => $roles,
                'sector_id' => $sectorId,
                'demo_user' => true,
            ]);
        }

        $executor = User::where('email', 'executor@missao.local')->firstOrFail();
        DB::table('team_user')->updateOrInsert(['team_id' => $teamTi, 'user_id' => $executor->id]);
        DB::table('team_user')->updateOrInsert(['team_id' => $teamPredial, 'user_id' => User::where('email', 'predial@missao.local')->value('id')]);

        $legacy = LegacyTicket::firstOrCreate(['requester_email' => 'demandante@missao.local', 'title' => 'Ponto de rede sem funcionamento'], [
            'external_id' => (string) Str::uuid(),
            'description' => 'Servidora ligou informando que o ponto de rede da sala de vacina nao conecta.',
            'requester_name' => 'Daniela Demandante',
            'sector_hint' => 'TI',
            'status' => 'novo',
            'received_events' => [],
        ]);

        $demandante = User::where('email', 'demandante@missao.local')->firstOrFail();
        $atendente = User::where('email', 'atendente@missao.local')->firstOrFail();
        $gestor = User::where('email', 'gestor@missao.local')->firstOrFail();
        $taskTypeTi = DB::table('task_types')->where('name', 'Ponto de rede sem funcionamento')->value('id');
        $locationTi = DB::table('municipal_locations')->where('name', 'Secretaria de Saude')->value('id');

        $demand = Demand::firstOrCreate(['origin_system' => 'legacy_demo', 'origin_reference' => $legacy->external_id], [
            'uuid' => (string) Str::uuid(),
            'title' => 'Ponto de rede sem funcionamento',
            'description' => $legacy->description,
            'sector_id' => $tiId,
            'location_id' => $locationTi,
            'task_type_id' => $taskTypeTi,
            'requester_id' => $demandante->id,
            'attendant_id' => $atendente->id,
            'manager_id' => $gestor->id,
            'executor_id' => $executor->id,
            'team_id' => $teamTi,
            'status' => 'assigned',
            'priority' => 'alta',
            'complexity' => 'media',
        ]);

        if ($demand->events()->count() === 0) {
            DemandEvent::create([
                'event_uuid' => (string) Str::uuid(),
                'demand_id' => $demand->id,
                'author_id' => $atendente->id,
                'type' => 'imported',
                'to_status' => 'registered',
                'description' => 'Chamado importado do legado simulado.',
                'visible_to_requester' => true,
                'payload' => ['legacy' => $legacy->external_id],
                'occurred_at' => now(),
            ]);
            DemandEvent::create([
                'event_uuid' => (string) Str::uuid(),
                'demand_id' => $demand->id,
                'author_id' => $gestor->id,
                'type' => 'assign',
                'from_status' => 'registered',
                'to_status' => 'assigned',
                'description' => 'Atribuida para Equipe de Campo TI.',
                'payload' => [],
                'occurred_at' => now(),
            ]);
        }

        OutboxEvent::firstOrCreate(['event_uuid' => '00000000-0000-4000-8000-000000000001'], [
            'demand_id' => $demand->id,
            'payload' => ['type' => 'seed_demo', 'demand_uuid' => $demand->uuid, 'status' => $demand->status, 'origin_reference' => $legacy->external_id],
        ]);
    }
}
