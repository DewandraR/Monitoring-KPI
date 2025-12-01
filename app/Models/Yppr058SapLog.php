<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Yppr058SapLog extends Model
{
    protected $fillable = [
        'batch_id',
        'sap_user',
        'pernr',
        'cname',
        'arbpl',
        'start_date',
        'end_date',
        'mint2',
        'mintu',
        'mintu2',
        'mintu3',
        'ok',
        'return_type',
        'return_id',
        'return_number',
        'return_message',
        'message_v1',
        'message_v2',
        'message_v3',
        'message_v4',
        'error_raw',
    ];
}
