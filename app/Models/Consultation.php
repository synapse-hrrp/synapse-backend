<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\Visite;

class Consultation extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'consultations';

    // ⚠️ on NE met PAS soignant_id / medecin_id dans fillable: source = visite.medecin_id
    protected $fillable = [
        'patient_id','visite_id', /* 'service_id', */ // si la colonne existe en base
        'date_acte',
        'categorie','type_consultation',
        'motif','examen_clinique','diagnostic','prescriptions','orientation_service',
        'donnees_specifiques',
        'statut',
    ];

    protected $casts = [
        'date_acte' => 'datetime',
        'donnees_specifiques' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (! $m->id)        $m->id = (string) Str::uuid();
            if (! $m->date_acte) $m->date_acte = now();
            if (! $m->statut)    $m->statut = 'en_cours';

            // Verrouiller depuis la visite
            if ($m->visite_id) {
                $v = Visite::find($m->visite_id);
                if ($v) {
                    // médecin responsable = médecin de la visite
                    if (empty($m->medecin_id))  $m->medecin_id  = $v->medecin_id;
                    // soignant = idem (si tu veux distinguer, adapte ici)
                    if (empty($m->soignant_id)) $m->soignant_id = $v->medecin_id;

                    if (empty($m->patient_id))  $m->patient_id  = $v->patient_id;

                    // Auto si la colonne service_id existe
                    if (Schema::hasColumn('consultations', 'service_id') && empty($m->service_id)) {
                        $m->service_id = $v->service_id;
                    }
                }
            }
        });

        static::updating(function (self $m) {
            // Si on change de visite, resynchroniser
            if ($m->isDirty('visite_id') && $m->visite_id) {
                $v = Visite::find($m->visite_id);
                if ($v) {
                    $m->medecin_id  = $v->medecin_id;
                    $m->soignant_id = $v->medecin_id;
                    if (empty($m->patient_id)) $m->patient_id = $v->patient_id;
                    if (Schema::hasColumn('consultations', 'service_id')) {
                        $m->service_id = $v->service_id;
                    }
                }
            }
        });
    }

    public function patient()  { return $this->belongsTo(\App\Models\Patient::class); }
    public function visite()   { return $this->belongsTo(\App\Models\Visite::class); }

    // Si tes FKs pointent vers personnels.id (comme ARU/SMI) :
    public function soignant() { return $this->belongsTo(\App\Models\Personnel::class, 'soignant_id'); }
    public function medecin()  { return $this->belongsTo(\App\Models\Personnel::class, 'medecin_id'); }

    // Si chez toi c’est users.id, remplace par:
    // public function soignant() { return $this->belongsTo(\App\Models\User::class, 'soignant_id'); }
    // public function medecin()  { return $this->belongsTo(\App\Models\User::class, 'medecin_id'); }

    // Seulement si tu ajoutes la colonne en base :
    public function service()  { return $this->belongsTo(\App\Models\Service::class); }
}
