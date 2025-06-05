<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Employee extends Model
{
    use HasFactory;
    protected $fillable = [
        'employeeId',
        'name',
        'whatsappId',
        'position',
        'shift',
        'salary',
        'isActive',
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
