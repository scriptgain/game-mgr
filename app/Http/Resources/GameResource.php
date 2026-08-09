<?php

namespace App\Http\Resources;

class GameResource extends ApiResource
{
    public function objectName(): string
    {
        return 'game';
    }

    public function fields(): array
    {
        return $this->resource->only($this->resource->getFillable())
            + ['id' => $this->id, 'created_at' => $this->created_at?->toIso8601String()];
    }
}
