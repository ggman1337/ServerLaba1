<?php

namespace App\DTO;

/**
 * DTO для передачи информации о результате авторизации пользователя
 */
class LoginDTO
{
    /**
     * Конструктор DTO авторизации
     *
     * @param string $accessToken Токен доступа
     * @param string $refreshToken Токен обновления
     * @param int $expiresIn Время жизни токена в секундах
     */
    public function __construct(
        public readonly string $accessToken,
        public readonly string $refreshToken,
        public readonly int $expiresIn
    ) {}
}
