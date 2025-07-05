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
        foreach ($shifts as $shift) {
            $shiftGroups[$shift->id] = [$shift->id];
        }
        if (empty($shiftGroups)) {
            $shiftGroups[1] = $shifts->pluck('id')->toArray();
        }
        return $shiftGroups;
    }
}
