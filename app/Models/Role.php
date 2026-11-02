<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = ['business_id', 'name', 'permissions', 'status'];

    protected function casts(): array
    {
        return ['permissions' => 'array'];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
