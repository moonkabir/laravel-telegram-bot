<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramMessage extends Model
{
    use HasFactory;

    protected $table = 'telegram_messages'; // Ensure this matches your migration

    protected $fillable = [
        'user_id',
        'username',
        'first_name',
        'last_name',
        'message_id',
        'message_text',
        'chat_id',
        'chat_type',
        'bot_response',
        'is_processed',
        'raw_data',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'is_processed' => 'boolean',
    ];
}
