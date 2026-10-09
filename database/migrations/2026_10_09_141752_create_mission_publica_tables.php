<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sectors', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->json('roles')->nullable()->after('password');
            $table->foreignId('sector_id')->nullable()->after('roles')->constrained()->nullOnDelete();
            $table->boolean('demo_user')->default(false)->after('sector_id');
        });

        Schema::create('municipal_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sector_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->timestamps();
        });

        Schema::create('teams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sector_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('team_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['team_id', 'user_id']);
        });

        Schema::create('task_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sector_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('category');
            $table->timestamps();
        });

        Schema::create('gamification_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('version')->unique();
            $table->json('rules');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('demands', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->text('description');
            $table->foreignId('sector_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('municipal_locations')->nullOnDelete();
            $table->foreignId('task_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('attendant_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('executor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('registered')->index();
            $table->string('priority')->default('media');
            $table->string('complexity')->default('media');
            $table->string('origin_system')->default('mission_publica');
            $table->string('origin_reference')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('executor_delivered_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['origin_system', 'origin_reference']);
        });

        Schema::create('demand_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->foreignId('demand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('description')->nullable();
            $table->boolean('visible_to_requester')->default(false);
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('evidences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('demand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->boolean('visible_to_requester')->default(false);
            $table->timestamps();
        });

        Schema::create('legacy_tickets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->string('title');
            $table->text('description');
            $table->string('requester_name');
            $table->string('requester_email')->nullable();
            $table->string('sector_hint')->nullable();
            $table->string('status')->default('novo');
            $table->boolean('fail_next_sync')->default(false);
            $table->json('received_events')->nullable();
            $table->timestamps();
        });

        Schema::create('outbox_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->foreignId('demand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('connector')->default('legacy_demo');
            $table->string('direction')->default('outbound');
            $table->string('status')->default('pending')->index();
            $table->json('payload');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('score_ledgers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('demand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('rule_version');
            $table->integer('points');
            $table->string('source_event_uuid');
            $table->text('justification');
            $table->boolean('reversal')->default(false);
            $table->timestamps();
            $table->unique(['demand_id', 'source_event_uuid', 'user_id', 'team_id']);
        });

        Schema::create('ai_suggestions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('demand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('model');
            $table->string('status')->default('pending');
            $table->json('input_digest');
            $table->json('suggestion')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('push_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint')->unique();
            $table->json('keys');
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('ai_suggestions');
        Schema::dropIfExists('score_ledgers');
        Schema::dropIfExists('outbox_events');
        Schema::dropIfExists('legacy_tickets');
        Schema::dropIfExists('evidences');
        Schema::dropIfExists('demand_events');
        Schema::dropIfExists('demands');
        Schema::dropIfExists('gamification_rules');
        Schema::dropIfExists('task_types');
        Schema::dropIfExists('team_user');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('municipal_locations');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sector_id');
            $table->dropColumn(['roles', 'demo_user']);
        });

        Schema::dropIfExists('sectors');
    }
};
