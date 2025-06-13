<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Employee extends Model
{
    use HasFactory;
    protected $fillable = [
        'is_admin',
        'employee_id',
        'name',
        'whatsapp_id',
        'position',
        'shift_id',
        'salary',
        'foto_url',
        'is_active',
        'rotated',
    ];
    //
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }
    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }
    public function shift() : BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
    public function getFotoUrlAttribute($value)
    {
        if (empty($value)) {
            return null;
        }
        
        if (is_string($value) && strpos($value, '[') === 0) {
            try {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
            } catch (\Exception $e) {
                \Log::error("Error decoding foto_url: " . $e->getMessage());
            }
        }
        
        return $value;
    }
}
