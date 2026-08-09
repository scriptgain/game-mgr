<?php

namespace App\Http\Resources;

class TemplateVariableResource extends ApiResource
{
    public function objectName(): string
    {
        return 'template_variable';
    }

    public function fields(): array
    {
        return [
            'id' => $this->id,
            'template_id' => $this->template_id,
            'name' => $this->name,
            'description' => $this->description,
            'env_variable' => $this->env_variable,
            'default_value' => $this->default_value,
            'rules' => $this->rules,
            'user_viewable' => (bool) $this->user_viewable,
            'user_editable' => (bool) $this->user_editable,
            'sort' => $this->sort,
        ];
    }
}
