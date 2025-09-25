<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\Visite;

class Sanitaire extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'sanitaires';

    // ❗ on NE met PAS soignant_id dans $fillable : source de vérité = visite.medecin_id
    protected $fillable = [
        'patient_id','visite_id', /* 'service_id', */ // si tu ajoutes cette colonne plus tard
        'date_acte','date_debut','date_fin',
        'type_action','zone','sous_zone','niveau_risque',
        'produits_utilises','equipe','duree_minutes','cout',
        'observation','statut',
    ];

    protected $casts = [
        'date_acte'  => 'datetime',
        'date_debut' => 'datetime',
        'date_fin'   => 'datetime',
        'equipe'     => 'array',
        'cout'       => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (!$m->id)        $m->id = (string) Str::uuid();
            if (!$m->date_acte) $m->date_acte = now();
            if (!$m->statut)    $m->statut = 'planifie';

            // soignant = médecin de la visite
            if ($m->visite_id && empty($m->soignant_id)) {
                $m->soignant_id = Visite::whereKey($m->visite_id)->value('medecin_id');
            }
            // propager service_id si la colonne existe
            if (Schema::hasColumn('sanitaires', 'service_id') && $m->visite_id && empty($m->service_id)) {
                $m->service_id = Visite::whereKey($m->visite_id)->value('service_id');
            }
            // patient de la visite si manquant
            if ($m->visite_id && empty($m->patient_id)) {
                $m->patient_id = Visite::whereKey($m->visite_id)->value('patient_id');
            }
        });

        static::updating(function (self $m) {
            if ($m->isDirty('visite_id') || empty($m->soignant_id)) {
                if ($m->visite_id) {
                    $m->soignant_id = Visite::whereKey($m->visite_id)->value('medecin_id');
                    if (Schema::hasColumn('sanitaires','service_id')) {
                        $m->service_id = Visite::whereKey($m->visite_id)->value('service_id');
                    }
                    if (empty($m->patient_id)) {
                        $m->patient_id = Visite::whereKey($m->visite_id)->value('patient_id');
                    }
                }
            }
        });
    }

    // Relations
    public function patient()  { return $this->belongsTo(\App\Models\Patient::class); }
    public function visite()   { return $this->belongsTo(\App\Models\Visite::class); }
    // si tu as harmonisé vers personnels : remplace User par Personnel
    public function soignant() { return $this->belongsTo(\App\Models\Personnel::class, 'soignant_id'); }
    public function service()  { return $this->belongsTo(\App\Models\Service::class); } // si colonne ajoutée
}
