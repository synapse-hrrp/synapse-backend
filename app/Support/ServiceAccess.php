<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;

class ServiceAccess
{
    /**
     * Rôles qui voient TOUTES les factures (pas de filtre service).
     */
    protected array $globalRoles = [
        'admin',
        'admin_caisse',
        'caissier_general',
    ];

    /**
     * Est-ce que l'utilisateur a un accès global (toutes factures) ?
     */
    public function isGlobal(Authenticatable $user): bool
    {
        // Cas Spatie: hasAnyRole()
        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole($this->globalRoles);
        }

        // Sinon on lit les rôles "à la main"
        $roles = $this->extractRoleNames($user);

        return count(array_intersect($roles, $this->globalRoles)) > 0;
    }

    /**
     * Retourne les IDs de services autorisés pour cet utilisateur.
     *
     * Sert à filtrer :
     *   - les visites (visites.service_id)
     *   - les factures (factures.service_id + via visites + via examens)
     */
    public function allowedServiceIds(Authenticatable $user): array
    {
        $out = [];

        // 1) Si l'utilisateur a un attribut "service_ids" (array d'ids)
        if (isset($user->service_ids) && is_array($user->service_ids)) {
            foreach ($user->service_ids as $sid) {
                $n = (int) $sid;
                if ($n > 0) {
                    $out[] = $n;
                }
            }
        }

        // 1.b) Attribut "caisse_service_ids" (array d'ids pour les caissiers)
        if (isset($user->caisse_service_ids) && is_array($user->caisse_service_ids)) {
            foreach ($user->caisse_service_ids as $sid) {
                $n = (int) $sid;
                if ($n > 0) {
                    $out[] = $n;
                }
            }
        }

        // 2) Relations sur l'utilisateur : services / caisseServices / caisse_services
        if (method_exists($user, 'loadMissing')) {
            $relations = [];

            if (method_exists($user, 'services')) {
                $relations[] = 'services';
            }
            if (method_exists($user, 'caisseServices')) {
                $relations[] = 'caisseServices';
            }
            if (method_exists($user, 'caisse_services')) {
                $relations[] = 'caisse_services';
            }
            if (method_exists($user, 'personnel')) {
                // personnel principal + son service
                $relations[] = 'personnel.service';
                // ne pas forcer 'personnel.services' ici pour éviter l'erreur
            }

            if (count($relations)) {
                $user->loadMissing($relations);
            }
        }

        // 2.a) user->services
        if (isset($user->services)) {
            $services = $user->services;
            if ($services instanceof \Illuminate\Support\Collection) {
                foreach ($services as $svc) {
                    if ($svc && $svc->id) {
                        $out[] = (int) $svc->id;
                    }
                }
            } elseif (is_array($services)) {
                foreach ($services as $svc) {
                    if (is_object($svc) && isset($svc->id)) {
                        $out[] = (int) $svc->id;
                    } elseif (is_array($svc) && isset($svc['id'])) {
                        $out[] = (int) $svc['id'];
                    }
                }
            }
        }

        // 2.b) user->caisseServices (nom classique possible)
        if (isset($user->caisseServices)) {
            $services = $user->caisseServices;
            if ($services instanceof \Illuminate\Support\Collection) {
                foreach ($services as $svc) {
                    if ($svc && $svc->id) {
                        $out[] = (int) $svc->id;
                    }
                }
            } elseif (is_array($services)) {
                foreach ($services as $svc) {
                    if (is_object($svc) && isset($svc->id)) {
                        $out[] = (int) $svc->id;
                    } elseif (is_array($svc) && isset($svc['id'])) {
                        $out[] = (int) $svc['id'];
                    }
                }
            }
        }

        // 2.c) user->caisse_services (autre convention de nommage possible)
        if (isset($user->caisse_services)) {
            $services = $user->caisse_services;
            if ($services instanceof \Illuminate\Support\Collection) {
                foreach ($services as $svc) {
                    if ($svc && $svc->id) {
                        $out[] = (int) $svc->id;
                    }
                }
            } elseif (is_array($services)) {
                foreach ($services as $svc) {
                    if (is_object($svc) && isset($svc->id)) {
                        $out[] = (int) $svc->id;
                    } elseif (is_array($svc) && isset($svc['id'])) {
                        $out[] = (int) $svc['id'];
                    }
                }
            }
        }

        // 3) service_id principal sur le personnel
        if (isset($user->personnel) && $user->personnel) {
            // 3.a) champ service_id direct
            if (!empty($user->personnel->service_id)) {
                $out[] = (int) $user->personnel->service_id;
            }

            // 4) relation personnel.service
            if (isset($user->personnel->service) && $user->personnel->service?->id) {
                $out[] = (int) $user->personnel->service->id;
            }

            // 5) personnel.services (plusieurs services pour un même agent)
            // ⚠️ Ici on ne présume pas de la relation, on lit juste la propriété si elle existe
            if (isset($user->personnel->services)) {
                $ps = $user->personnel->services;
                if ($ps instanceof \Illuminate\Support\Collection) {
                    foreach ($ps as $svc) {
                        if ($svc && $svc->id) {
                            $out[] = (int) $svc->id;
                        }
                    }
                } elseif (is_array($ps)) {
                    foreach ($ps as $svc) {
                        if (is_object($svc) && isset($svc->id)) {
                            $out[] = (int) $svc->id;
                        } elseif (is_array($svc) && isset($svc['id'])) {
                            $out[] = (int) $svc['id'];
                        }
                    }
                }
            }
        }

        // Nettoyage : unique + ints positifs
        $out = array_map('intval', $out);
        $out = array_filter($out, fn ($v) => $v > 0);
        $out = array_values(array_unique($out));

        return $out;
    }

    /**
     * Récupère les noms de rôles de l'utilisateur sous forme de tableau de strings.
     */
    protected function extractRoleNames($user): array
    {
        // Spatie: getRoleNames()
        if (method_exists($user, 'getRoleNames')) {
            try {
                return $user->getRoleNames()->toArray();
            } catch (\Throwable $e) {
                // on tombera sur les autres méthodes
            }
        }

        // Attribut/Relation "roles"
        if (isset($user->roles)) {
            $roles = $user->roles;

            if ($roles instanceof \Illuminate\Support\Collection) {
                // Peut être collection de noms 'caissier_service', ou de modèles avec ->name
                return $roles->map(function ($r) {
                    if (is_string($r)) return $r;
                    if (is_object($r) && isset($r->name)) return (string) $r->name;
                    if (is_array($r) && isset($r['name'])) return (string) $r['name'];
                    return null;
                })->filter()->values()->all();
            }

            if (is_array($roles)) {
                return array_values(array_filter(array_map(function ($r) {
                    if (is_string($r)) return $r;
                    if (is_object($r) && isset($r->name)) return (string) $r->name;
                    if (is_array($r) && isset($r['name'])) return (string) $r['name'];
                    return null;
                }, $roles)));
            }
        }

        return [];
    }
}
