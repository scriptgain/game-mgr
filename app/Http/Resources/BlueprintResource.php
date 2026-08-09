<?php

namespace App\Http\Resources;

class BlueprintResource extends ApiResource
{
    public function objectName(): string
    {
        return 'blueprint';
    }

    public function fields(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'template_id' => $this->template_id,
            'limits' => $this->limits,
            'feature_limits' => $this->feature_limits,
            'environment' => $this->environment,
        ];
    }
}
