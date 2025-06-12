<?php

namespace App\Http\Controllers\Api\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenants\Attendance;
use App\Models\Tenants\Employee;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    private function store(array $data)
    {
        $validator = \Validator::make($data, [
            'whatsapp_id' => 'required|exists:employees,whatsapp_id',
            'date' => 'required|date',
            'clock_in' => 'required_without:clock_out|nullable|date_format:H:i',
            'clock_out' => 'required_without:clock_in|nullable|date_format:H:i|after:clock_in',
            'status' => 'required|string',
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
            $shift = null;
            if ($clockIn >= '06:00' && $clockIn <= '08:00') {
                $shift = 'pagi';
            } elseif ($clockIn >= '17:00' && $clockIn <= '19:00') {
                $shift = 'sore';
            } elseif ($clockIn >= '21:00' && $clockIn <= '23:00') {
                $shift = 'malam';
            }
            if (strtolower($employee->shift) !== $shift) {
                return response()->json([
                    'success' => false,
                    'message' => __('Sorry, you are not allowed to clock in at this time. Your shift is ' . $employee->shift),
                ], 200);
            }
            $attendance = Attendance::create(array_merge($validated, [
                'employee_id' => $employee->id,
                'shift' => $shift,
            ]));
            return response()->json([
                'success' => true,
                'message'    => 'attendance in successfully',
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
    }
    public function storeAttendance(Request $request)
    {
        try {
            $validated = $request->validate([
            'from' => ['required', 'string'],
            'participant' => ['required', 'string'],
            'message' => ['nullable', 'string'],
            'media' => ['nullable', 'string'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
            'success' => false,
            'message' => 'Invalid request',
            'errors' => $e->errors(),
            ], 422);
        }
        if ($request->input('from') !== config('app.whatsapp_id')) {
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
            $path = 'uploads/employee_photos/' . $filename;
            Storage::disk('public')->put($path, $mediaData);
            $employee->foto_url = 'storage/' . $path;
            $employee->save();
        }

        // Gunakan Redis key unik per user per hari
        $redisKey = 'whatsapp:attendance:' . $employee->id . ':' . now()->toDateString();
        $session = Redis::get($redisKey);

        $data = [
            'whatsapp_id' => $whatsappId,
            'date' => now()->toDateString(),
            'clock_in' => null,
            'clock_out' => null,
            'note' => $request->input('message') ?: 'Via WhatsApp',
            'status' => 'present',
        ];

        if (!$session) {
            // Clock in
            $data['clock_in'] = now()->format('H:i');
            Redis::set($redisKey, 'clocked_in');
        } else {
            // Clock out
            $data['clock_out'] = now()->format('H:i');
            Redis::del($redisKey); // hapus session setelah clock out
        }

        return $this->store($data);
    }
}
