<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['business_id', 'role_id', 'role', 'permissions', 'name', 'username', 'email', 'phone', 'password', 'status', 'token_reference', 'must_change_password', 'temporary_password_expires_at', 'password_changed_at', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUlids, Notifiable;

    public function getTable(): string
    {
        return 'users';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'temporary_password_expires_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'permissions' => 'array',
        ];
    }

    public function roleRecord()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role || $this->roleRecord?->name === $role;
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = $this->permissions ?: $this->roleRecord?->permissions ?: [];
        return in_array($permission, $permissions, true)
            || (is_array($permissions) && in_array(strtok($permission, '.'), $permissions, true));
    }

    protected function username(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : strtolower(trim($value)));
    }

    protected function email(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : strtolower(trim($value)));
    }

    public function businesses()
    {
        return $this->belongsToMany(Business::class, 'business_owners')->withTimestamps();
    }

    public function customerProfile()
    {
        return $this->hasOne(Customer::class);
    }
}
