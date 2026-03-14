<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    public const CEO = 'ceo';
    public const MD = 'md';
    public const CHIEF_MARKET = 'chief_market';
    public const EMPLOYEE = 'employee';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public static function defaultRoles(): array
    {
        return [
            [
                'name' => 'CEO',
                'slug' => self::CEO,
                'description' => 'Chief Executive Officer with full system access.',
                'is_active' => true,
            ],
            [
                'name' => 'Managing Director',
                'slug' => self::MD,
                'description' => 'Manages company operations and reporting.',
                'is_active' => true,
            ],
            [
                'name' => 'Chief of Market',
                'slug' => self::CHIEF_MARKET,
                'description' => 'Handles OTA listings, marketing, campaigns, and visibility.',
                'is_active' => true,
            ],
            [
                'name' => 'Employee',
                'slug' => self::EMPLOYEE,
                'description' => 'Standard employee with limited access.',
                'is_active' => true,
            ],
        ];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}