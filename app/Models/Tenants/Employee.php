<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Employee extends Model
{
    use HasFactory;
    protected $fillable = [
        'employee_id',
        'name',
        'whatsapp_id',
        'position',
        'shift',
        'salary',
        'foto_url',
        'is_active',
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
}
