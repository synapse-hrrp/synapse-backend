<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class BilletSortie extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType   = 'string';
    protected $table     = 'billets_sortie';

    protected $fillable = [
        'patient_id','service_slug','admission_id',
        'created_via','created_by_user_id',
        'motif_sortie','diagnostic_sortie','resume_clinique','consignes',
        'traitement_sortie_json','rdv_controle_at','destination',
        'statut','remis_a','signature_par','date_signature','date_sortie_effective',

        // facturation
        'prix','devise','facture_id',
    ];

    protected $casts = [
        'traitement_sortie_json' => 'array',
        'rdv_controle_at'        => 'datetime',
        'date_signature'         => 'datetime',
        'date_sortie_effective'  => 'datetime',
        'prix'                   => 'decimal:2',
    ];

    protected static function booted(): void
    {
        $fillFromTarif = function (self $m, bool $failIfMissing = false): void {
            $code = config('billing.codes.billet_sortie', 'BIL_SORTIE');

            $needsPrix = !isset($m->prix) || $m->prix === '' || (is_numeric($m->prix) && (float)$m->prix == 0.0);
            if (!$needsPrix && $m->devise) return;

            $tarifQ = \App\Models\Tarif::query()->actifs()->byCode($code);
            if ($m->service_slug) $tarifQ->forService($m->service_slug);
            $tarif = $tarifQ->latest('created_at')->first();

            if (!$tarif && $failIfMissing) {
                throw new \DomainException("Tarif introuvable pour le billet de sortie (code={$code}, service={$m->service_slug})");
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

            // 🔐 tarif requis
            $fillFromTarif($m, true);
        });

        static::updating(function (self $m) use ($fillFromTarif) {
            if ($m->isDirty(['service_slug','prix','devise'])) {
                $fillFromTarif($m, false);
            }
        });
    }

    // Relations
    public function patient()    { return $this->belongsTo(Patient::class); }
    public function service()    { return $this->belongsTo(Service::class, 'service_slug', 'slug'); }
    public function signataire() { return $this->belongsTo(Personnel::class, 'signature_par'); }
    public function creator()    { return $this->belongsTo(User::class, 'created_by_user_id'); }
    public function facture()    { return $this->belongsTo(Facture::class, 'facture_id'); }

    // Scopes
    public function scopeFromService($q) { return $q->where('created_via', 'service'); }
    public function scopeForService($q, ?string $slug)
    {
        return $slug ? $q->where('service_slug', $slug) : $q->whereNull('service_slug');
    }
}
