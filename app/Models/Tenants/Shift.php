<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'start_time', 'end_time'];
    
    public function schedules()
    {
        return $this->hasMany(WorkSchedule::class);
    }
    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}