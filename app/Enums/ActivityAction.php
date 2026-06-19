<?php

namespace App\Enums;

enum ActivityAction: string
{
    case CREATE = 'create';
    case UPDATE = 'update';
    case DELETE = 'delete';
    case APPROVE = 'approve';
    case REJECT = 'reject';
    case CANCEL = 'cancel';
    case MANUAL_UPDATE = 'manual_update';
    case SUBMIT = 'submit';
    case FINALIZE = 'finalize';
}