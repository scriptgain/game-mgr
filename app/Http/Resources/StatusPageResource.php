<?php

namespace App\Http\Resources;

class StatusPageResource extends ApiResource
{
    public function objectName(): string
    {
        return 'status_page';
    }

    public function fields(): array
    {
        return [
            'id' => $this->id,
            'server_id' => $this->server_id,
            'slug' => $this->slug,
            'headline' => $this->headline,
            'is_public' => (bool) $this->is_public,
            'show' => [
                'players' => (bool) $this->show_players,
                'address' => (bool) $this->show_address,
                'uptime' => (bool) $this->show_uptime,
                'version' => (bool) $this->show_version,
            ],
        ];
    }
}
