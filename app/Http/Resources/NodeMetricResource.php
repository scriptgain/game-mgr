<?php

namespace App\Http\Resources;

class NodeMetricResource extends ApiResource
{
    public function objectName(): string
    {
        return 'node_metric';
    }

    public function fields(): array
    {
        return [
            'sampled_at' => $this->sampled_at?->toIso8601String(),
            'cpu' => (float) $this->cpu,
            'memory' => (int) $this->memory,
            'disk' => (int) $this->disk,
            'load' => (float) $this->load,
            'server_count' => (int) $this->server_count,
            'running_count' => (int) $this->running_count,
        ];
    }
}
