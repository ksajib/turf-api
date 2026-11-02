<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class PlatformAdmin extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUlids, Notifiable;

    protected $table = 'users';

    protected $fillable = ['business_id', 'role_id', 'permissions', 'name', 'username', 'email', 'password', 'role', 'status', 'token_reference', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    protected function setIsActiveAttribute(bool $value): void
    {
        $this->attributes['status'] = $value ? 'active' : 'inactive';
        $this->attributes['role'] ??= 'platform-admin';
    }

    protected function username(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : strtolower(trim($value)));
    }

    protected function email(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : strtolower(trim($value)));
    }
}
