<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Attendance extends Model
{
    use HasFactory;
    protected $fillable = [
        'employee_id',
        'date',
        'clock_in',
        'clock_out',
        'status',
        'note',
    ];

    protected $dates = [
        'clock_in',
        'clock_out',
    ];
    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
