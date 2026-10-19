<?php
namespace App\Modules\Auth;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
class Employee extends Authenticatable {
    use Notifiable;
    protected $table = 'orbita_users';
    protected $attributes = ['role' => 'editor', 'is_active' => true];
    protected $fillable = ['name', 'email', 'password', 'role', 'is_active'];
    protected $hidden = ['password', 'remember_token'];
    protected function casts(): array { return ['password' => 'hashed', 'is_active' => 'boolean']; }
}
