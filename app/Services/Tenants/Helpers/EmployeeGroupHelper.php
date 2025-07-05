<?php

namespace App\Services\Tenants\Helpers;

use Illuminate\Support\Collection;
use App\Models\Tenants\Employee;

class EmployeeGroupHelper
{
    /**
     * Membagi karyawan ke dalam grup shift dengan memperhatikan kepadatan shift
     */
    public static function divideEmployeesIntoGroups(Collection $employees, array $shiftGroups, callable $firstGroupRequiresMoreStaff, callable $assignNonRotatableEmployees, callable $countEmployeesPerGroup, callable $balanceFirstTwoGroups, callable $assignRotatableEmployees, callable $ensureNoEmptyGroups, callable $calculateGroupDistribution): array
    {
        $groups = [];
        $totalEmployees = $employees->count();
        foreach (array_keys($shiftGroups) as $groupId) {
            $groups[$groupId] = [];
        }
        if (empty($groups)) {
            $groups[1] = [];
            return $groups;
        }
        $totalGroups = count($groups);
        $isTwoShiftPattern = $totalGroups === 2;
        $firstGroupHasPriority = $isTwoShiftPattern && $firstGroupRequiresMoreStaff();
        list($groupDistribution, $minGroup1, $maxGroup2) = $calculateGroupDistribution(
            $totalEmployees, 
            $totalGroups, 
            $isTwoShiftPattern, 
            $firstGroupHasPriority
        );
        $assignedEmployees = $assignNonRotatableEmployees($employees, $groups);
        $groupCounts = $countEmployeesPerGroup($groups);
        if ($isTwoShiftPattern && $firstGroupHasPriority) {
            $balanceFirstTwoGroups($groups, $groupCounts, $maxGroup2);
        }
        $assignRotatableEmployees($employees, $groups, $groupCounts, $isTwoShiftPattern, $firstGroupHasPriority, $maxGroup2);
        $ensureNoEmptyGroups($groups, $groupCounts, $totalEmployees);
        return $groups;
    }
}
