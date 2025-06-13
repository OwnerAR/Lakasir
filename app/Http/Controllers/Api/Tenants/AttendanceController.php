<?php

namespace App\Http\Controllers\Api\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenants\Attendance;
use App\Models\Tenants\Employee;
use App\Models\Tenants\WorkSchedule;
use App\Models\Tenants\Shift;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    private function store(array $data)
    {
        try {
            $validator = \Validator::make($data, [
                'whatsapp_id' => 'required|exists:employees,whatsapp_id',
                'date' => 'required|date',
                'clock_in' => 'required_without:clock_out|nullable|date_format:H:i',
                'clock_out' => 'required_without:clock_in|nullable|date_format:H:i|after:clock_in',
                'status' => 'nullable|string',
                'note' => 'nullable|string|max:255',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }
            $validated = $validator->validated();
            $employee = Employee::where('whatsapp_id', $validated['whatsapp_id'])->first();
            $attendanceToday = Attendance::where('employee_id', $employee->id)
                ->whereDate('created_at', now()->toDateString())
                ->first();
            if ($attendanceToday) {
                if (!empty($attendanceToday->clock_in) && !empty($attendanceToday->clock_out)) {
                    return response()->json([
                        'success' => false,
                        'message' => __('Attendance already exists for today'),
                    ], 200);
                }
            }
            if (!empty($validated['clock_in'])) {
                $clockIn = $validated['clock_in'];
                $clockInTime = Carbon::parse($clockIn);
                $isLate = false;
                
                // Get the applicable shift based on employee type
                if ($employee->is_admin) {
                    $shift = Shift::find($employee->shift_id);
                    
                    if (!$shift) {
                        return response()->json([
                            'success' => false,
                            'message' => __('Shift not found for this employee. Please contact HR department.'),
                        ], 200);
                    }
                } else {
                    // Non-admin employees follow the work schedule
                    $workSchedule = WorkSchedule::where('employee_id', $employee->id)
                        ->whereDate('date', now()->toDateString())
                        ->first();

                    if (!$workSchedule) {
                        return response()->json([
                            'success' => false,
                            'message' => __('You do not have any scheduled shift today.'),
                        ], 200);
                    }
                    
                    $shift = Shift::find($workSchedule->shift_id);
                    if (!$shift) {
                        return response()->json([
                            'success' => false,
                            'message' => __('Scheduled shift not found. Please contact HR department.'),
                        ], 200);
                    }
                }
                
                // Check for 15 minutes before and after start_time window for all employees
                $shiftStart = Carbon::parse($shift->start_time);
                $earlyLimit = $shiftStart->copy()->subMinutes(15);
                $lateLimit = $shiftStart->copy()->addMinutes(15);
                
                // Clock in is only allowed 15 minutes before or after start time
                if ($clockInTime->lt($earlyLimit) || $clockInTime->gt($lateLimit)) {
                    return response()->json([
                        'success' => false,
                        'message' => __('Clock in is only allowed within 15 minutes before or after your shift start time (' . $shift->start_time . ').'),
                    ], 200);
                }
                
                // Determine if the employee is late
                $isLate = $clockInTime->gt($shiftStart);
                
                // Prepare attendance data
                $attendanceData = array_merge($validated, [
                    'employee_id' => $employee->id,
                    'shift' => $shift->name,
                    'shift_id' => $shift->id,
                    'status' => $isLate ? 'late' : 'present',
                ]);
                
                if ($isLate) {
                    $lateMinutes = $clockInTime->diffInMinutes($shiftStart);
                    $lateNote = "(Terlambat $lateMinutes menit)";
                    $attendanceData['note'] = trim(($validated['note'] ?? '') . ' ' . $lateNote);
                }
                
                $attendance = Attendance::create($attendanceData);
                
                return response()->json([
                    'success' => true,
                    'message' => $isLate ? 
                        'Attendance recorded successfully. You are late.' :
                        'Attendance recorded successfully.',
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
                    ], 200);
                }

                $attendance->clock_out = $validated['clock_out'];
                $attendance->note = $validated['note'] ?? $attendance->note;
                $attendance->save();

                return response()->json([
                    'success' => true,
                    'message' => 'attendance out successfully',
                ]);
            }
            return response()->json([
                'success' => false,
                'message' => __('Invalid request, clock_in or clock_out is required'),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error storing attendance', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            return response()->json([
                'success' => false,
                'message' => __('Failed to store attendance'),
            ], 500);
        }
    }
    /**
     * Store attendance via WhatsApp.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeAttendance(Request $request)
    {
        try {
            $validator = \Validator::make($request->all(), [
                'from' => ['required', 'string'],
                'participant' => ['required', 'string'],
                'message' => ['nullable', 'string'],
                'media' => ['nullable', 'string'],
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid request',
                    'errors' => $validator->errors(),
                ], 422);
            }
            if ($request->input('from') !== config('app.whatsapp_id')) {
                Log::error('Invalid sender', [
                    'from' => $request->input('from'),
                    'expected' => config('app.whatsapp_id'),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => __('Invalid sender'),
                    'errors' => [
                        'from' => $request->input('from') . ' is not allowed',
                    ],
                ], 400);
            }
            $whatsappId = trim(str_replace('@s.whatsapp.net', '', strtolower($request->input('participant'))));
            if (Redis::get('whatsapp:ignore_self:' . $whatsappId) || $request->input('fromMe')) {
                return response()->json([
                    'success' => false,
                ], 400);
            }

            $employee = Employee::where('whatsapp_id', $whatsappId)->first();
            if (!$employee) {
                Redis::set('whatsapp:ignore_self:' . $whatsappId, true, 'EX', 300);
                return response()->json([
                    'success' => false,
                    'message' => __('Employee not found'),
                ], 200);
            }
            if (!$employee->is_active) {
                Redis::set('whatsapp:ignore_self:' . $whatsappId, true, 'EX', 300);
                return response()->json([
                    'success' => false,
                    'message' => __('Employee is not active'),
                ], 200);
            }
            if (!$request->filled('media')) {
                Redis::set('whatsapp:ignore_self:' . $whatsappId, true, 'EX', 300);
                return response()->json([
                    'success' => false,
                    'message' => __('Media is required for attendance'),
                ], 200);
            }

            if (!$employee->foto_url && $request->filled('media')) {
                $mediaData = base64_decode($request->input('media'));
                $filename = 'employee_' . $employee->id . '_' . time() . '.jpg';
                Storage::disk('public')->put('employees/' . $filename, $mediaData);
                $employee->foto_url = $filename;
                $employee->save();
            }

            $redisKey = 'whatsapp:attendance:' . $employee->id . ':' . now()->toDateString();
            $session = Redis::get($redisKey);

            $data = [
                'whatsapp_id' => $whatsappId,
                'date' => now()->toDateString(),
                'clock_in' => null,
                'clock_out' => null,
                'note' => $request->input('message') ?: 'Via WhatsApp',
                'status' => null,
            ];

            if (!$session) {
                // Clock in
                $data['clock_in'] = now()->format('H:i');
                Redis::set($redisKey, 'clocked_in', 'EX', 86400);
            } else {
                // Clock out
                $data['clock_out'] = now()->format('H:i');
                Redis::del($redisKey);
            }

            return $this->store($data);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request',
                'errors' => $e->errors(),
            ], 500);
        }
    }
}
