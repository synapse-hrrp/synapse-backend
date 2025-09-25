<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Models\Visite;

class Maternite extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'maternites';

    // On n’expose pas soignant_id en fillable : vérité = visite.medecin_id
    protected $fillable = [
        'patient_id','visite_id', /* 'service_id', */ // ← si tu ajoutes la colonne en DB
        'date_acte',
        'motif','diagnostic',
        'terme_grossesse','age_gestationnel','mouvements_foetaux',
        'tension_arterielle','temperature','frequence_cardiaque','frequence_respiratoire',
        'hauteur_uterine','presentation','battements_cardiaques_foetaux','col_uterin','pertes',
        'examen_clinique','traitements','observation',
        'statut',
    ];

    protected $casts = [
        'date_acte'         => 'datetime',
        'temperature'       => 'decimal:1',
        'mouvements_foetaux'=> 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (!$m->id)        $m->id = (string) Str::uuid();
            if (!$m->date_acte) $m->date_acte = now();
            if (!$m->statut)    $m->statut = 'en_cours';

            // 🔒 médecin = visite.medecin_id
            if ($m->visite_id && empty($m->soignant_id)) {
                $m->soignant_id = Visite::whereKey($m->visite_id)->value('medecin_id');
            }

            // Optionnel : si colonne service_id ajoutée, on reflète depuis la visite
            if (\Illuminate\Support\Facades\Schema::hasColumn('maternites','service_id')
                && $m->visite_id && empty($m->service_id)) {
                $m->service_id = Visite::whereKey($m->visite_id)->value('service_id');
            }
        });

        static::updating(function (self $m) {
            // si la visite change, on recalcule médecin (+ service éventuel)
            if ($m->isDirty('visite_id') || empty($m->soignant_id)) {
                if ($m->visite_id) {
                    $m->soignant_id = Visite::whereKey($m->visite_id)->value('medecin_id');
                    if (\Illuminate\Support\Facades\Schema::hasColumn('maternites','service_id')) {
                        $m->service_id = Visite::whereKey($m->visite_id)->value('service_id');
                    }
                }
            }
        });
    }

    // Relations
    public function patient()  { return $this->belongsTo(\App\Models\Patient::class); }
    public function visite()   { return $this->belongsTo(\App\Models\Visite::class); }
    public function soignant() { return $this->belongsTo(\App\Models\Personnel::class, 'soignant_id'); }
    public function service()  { return $this->belongsTo(\App\Models\Service::class); } // si service_id ajouté
}
