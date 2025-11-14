<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Patient extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'patients';

    protected $fillable = [
        'numero_dossier',
        'nom','prenom',
        'date_naissance','lieu_naissance','age_reporte','sexe',
        'nationalite','profession','adresse','quartier','telephone',
        'statut_matrimonial',
        'proche_nom','proche_tel',
        'groupe_sanguin','allergies',
        'assurance_id','numero_assure',
        'is_active',
    ];

    protected $casts = [
        'date_naissance' => 'date',
        'is_active' => 'boolean',
    ];

    protected $appends = ['age'];

    protected static function booted(): void
    {
        static::creating(function (self $p) {
            if (!$p->id) $p->id = (string) Str::uuid();
            if (!$p->numero_dossier) {
                $p->numero_dossier = sprintf('HSP-%s-%06d', now()->year, random_int(0, 999999));
            }
        });
    }

    public function getAgeAttribute(): ?int
    {
        if ($this->date_naissance) {
            return Carbon::parse($this->date_naissance)->age;
        }
        return $this->age_reporte;
    }

    // Relations utiles
    public function visites() { return $this->hasMany(Visite::class); }
}
