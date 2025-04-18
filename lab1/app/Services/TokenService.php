<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Сервис для генерации, валидации и отзыва токенов
 */
class TokenService
{
    /**
     * Генерирует токен доступа для пользователя
     * @param int $userId
     * @param int $ttlMinutes
     * @return string
     */
    public static function generateAccessToken(int $userId, int $ttlMinutes = 60): string
    {
        $payload = [
            'uid' => $userId,
            'type' => 'access',
            'exp' => Carbon::now()->addMinutes($ttlMinutes)->timestamp,
            'jti' => Str::uuid()->toString(),
        ];
        return Crypt::encryptString(json_encode($payload));
    }

    /**
     * Генерирует токен обновления для пользователя
     * @param int $userId
     * @param int $ttlMinutes
     * @return string
     */
    public static function generateRefreshToken(int $userId, int $ttlMinutes = 1440): string
    {
        $payload = [
            'uid' => $userId,
            'type' => 'refresh',
            'exp' => Carbon::now()->addMinutes($ttlMinutes)->timestamp,
            'jti' => Str::uuid()->toString(),
        ];
        return Crypt::encryptString(json_encode($payload));
    }

    /**
     * Валидация токена (проверка срока действия и структуры)
     * @param string $token
     * @param string $type
     * @return array|null
     */
    public static function validateToken(string $token, string $type = 'access'): ?array
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true);
        } catch (\Throwable) {
            return null;
        }
        if (!$payload || ($payload['type'] ?? null) !== $type) {
            return null;
        }
        return (($payload['exp'] ?? 0) >= Carbon::now()->timestamp) ? $payload : null;
    }

    /**
     * Извлекает user_id из токена
     * @param string $token
     * @return int|null
     */
    public static function extractUserId(string $token): ?int
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true);
        } catch (\Throwable) {
            return null;
        }
        return $payload['uid'] ?? null;
    }

    /**
     * Извлекает jti из токена
     * @param string $token
     * @return string|null
     */
    public static function extractJti(string $token): ?string
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true);
        } catch (\Throwable) {
            return null;
        }
        return $payload['jti'] ?? null;
    }

    /**
     * Проверяет валидность access token (структура и срок)
     * @param string $token
     * @return bool
     */
    public static function validateAccessToken(string $token): bool
    {
        return self::validateToken($token, 'access') !== null;
    }

    /**
     * Проверяет валидность refresh token (структура и срок)
     * @param string $token
     * @return bool
     */
    public static function validateRefreshToken(string $token): bool
    {
        return self::validateToken($token, 'refresh') !== null;
    }
}
