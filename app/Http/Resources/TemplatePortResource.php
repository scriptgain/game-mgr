<?php

namespace App\Http\Resources;

class TemplatePortResource extends ApiResource
{
    public function objectName(): string
    {
        return 'template_port';
    }

    public function fields(): array
    {
        return [
            'id' => $this->id,
            'template_id' => $this->template_id,
            'role' => $this->role,
            'label' => $this->label,
            'protocol' => $this->protocol,
            'source' => $this->source,
            'port' => $this->port,
            'port_offset' => $this->port_offset,
            'required' => (bool) $this->required,
        ];
    }
}
