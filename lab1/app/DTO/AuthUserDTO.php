<?php

namespace App\DTO;

/**
 * DTO для передачи информации об авторизованном пользователе
 */
class AuthUserDTO
{
    /**
     * Конструктор DTO пользователя
     *
     * @param int $id Идентификатор пользователя
     * @param string $username Имя пользователя
     * @param string $email Email пользователя
     * @param string $birthday Дата рождения
     */
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $email,
        public readonly string $birthday
    ) {}
}
