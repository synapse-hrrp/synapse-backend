<?php

namespace App\Enums;

enum VisiteStatut: string {
    case EN_ATTENTE  = 'EN_ATTENTE';
    case A_ENCAISSER = 'A_ENCAISSER';
    case PAYEE       = 'PAYEE';
    case CLOTUREE    = 'CLOTUREE';
}
