<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class DeclarationNaissance extends Model
{
    use SoftDeletes;

    protected $table = 'declarations_naissance';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'mere_id','service_slug','accouchement_id','created_via','created_by_user_id',
        'bebe_nom','bebe_prenom','pere_nom','pere_prenom',
        'date_heure_naissance','lieu_naissance','sexe','poids_kg','taille_cm','apgar_1','apgar_5',
        'numero_acte','officier_etat_civil','documents_json',
        'statut','date_transmission',

        // facturation
        'prix','devise','facture_id',
    ];

    protected $casts = [
        'date_heure_naissance' => 'datetime',
        'date_transmission'    => 'datetime',
        'documents_json'       => 'array',
        'poids_kg'             => 'decimal:2',
        'taille_cm'            => 'decimal:2',
        'apgar_1'              => 'integer',
        'apgar_5'              => 'integer',
        'prix'                 => 'decimal:2',
    ];

    protected static function booted(): void
    {
        $fillFromTarif = function (self $m, bool $failIfMissing = false): void {
            $code = config('billing.codes.declaration', 'DECL_NAIS');

            $needsPrix = !isset($m->prix) || $m->prix === '' || (is_numeric($m->prix) && (float)$m->prix == 0.0);
            if (!$needsPrix && $m->devise) return;

            $tarifQ = \App\Models\Tarif::query()->actifs()->byCode($code);
            if ($m->service_slug) $tarifQ->forService($m->service_slug);
            $tarif = $tarifQ->latest('created_at')->first();

            if (!$tarif && $failIfMissing) {
                throw new \DomainException("Tarif introuvable pour la déclaration (code={$code}, service={$m->service_slug})");
            }

            if ($tarif) {
                if ($needsPrix)   $m->prix   = $tarif->montant;
                if (!$m->devise)  $m->devise = $tarif->devise ?? 'XAF';
            }
        };

        static::creating(function (self $m) use ($fillFromTarif) {
            if (!$m->id)          $m->id = (string) Str::uuid();
            if (!$m->statut)      $m->statut = 'brouillon';
            if (!$m->created_via) $m->created_via = $m->service_slug ? 'service' : 'med';
            if (!$m->devise)      $m->devise = 'XAF';

            if (is_string($m->documents_json)) {
                $decoded = json_decode($m->documents_json, true);
                $m->documents_json = is_array($decoded) ? $decoded : null;
            }

            // 🔐 tarif requis
            $fillFromTarif($m, true);
        });

        static::updating(function (self $m) use ($fillFromTarif) {
            if (is_string($m->documents_json)) {
                $decoded = json_decode($m->documents_json, true);
                $m->documents_json = is_array($decoded) ? $decoded : null;
            }

            if ($m->isDirty(['service_slug','prix','devise'])) {
                $fillFromTarif($m, false);
            }
        });
    }

    // Relations
    public function mere()    { return $this->belongsTo(Patient::class, 'mere_id'); }
    public function service() { return $this->belongsTo(Service::class, 'service_slug', 'slug'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by_user_id'); }
    public function facture() { return $this->belongsTo(Facture::class, 'facture_id'); }

    // Scope
    public function scopeForService($q, ?string $slug)
    {
        return $slug ? $q->where('service_slug', $slug) : $q->whereNull('service_slug');
    }
}
