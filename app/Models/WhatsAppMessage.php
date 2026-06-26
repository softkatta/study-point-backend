<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'to_phone',
        'message_type',
        'body',
        'template_params',
        'document_filename',
        'document_path',
        'provider',
        'external_id',
        'status',
        'related_type',
        'related_id',
        'error_message',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'template_params' => 'array',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function related(): ?Model
    {
        if (! $this->related_type || ! $this->related_id) {
            return null;
        }

        if (! class_exists($this->related_type)) {
            return null;
        }

        return $this->related_type::find($this->related_id);
    }
}
