<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Echographie extends Model
{
    use SoftDeletes;

    /** =========================
     *  Constantes & configs
     *  ========================= */
    public const STAT_EN_ATTENTE = 'en_attente';
    public const STAT_EN_COURS   = 'en_cours';
    public const STAT_TERMINE    = 'termine';
    public const STAT_VALIDE     = 'valide';

    public const ORIG_SERVICE = 'service';
    public const ORIG_LABO    = 'labo';

    public $incrementing = false;
    protected $keyType   = 'string';
    protected $table     = 'echographies';

    /** Champs exposés */
    protected $fillable = [
        'patient_id',
        'service_slug',
        'type_origine',         // interne | externe
        'prescripteur_externe',
        'reference_demande',

        // traçabilité
        'created_via',          // 'labo' | 'service'
        'created_by_user_id',

        // infos écho
        'code_echo',
        'nom_echo',
        'indication',
        'statut',               // en_attente | en_cours | termine | valide
        'compte_rendu',
        'conclusion',
        'mesures_json',
        'images_json',

        // facturation (remplis auto)
        'prix','devise','facture_id',

        // workflow
        'demande_par','date_demande',
        'realise_par','date_realisation',
        'valide_par','date_validation',
    ];

    /** Casts + attributs calculés */
    protected $casts = [
        'date_demande'     => 'datetime',
        'date_realisation' => 'datetime',
        'date_validation'  => 'datetime',
        'mesures_json'     => 'array',
        'images_json'      => 'array',
        'prix'             => 'decimal:2',
    ];

    protected $appends = [
        'has_images',
        'prix_formate',
    ];

    /** =========================
     *  Boot & auto-fill
     *  ========================= */
    protected static function booted(): void
    {
        // Complète nom/prix/devise depuis la tarification.
        // $failIfMissing = true à la création pour imposer un tarif valide.
        $fillFromTarif = function (self $m, bool $failIfMissing = false): void {
            if ($m->code_echo) {
                $m->code_echo = strtoupper(trim($m->code_echo));
            }

            $needsName = empty($m->nom_echo);
            $needsPrix = !isset($m->prix) || $m->prix === '' || (is_numeric($m->prix) && (float) $m->prix == 0.0);
            $needsDev  = empty($m->devise);

            if (! $needsName && ! $needsPrix && ! $needsDev) return;

            $tarifQ = \App\Models\Tarif::query()->actifs();
            if ($m->service_slug) $tarifQ->forService($m->service_slug);
            if ($m->code_echo)    $tarifQ->byCode($m->code_echo);

            $tarif = $tarifQ->latest('created_at')->first();

            if (!$tarif && $failIfMissing) {
                throw new \DomainException("Tarif introuvable pour l'échographie (code={$m->code_echo}, service={$m->service_slug})");
            }

            if ($tarif) {
                if ($needsName) $m->nom_echo = $tarif->libelle ?: $tarif->code;
                if ($needsPrix) $m->prix     = $tarif->montant;
                if ($needsDev)  $m->devise   = $tarif->devise ?? 'XAF';
            }
        };

        static::creating(function (self $m) use ($fillFromTarif) {
            if (! $m->id)             $m->id = (string) Str::uuid();
            if (! $m->date_demande)   $m->date_demande = now();
            if (! $m->statut)         $m->statut = self::STAT_EN_ATTENTE;

            // Origine explicite selon la présence d'un service
            if (! $m->created_via)    $m->created_via  = $m->service_slug ? self::ORIG_SERVICE : self::ORIG_LABO;

            // Cohérence avec ancien champ
            if (! $m->type_origine)   $m->type_origine = $m->service_slug ? 'interne' : 'externe';

            // Devise par défaut (sécurité)
            if (! $m->devise)         $m->devise = 'XAF';

            // 🔐 tarif OBLIGATOIRE à la création
            $fillFromTarif($m, true);
        });

        // Recompléter si code/service/prix/nom/devise changent (sans bloquer)
        static::updating(function (self $m) use ($fillFromTarif) {
            if ($m->isDirty(['code_echo','service_slug','prix','nom_echo','devise'])) {
                $fillFromTarif($m, false);
            }
        });
    }

    /** =========================
     *  Relations
     *  ========================= */
    public function patient()     { return $this->belongsTo(Patient::class); }
    public function service()     { return $this->belongsTo(Service::class, 'service_slug', 'slug'); }
    public function demandeur()   { return $this->belongsTo(Personnel::class, 'demande_par'); }
    public function operateur()   { return $this->belongsTo(Personnel::class, 'realise_par'); }   // sonographe/radiologue
    public function validateur()  { return $this->belongsTo(Personnel::class, 'valide_par'); }
    public function creator()     { return $this->belongsTo(User::class, 'created_by_user_id'); }
    public function facture()     { return $this->belongsTo(Facture::class, 'facture_id'); }

    /** =========================
     *  Scopes pratiques
     *  ========================= */
    public function scopeFromService($q) { return $q->where('created_via', self::ORIG_SERVICE); }
    public function scopeFromLabo($q)    { return $q->where('created_via', self::ORIG_LABO); }

    public function scopeForService($q, ?string $slug)
    {
        return $slug ? $q->where('service_slug', $slug) : $q->whereNull('service_slug');
    }

    public function scopeStatut($q, string $statut) { return $q->where('statut', $statut); }
    public function scopeValides($q) { return $q->where('statut', self::STAT_VALIDE); }
    public function scopeEnCours($q) { return $q->where('statut', self::STAT_EN_COURS); }

    public function scopeDateBetween($q, ?string $from, ?string $to, string $col = 'date_demande')
    {
        return $q->when($from, fn($qq) => $qq->whereDate($col, '>=', $from))
                 ->when($to,   fn($qq) => $qq->whereDate($col, '<=', $to));
    }

    public function scopeSearch($q, ?string $term)
    {
        if (! $term) return $q;
        $t = trim($term);
        return $q->where(function ($qq) use ($t) {
            $qq->where('nom_echo', 'like', "%$t%")
               ->orWhere('code_echo', 'like', "%$t%")
               ->orWhere('indication', 'like', "%$t%")
               ->orWhere('conclusion', 'like', "%$t%");
        });
    }

    /** =========================
     *  Mutators / Accessors
     *  ========================= */
    public function setCodeEchoAttribute(?string $value): void
    {
        $this->attributes['code_echo'] = $value ? strtoupper(trim($value)) : null;
    }

    public function setDeviseAttribute(?string $value): void
    {
        $this->attributes['devise'] = $value ? strtoupper(trim($value)) : null;
    }

    public function setStatutAttribute(?string $value): void
    {
        if (! $value) { $this->attributes['statut'] = null; return; }
        $value = strtolower(trim($value));
        $allowed = [
            self::STAT_EN_ATTENTE,
            self::STAT_EN_COURS,
            self::STAT_TERMINE,
            self::STAT_VALIDE,
        ];
        $this->attributes['statut'] = in_array($value, $allowed, true) ? $value : self::STAT_EN_ATTENTE;
    }

    /** Attributs calculés */
    public function getHasImagesAttribute(): bool
    {
        $imgs = $this->images_json ?? [];
        return is_array($imgs) && count($imgs) > 0;
    }

    public function getPrixFormateAttribute(): ?string
    {
        if (is_null($this->prix)) return null;
        $dev = $this->devise ?: 'XAF';
        $num = number_format((float) $this->prix, 2, '.', ' ');
        return "{$num} {$dev}";
    }

    /** =========================
     *  Helpers métier
     *  ========================= */
    public function markAsTermine(?string $operateurId = null, ?\DateTimeInterface $at = null): self
    {
        $this->statut = self::STAT_TERMINE;
        if ($operateurId)       $this->realise_par = $operateurId;
        if ($at instanceof \DateTimeInterface) {
            $this->date_realisation = $at;
        } elseif (! $this->date_realisation) {
            $this->date_realisation = now();
        }
        $this->save();
        return $this->refresh();
    }

    public function markAsValide(?string $validateurId = null, ?\DateTimeInterface $at = null): self
    {
        $this->statut = self::STAT_VALIDE;
        if ($validateurId)      $this->valide_par = $validateurId;
        if ($at instanceof \DateTimeInterface) {
            $this->date_validation = $at;
        } elseif (! $this->date_validation) {
            $this->date_validation = now();
        }
        $this->save();
        return $this->refresh();
    }
}
