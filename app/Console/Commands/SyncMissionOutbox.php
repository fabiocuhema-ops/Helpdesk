<?php

namespace App\Console\Commands;

use App\Models\LegacyTicket;
use App\Models\OutboxEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:sync-mission-outbox')]
#[Description('Synchronize pending Missao Publica outbox events with the simulated legacy service.')]
class SyncMissionOutbox extends Command
{
    public function handle(): int
    {
        $events = OutboxEvent::whereIn('status', ['pending', 'failed'])
            ->where(function ($query): void {
                $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
            })
            ->oldest()
            ->limit(25)
            ->get();

        foreach ($events as $event) {
            $event->attempts++;

            try {
                $reference = $event->payload['origin_reference'] ?? null;
                $ticket = $reference ? LegacyTicket::where('external_id', $reference)->first() : LegacyTicket::oldest()->first();

                if ($ticket?->fail_next_sync) {
                    $ticket->update(['fail_next_sync' => false]);
                    throw new \RuntimeException('Falha temporaria provocada no legado simulado.');
                }

                if ($ticket) {
                    $received = $ticket->received_events ?? [];
                    $received[$event->event_uuid] = [
                        'received_at' => now()->toIso8601String(),
                        'payload' => $event->payload,
                    ];
                    $ticket->update(['received_events' => $received, 'status' => $event->payload['status'] ?? $ticket->status]);
                }

                $event->update(['status' => 'sent', 'last_error' => null, 'next_attempt_at' => null]);
                $this->info("sent {$event->event_uuid}");
            } catch (\Throwable $exception) {
                $delay = min(60, 2 ** min($event->attempts, 5));
                $event->update([
                    'status' => 'failed',
                    'last_error' => $exception->getMessage(),
                    'next_attempt_at' => now()->addSeconds($delay),
                ]);
                $this->warn("failed {$event->event_uuid}: {$exception->getMessage()}");
            }
        }

        $this->info("processed {$events->count()} events");

        return self::SUCCESS;
    }
}
