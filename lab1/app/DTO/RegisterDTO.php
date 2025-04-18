<?php

namespace App\DTO;

/**
 * DTO для передачи информации о результате регистрации пользователя
 */
class RegisterDTO
{
    /**
     * Конструктор DTO регистрации
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
