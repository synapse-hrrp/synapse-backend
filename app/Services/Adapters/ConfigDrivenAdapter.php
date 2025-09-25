<?php

namespace App\Services\Adapters;

use App\Models\Visite;
use App\Services\Contracts\ServiceAdapter;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ConfigDrivenAdapter implements ServiceAdapter
{
    public function handleVisit(Visite $visit, array $payload): void
    {
        // --- Récupération sûre de la config service (compatible PHP < 8) ---
        $cfg = [];
        $service = $visit->service; // peut être null si non chargé
        if ($service) {
            $raw = $service->config; // peut être array si casté, sinon string/json/null
            if (is_array($raw)) {
                $cfg = $raw;
            } elseif (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) $cfg = $decoded;
            }
        }

        $modelClass = isset($cfg['model']) ? $cfg['model'] : null;
        $table      = isset($cfg['table']) ? $cfg['table'] : null; // fallback si pas de modèle
        $fieldMap   = isset($cfg['field_map']) ? $cfg['field_map'] : [];
        $uniqueBy   = isset($cfg['unique_by']) ? $cfg['unique_by'] : ['visite_id'];

        // Construire les attributs à partir du mapping
        $attrs = [];
        foreach ($fieldMap as $target => $expr) {
            $attrs[$target] = $this->resolve($expr, $visit, $payload, $cfg);
        }

        // Clés d’unicité
        $unique = [];
        foreach ($uniqueBy as $key) {
            $unique[$key] = array_key_exists($key, $attrs)
                ? $attrs[$key]
                : $this->resolve('@visit.'.$key, $visit, $payload, $cfg);
        }

        // Création via modèle si disponible
        if ($modelClass && class_exists($modelClass)) {
            $modelClass::query()->firstOrCreate($unique, $attrs);
            return;
        }

        // Fallback table brute si pas de modèle
        if ($table) {
            DB::table($table)->updateOrInsert($unique, $attrs);
            return;
        }

        throw new \RuntimeException("Config service invalide: ni 'model' existant, ni 'table'.");
    }

    private function resolve($expr, Visite $visit, array $payload, array $cfg)
    {
        if ($expr === null || is_bool($expr) || is_numeric($expr)) return $expr;
        if (!is_string($expr)) return $expr;

        // Opérateur "A || B || C" (sans str_contains)
        if (strpos($expr, '||') !== false) {
            $parts = array_map('trim', explode('||', $expr));
            foreach ($parts as $part) {
                $val = $this->resolve($part, $visit, $payload, $cfg);
                if ($val !== null && $val !== '') return $val;
            }
            return null;
        }

        // Équivalents du match(...) en if/elseif
        if ($expr === '@now')      return now();
        if ($expr === '@uuid')     return (string) Str::uuid();
        if ($expr === '@actor')    return isset($payload['actor_user_id']) ? $payload['actor_user_id'] : null;

        if (Str::startsWith($expr, '@const:')) {
            return substr($expr, 7);
        }

        if (Str::startsWith($expr, '@visit.')) {
            return data_get($visit, substr($expr, 7));
        }

        if (Str::startsWith($expr, '@payload.')) {
            return data_get($payload, substr($expr, 9));
        }

        if (Str::startsWith($expr, '@config.')) {
            return data_get($cfg, substr($expr, 8));
        }

        // Littéral
        return $expr;
    }
}
