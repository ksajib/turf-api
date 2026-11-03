<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasUlids;

    protected $fillable = ['user_id', 'status'];

    public function user() { return $this->belongsTo(User::class); }
}
