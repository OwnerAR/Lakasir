<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkSchedule extends Model
{
    use HasFactory;

    protected $fillable = ['employee_id', 'shift_id', 'date', 'status'];
    
    protected $casts = [
        'date' => 'date'
    ];
    
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
    
    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }
}