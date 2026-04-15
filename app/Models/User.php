<?php

namespace App\Models;



use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Enums\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;



#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

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
            'two_factor_confirmed_at' => 'datetime',
            'role' => UserRole::class
        ];
    } 

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    public function isCashier(): bool
    {
        return $this->role === UserRole::CASHIER;
    }

    /**
     * Relationships
     */
    public function orders()
    {
        return $this->hasMany(\App\Models\Order::class, 'cashier_id');
    }

    // Relasi ke ProductReturn (jika ada)
    public function returns()
    {
        return $this->hasMany(\App\Models\ProductReturn::class, 'cashier_id');
    }

    // Relasi untuk returns yang disetujui oleh user ini (jika user adalah admin)
    public function approvedReturns()
    {
        return $this->hasMany(\App\Models\ProductReturn::class, 'approved_by');
    }

    // Returns & Chatbot relationships akan ditambah di Phase 3 & 4
}
