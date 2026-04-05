<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    protected $fillable = [
        'user_id',
        'to',
        'cc',
        'bcc',
        'subject',
        'body',
        'sent_at',
    ];

    protected $casts = [
        'to' => 'json',
        'cc' => 'json',
        'bcc' => 'json',
        'sent_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
