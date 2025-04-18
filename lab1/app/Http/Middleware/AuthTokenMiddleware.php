<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\TokenService;
use App\Services\UserTokenCacheService;
use Illuminate\Support\Facades\Log;

/**
 * Middleware для проверки access token в заголовке Authorization
 */
class AuthTokenMiddleware
{
    /**
     * Обработка входящего запроса
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            $header = $request->header('Authorization');
            if (!$header || !preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
                return response()->json(['message' => 'Требуется авторизация'], 401);
            }
            $token = $matches[1];
            $userId = TokenService::extractUserId($token);
            $jti = TokenService::extractJti($token);
            if (!$userId || !$jti) {
                return response()->json(['message' => 'Некорректный токен'], 401);
            }
            if (!TokenService::validateAccessToken($token)) {
                return response()->json(['message' => 'Недействительный или истёкший токен'], 401);
            }
            if (!UserTokenCacheService::isTokenActive($userId, $jti)) {
                return response()->json(['message' => 'Токен отозван или не найден'], 401);
            }
            // Добавляем пользователя в request для дальнейшего использования
            $request->attributes->set('user_id', $userId);
            return $next($request);
        } catch (\Exception $e) {
            Log::error('Auth middleware error: ' . $e->getMessage());
            return response()->json(['message' => 'Ошибка аутентификации'], 500);
        }
    }
}
