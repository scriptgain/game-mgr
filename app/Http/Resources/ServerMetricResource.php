<?php

namespace App\Http\Resources;

class ServerMetricResource extends ApiResource
{
    public function objectName(): string
    {
        return 'metric';
    }

    public function fields(): array
    {
        return [
            'sampled_at' => $this->sampled_at?->toIso8601String(),
            'cpu' => (float) $this->cpu,
            'memory' => (int) $this->memory,
            'disk' => (int) $this->disk,
            'net_rx' => (int) $this->net_rx,
            'net_tx' => (int) $this->net_tx,
            'players' => (int) $this->players,
            'tick_rate' => (float) $this->tick_rate,
        ];
    }
}
