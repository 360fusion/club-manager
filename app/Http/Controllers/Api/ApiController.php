<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Pennant\Feature;

class ApiController extends Controller
{
    /**
     * Issue Sanctum API token for user authentication.
     */
    public function issueToken(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|max:255',
            'password' => 'required|string|max:255',
            'device_name' => 'required|string|max:100',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials provided.'], 401);
        }

        $token = $user->createToken($request->device_name, ['profile:read'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    /**
     * Get club details with Pennant feature flags.
     */
    public function getClubInfo(string $slug): JsonResponse
    {
        $club = Club::where('slug', $slug)->with(['clubType', 'membershipPlans'])->firstOrFail();

        return response()->json([
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
                'type' => $club->clubType->name,
                'custom_domain' => $club->custom_domain,
            ],
            'features' => [
                'dining_menu' => Feature::for($club)->active('3-course-dining'),
                'custom_domain' => Feature::for($club)->active('custom-domain'),
                'executive_analytics' => Feature::for($club)->active('executive-analytics'),
            ],
        ]);
    }
}
