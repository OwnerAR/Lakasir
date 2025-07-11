<?php

namespace App\Services\Tenants\Helpers;

use App\Models\Tenants\Shift;
use Carbon\Carbon;

class ShiftGroupHelper
{
    /**
     * Mengelompokkan shift berdasarkan jam kerja (1 shift = 1 grup)
     */
    public static function initializeShiftGroups(?int $offShiftId = null): array
    {
        $shiftGroups = [];
        $shifts = Shift::when($offShiftId, function ($query) use ($offShiftId) {
            return $query->where('id', '!=', $offShiftId);
        })->orderBy('start_time')->get();

        if ($shifts->isEmpty()) {
            return $shiftGroups;
        }

        // Group shifts by their time periods (morning, afternoon, night)
        $morningShifts = [];
        $afternoonShifts = [];
        $nightShifts = [];

        foreach ($shifts as $shift) {
            $startHour = (int)substr($shift->start_time, 0, 2);
            
            if ($startHour >= 4 && $startHour < 12) {
                $morningShifts[] = $shift->id;
            } elseif ($startHour >= 12 && $startHour < 18) {
                $afternoonShifts[] = $shift->id;
            } else {
                $nightShifts[] = $shift->id;
            }
        }

        // Create groups based on time periods
        if (!empty($morningShifts)) {
            $shiftGroups[1] = $morningShifts;
        }
        if (!empty($afternoonShifts)) {
            $shiftGroups[2] = $afternoonShifts;
        }
        if (!empty($nightShifts)) {
            $shiftGroups[3] = $nightShifts;
        }

        // If no groups were created, create a single group with all shifts
        if (empty($shiftGroups)) {
            $shiftGroups[1] = $shifts->pluck('id')->toArray();
        }

        return $shiftGroups;
    }
}
