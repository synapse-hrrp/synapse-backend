<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Accouchement extends Model
{
    use SoftDeletes;

    protected $table = 'accouchements';
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'mere_id','service_slug','created_via','created_by_user_id',
        'date_heure_accouchement','terme_gestationnel_sa','voie','presentation','type_cesarienne',
        'score_apgar_1_5','poids_kg','taille_cm','sexe',
        'complications_json','notes',
        'statut',
        // facturation
        'prix','devise','facture_id',
        // staff
        'sage_femme_id','obstetricien_id',
    ];

    protected $casts = [
        'date_heure_accouchement' => 'datetime',
        'complications_json'      => 'array',
        'poids_kg'                => 'decimal:2',
        'taille_cm'               => 'decimal:2',
        'prix'                    => 'decimal:2',
    ];

    protected static function booted(): void
    {
        $fillFromTarif = function (self $m, bool $failIfMissing = false): void {
            $code = config('billing.codes.accouchement', 'ACCOUCH'); // code par défaut

            $needsPrix = !isset($m->prix) || $m->prix === '' || (is_numeric($m->prix) && (float)$m->prix == 0.0);
            if (!$needsPrix && $m->devise) return;

            $tarifQ = \App\Models\Tarif::query()->actifs()->byCode($code);
            if ($m->service_slug) $tarifQ->forService($m->service_slug);
            $tarif = $tarifQ->latest('created_at')->first();

            if (!$tarif && $failIfMissing) {
                throw new \DomainException("Tarif introuvable pour accouchement (code={$code}, service={$m->service_slug})");
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

            $fillFromTarif($m, true); // tarif obligatoire
        });

        static::updating(function (self $m) use ($fillFromTarif) {
            if ($m->isDirty(['service_slug','prix','devise'])) {
                $fillFromTarif($m, false);
            }
        });
    }

    // Relations
    public function mere()          { return $this->belongsTo(Patient::class, 'mere_id'); }
    public function service()       { return $this->belongsTo(Service::class, 'service_slug', 'slug'); }
    public function creator()       { return $this->belongsTo(User::class, 'created_by_user_id'); }
    public function facture()       { return $this->belongsTo(Facture::class, 'facture_id'); }
    public function sageFemme()     { return $this->belongsTo(Personnel::class, 'sage_femme_id'); }
    public function obstetricien()  { return $this->belongsTo(Personnel::class, 'obstetricien_id'); }

    // Scopes
    public function scopeForService($q, ?string $slug)
    {
        return $slug ? $q->where('service_slug', $slug) : $q->whereNull('service_slug');
    }
}
