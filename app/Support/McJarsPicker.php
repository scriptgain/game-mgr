<?php

namespace App\Support;

use App\Models\Template;
use App\Models\TemplateVariable;
use App\Services\Minecraft\McJars;
use Illuminate\Support\Str;

/**
 * The bridge between one template's environment variables and the MCJars
 * catalogue.
 *
 * A template says it is Minecraft by carrying a `mcjars` document. Nothing
 * else does: not the game name, not the runtime, not a guess from the image
 * tag. The document is small and says exactly three things.
 *
 *   {
 *     "type_variable":    "TYPE",
 *     "version_variable": "VERSION",
 *     "builds": {
 *       "PAPER":  "PAPER_BUILD",
 *       "PURPUR": "PURPUR_BUILD",
 *       "SPIGOT": null
 *     }
 *   }
 *
 * The keys of `builds` are the type codes this template offers, in the order
 * they are offered. The value is the environment variable that pins a build
 * for that type, or null where pinning one is not possible.
 *
 * That last part is not tidiness. MCJars lists builds for Spigot, but
 * itzg/minecraft-server has no SPIGOT_BUILD to put one in, so offering a build
 * control for Spigot would be a control that changes nothing. The variables
 * differ by family too, and there is no rule that derives them: Paper uses
 * PAPER_BUILD, Purpur PURPUR_BUILD, Folia FOLIABUILD with no underscore, Forge
 * FORGE_VERSION, Fabric FABRIC_LOADER_VERSION. A boolean "this is Minecraft"
 * column could not have said any of it, which is why the column holds a
 * document instead.
 *
 * A build variable named in the document but absent from the template is
 * dropped rather than invented. The picker can only ever write to a variable
 * that already exists, so it can never post a field the controller would
 * reject as not belonging to this template.
 */
class McJarsPicker
{
    /** @param  array<string, ?TemplateVariable>  $buildVariables  type code => variable or null */
    private function __construct(
        public readonly Template $template,
        public readonly TemplateVariable $typeVariable,
        public readonly TemplateVariable $versionVariable,
        public readonly array $buildVariables,
    ) {}

    /**
     * Build a picker for this template, or null when the template is not a
     * Minecraft one or its document does not line up with its variables.
     */
    public static function for(Template $template): ?self
    {
        $doc = $template->mcjars;

        if (! is_array($doc) || $doc === []) {
            return null;
        }

        $byEnv = $template->variables->keyBy('env_variable');

        $type = $byEnv->get((string) ($doc['type_variable'] ?? 'TYPE'));
        $version = $byEnv->get((string) ($doc['version_variable'] ?? 'VERSION'));

        // Without somewhere to put the type and the version there is no picker
        // to draw. A template that lost those variables falls straight back to
        // whatever controls its remaining variables describe.
        if (! $type instanceof TemplateVariable || ! $version instanceof TemplateVariable) {
            return null;
        }

        $builds = [];

        foreach ((array) ($doc['builds'] ?? []) as $code => $env) {
            if (! is_string($code) || $code === '') {
                continue;
            }

            $variable = is_string($env) && $env !== '' ? $byEnv->get($env) : null;
            $builds[mb_strtoupper($code)] = $variable instanceof TemplateVariable ? $variable : null;
        }

        if ($builds === []) {
            return null;
        }

        return new self($template, $type, $version, $builds);
    }

    /** The type codes this template offers, in declaration order. */
    public function typeCodes(): array
    {
        return array_keys($this->buildVariables);
    }

    /**
     * The template variable ids the picker draws itself, so the caller can
     * leave them out of the generic variable loop and not render two controls
     * posting the same field name.
     *
     * @return array<int, int>
     */
    public function ownedVariableIds(): array
    {
        $ids = [$this->typeVariable->id, $this->versionVariable->id];

        foreach ($this->buildVariables as $variable) {
            if ($variable) {
                $ids[] = $variable->id;
            }
        }

        return array_values(array_unique($ids));
    }

    /** Is this variable one the picker owns? */
    public function owns(TemplateVariable $variable): bool
    {
        return in_array($variable->id, $this->ownedVariableIds(), true);
    }

    /**
     * Everything the browser needs to draw and drive the picker, as one array
     * ready to be JSON encoded into the page.
     *
     * `types` is only the subset MCJars agreed exists, decorated with its own
     * names and colours. When MCJars is unreachable it comes back empty and
     * the caller renders the plain inputs instead, which is what the panel did
     * before any of this existed.
     *
     * @param  array<int|string, string>  $values  current value per variable id
     */
    public function payload(McJars $api, array $values = []): array
    {
        $catalogue = $api->types();
        $chosenType = $this->currentType($values);

        $types = [];

        foreach ($this->typeCodes() as $code) {
            $info = $catalogue[$code] ?? null;

            // A code MCJars does not know is dropped rather than offered: the
            // version list behind it would be a guaranteed 400.
            if ($catalogue !== null && $info === null) {
                continue;
            }

            $types[] = [
                'code' => $code,
                'name' => $info['name'] ?? Str::headline(mb_strtolower($code)),
                'description' => $info['description'] ?? null,
                'color' => $info['color'] ?? null,
                'experimental' => (bool) ($info['experimental'] ?? false),
                'deprecated' => (bool) ($info['deprecated'] ?? false),
                'build_variable' => $this->buildVariables[$code]?->id,
                'build_label' => $this->buildVariables[$code]?->name,
                'build_env' => $this->buildVariables[$code]?->env_variable,
            ];
        }

        // Only the chosen type's versions travel with the page. The rest are
        // fetched when the operator picks one, because shipping the version
        // list for every type would be a megabyte of JSON to render a select
        // that shows one of them.
        $versions = $catalogue === null ? null : $api->versions($chosenType);

        return [
            'available' => $catalogue !== null && $types !== [],
            'types' => $types,
            'type_variable' => $this->typeVariable->id,
            'version_variable' => $this->versionVariable->id,
            'type' => $chosenType,
            'version' => (string) ($values[$this->versionVariable->id] ?? $this->versionVariable->default_value ?? ''),
            'versions' => $versions ?? [],
            'builds' => $this->buildValues($values),
            'endpoints' => [
                'versions' => route('minecraft.versions'),
                'builds' => route('minecraft.builds'),
            ],
        ];
    }

    /**
     * The type currently selected, which is the stored value when there is one
     * and the variable's default otherwise. Anything the document does not
     * offer falls back to the first offered code, so a picker can never open
     * showing a type it has no versions for.
     */
    public function currentType(array $values = []): string
    {
        $codes = $this->typeCodes();
        $current = mb_strtoupper(trim((string) ($values[$this->typeVariable->id] ?? $this->typeVariable->default_value ?? '')));

        return in_array($current, $codes, true) ? $current : (string) ($codes[0] ?? '');
    }

    /**
     * Current value of every build variable, keyed by variable id.
     *
     * All of them travel, not just the active type's. Each one is a required
     * or defaulted field on the template, so every hidden input has to keep
     * posting the value it already had while a different type is selected. A
     * picker that blanked FORGE_VERSION because the operator switched to
     * Fabric would fail validation on a field nobody could see.
     *
     * @return array<int, string>
     */
    public function buildValues(array $values = []): array
    {
        $out = [];

        foreach ($this->buildVariables as $variable) {
            if (! $variable) {
                continue;
            }

            $out[$variable->id] = (string) ($values[$variable->id] ?? $variable->default_value ?? '');
        }

        return $out;
    }
}
