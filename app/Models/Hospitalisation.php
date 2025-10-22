<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Hospitalisation extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType   = 'string';
    protected $table     = 'hospitalisations';

    protected $fillable = [
        'patient_id',
        'service_slug',
        'admission_no',
        'created_via',
        'created_by_user_id',

        // logistique
        'unite','chambre','lit','lit_id','medecin_traitant_id',

        // clinique
        'motif_admission','diagnostic_entree','diagnostic_sortie','notes','prise_en_charge_json',

        // workflow
        'statut','date_admission','date_sortie_prevue','date_sortie_reelle',

        // facturation
        'prix','devise','facture_id',
    ];

    protected $casts = [
        'date_admission'       => 'datetime',
        'date_sortie_prevue'   => 'datetime',
        'date_sortie_reelle'   => 'datetime',
        'prise_en_charge_json' => 'array',
        'prix'                 => 'decimal:2',
    ];

    protected static function booted(): void
    {
        $fillFromTarif = function (self $m, bool $failIfMissing = false): void {
            // Code tarifaire “hospitalisation admission”
            $code = config('billing.codes.hospitalisation', 'HOSP_ADM');

            $needsPrix = !isset($m->prix) || $m->prix === '' || (is_numeric($m->prix) && (float)$m->prix == 0.0);
            if (!$needsPrix && $m->devise) return;

            $tarifQ = \App\Models\Tarif::query()->actifs()->byCode($code);
            if ($m->service_slug) $tarifQ->forService($m->service_slug);
            $tarif = $tarifQ->latest('created_at')->first();

            if (!$tarif && $failIfMissing) {
                throw new \DomainException("Tarif introuvable pour hospitalisation (code={$code}, service={$m->service_slug})");
            }

            if ($tarif) {
                if ($needsPrix)   $m->prix   = $tarif->montant;
                if (!$m->devise)  $m->devise = $tarif->devise ?? 'XAF';
            }
        };

        static::creating(function (self $m) use ($fillFromTarif) {
            if (!$m->id)              $m->id = (string) Str::uuid();
            if (!$m->statut)          $m->statut = 'en_cours';
            if (!$m->date_admission)  $m->date_admission = now();
            if (!$m->created_via)     $m->created_via = $m->service_slug ? 'service' : 'med';
            if (!$m->devise)          $m->devise = 'XAF';

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
    public function patient()         { return $this->belongsTo(Patient::class); }
    public function service()         { return $this->belongsTo(Service::class, 'service_slug', 'slug'); }
    public function medecinTraitant() { return $this->belongsTo(Personnel::class, 'medecin_traitant_id'); }
    public function creator()         { return $this->belongsTo(User::class, 'created_by_user_id'); }
    public function facture()         { return $this->belongsTo(Facture::class, 'facture_id'); }

    // Scopes
    public function scopeFromService($q) { return $q->where('created_via', 'service'); }
    public function scopeForService($q, ?string $slug)
    {
        return $slug ? $q->where('service_slug', $slug) : $q->whereNull('service_slug');
    }
}
