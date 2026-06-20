<?php

namespace App\Support;

use App\Models\Student;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use App\Support\ApiResponse;

class StudentScope
{
    public static function resolve(?User $user): ?Student
    {
        if (! $user) {
            return null;
        }

        return Student::where('user_id', $user->id)
            ->orWhere('email', $user->email)
            ->first();
    }

    public static function require(?User $user): Student|JsonResponse
    {
        $student = self::resolve($user);

        if (! $student) {
            return ApiResponse::error('Student profile not found.', 404);
        }

        return $student;
    }
}
