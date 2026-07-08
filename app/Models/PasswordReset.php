<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordReset extends Model
{
    protected $table = 'password_resets';

    protected $fillable = [
        'email',
        'token',
        'expires_at',
        'use_at',
    ];

    protected function casts(): array
    {
        return[
            'expires_at' =>'datetime',
            'use_at'     =>'datetime',
        ];
    }
}