<?php

namespace App\Http\Controllers\Api\Tenants;
use App\Http\Controllers\Controller;
use App\Models\Tenants\Employee;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EmployeeController extends Controller
{
    public function getEmployees(Request $request)
    {
        try {
            $validated = $request->validate([
                'whatsapp_id' => 'required|exists:employees,whatsapp_id',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
            ], 422);
        }

        $employee = Employee::where('whatsapp_id', $validated['whatsapp_id'])->where('is_active', true)->first();
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => __('Employee not found'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $employee,
        ]);
    }
}