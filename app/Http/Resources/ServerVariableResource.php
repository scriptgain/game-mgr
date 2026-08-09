<?php

namespace App\Http\Resources;

class ServerVariableResource extends ApiResource
{
    public function objectName(): string
    {
        return 'server_variable';
    }

    public function fields(): array
    {
        return [
            'id' => $this->id,
            'server_id' => $this->server_id,
            'template_variable_id' => $this->template_variable_id,
            'value' => $this->value,
        ];
    }
}
