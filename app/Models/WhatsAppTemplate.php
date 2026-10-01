<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Table('whatsapp_templates')]
#[Fillable(['name', 'language', 'category', 'status', 'body', 'variables'])]
class WhatsAppTemplate extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return ['variables' => 'integer'];
    }

    public function isApproved(): bool
    {
        return $this->status === 'APPROVED';
    }

    public function label(): string
    {
        return "{$this->name} ({$this->language})";
    }
}
