<?php
namespace App\Http\Middleware;

// ─────────────────────────────────────────────
//  JwtMiddleware  –  verifies Bearer token via tymon/jwt-auth
// ─────────────────────────────────────────────
class JwtMiddleware {
    public function handle($request, \Closure $next) {
        try {
            $user = \Tymon\JWTAuth\Facades\JWTAuth::parseToken()->authenticate();
            if (!$user || !$user->is_active) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json(['message' => 'Token expired'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json(['message' => 'Token invalid'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['message' => 'Token absent'], 401);
        }
        return $next($request);
    }
}

// ─────────────────────────────────────────────
//  AdminMiddleware  –  allows only municipality_admin
// ─────────────────────────────────────────────
class AdminMiddleware {
    public function handle($request, \Closure $next) {
        $user = auth()->user();
        if (!$user || !in_array($user->role, ['municipality_admin', 'ward_admin'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        return $next($request);
    }
}

// ─────────────────────────────────────────────
//  WardAdminMiddleware  –  ward_admin or municipality_admin
// ─────────────────────────────────────────────
class WardAdminMiddleware {
    public function handle($request, \Closure $next) {
        $user = auth()->user();
        if (!$user || $user->role === 'citizen') {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        return $next($request);
    }
}
