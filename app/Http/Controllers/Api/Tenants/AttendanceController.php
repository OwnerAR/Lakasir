<?php

namespace App\Http\Controllers\Api\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenants\Attendance;
use App\Models\Tenants\Employee;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'whatsapp_id' => 'required|exists:employees,whatsapp_id',
                'clock_in' => 'required_without:clock_out|nullable|date_format:H:i',
                'clock_out' => 'required_without:clock_in|nullable|date_format:H:i|after:clock_in',
                'status' => 'required|string',
                'note' => 'nullable|string|max:255',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
            ], 422);
        }
        $employee = Employee::where('whatsapp_id', $validated['whatsapp_id'])->first();
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => __('Employee not found'),
            ], 404);
        }
        if (!$employee->is_active) {
            return response()->json([
                'success' => false,
                'message' => __('Employee is not active'),
            ], 403);
        }

        $attendanceToday = Attendance::where('employee_id', $employee->id)
            ->whereDate('created_at', now()->toDateString())
            ->first();
        if ($attendanceToday) {
            if (!empty($attendanceToday->clock_in) && !empty($attendanceToday->clock_out)) {
                return response()->json([
                    'success' => false,
                    'message' => __('Attendance already exists for today'),
                ], 403);
            }
        }
        if (!empty($validated['clock_in']) && !$attendanceToday->clock_in) {
            $clockIn = $validated['clock_in'];
            $shift = null;
            if ($clockIn >= '07:00' && $clockIn <= '08:00') {
                $shift = 'pagi';
            } elseif ($clockIn >= '17:00' && $clockIn <= '19:00') {
                $shift = 'sore';
            } elseif ($clockIn >= '21:00' && $clockIn <= '23:00') {
                $shift = 'malam';
            }
            if (strtolower($employee->shift) !== $shift) {
                return response()->json([
                    'success' => false,
                    'message' => __('Employee shift does not match'),
                ], 403);
            }
            $attendance = Attendance::create(array_merge($validated, [
                'employee_id' => $employee->id,
                'shift' => $shift,
            ]));
            return response()->json([
                'success' => true,
                'data'    => $attendance,
            ]);
        }


        if (!empty($validated['clock_out'])) {
            $attendance = Attendance::where('employee_id', $employee->id)
                ->whereDate('created_at', now()->toDateString())
                ->whereNull('clock_out')
                ->first();

            if (!$attendance) {
                return response()->json([
                    'success' => false,
                    'message' => __('No attendance record found for clock out'),
                ], 404);
            }

            $attendance->clock_out = $validated['clock_out'];
            $attendance->note = $validated['note'] ?? $attendance->note;
            $attendance->save();

            return response()->json([
                'success' => true,
                'data'    => $attendance,
            ]);
        }
        return response()->json([
            'success' => false,
            'message' => __('Invalid request, clock_in or clock_out is required'),
        ], 400);
    }
}
