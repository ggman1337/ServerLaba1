<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Сервис для хранения и управления jti токенов пользователя через файловый кэш Laravel
 */
class UserTokenCacheService
{
    /**
     * Ключ для хранения токенов пользователя
     */
    protected static function cacheKey(int $userId): string
    {
        return 'user_tokens_' . $userId;
    }

    /**
     * Получить список jti активных токенов пользователя
     * @param int $userId
     * @return array
     */
    public static function getUserTokens(int $userId): array
    {
        return Cache::get(self::cacheKey($userId), []);
    }

    /**
     * Добавить новый jti токена пользователя (с учётом лимита)
     * @param int $userId
     * @param string $jti
     * @param int $maxTokens
     * @param int $ttlSeconds
     */
    public static function addUserToken(int $userId, string $jti, int $maxTokens, int $ttlSeconds): void
    {
        $tokens = self::getUserTokens($userId);
        // Записываем время создания токена с микросекундами для корректной сортировки
        $tokens[$jti] = microtime(true);
        // Оставляем только последние maxTokens токенов
        if (count($tokens) > $maxTokens) {
            // Сортируем по времени создания, оставляем новые
            asort($tokens);
            $tokens = array_slice($tokens, -$maxTokens, $maxTokens, true);
        }
        Cache::put(self::cacheKey($userId), $tokens, $ttlSeconds);
    }

    /**
     * Проверить, активен ли токен (jti) у пользователя
     * @param int $userId
     * @param string $jti
     * @return bool
     */
    public static function isTokenActive(int $userId, string $jti): bool
    {
        $tokens = self::getUserTokens($userId);
        return isset($tokens[$jti]);
    }

    /**
     * Отозвать токен (удалить jti)
     * @param int $userId
     * @param string $jti
     */
    public static function revokeUserToken(int $userId, string $jti): void
    {
        $tokens = self::getUserTokens($userId);
        unset($tokens[$jti]);
        Cache::put(self::cacheKey($userId), $tokens);
    }

    /**
     * Отозвать все токены пользователя
     * @param int $userId
     */
    public static function revokeAllUserTokens(int $userId): void
    {
        Cache::forget(self::cacheKey($userId));
    }
}
