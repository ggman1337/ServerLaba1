<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * Определяет, авторизован ли пользователь для выполнения этого запроса.
     * Возвращает true, чтобы разрешить доступ к авторизации.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Правила валидации для авторизации пользователя.
     *
     * @return array Правила валидации
     */
    public function rules(): array
    {
        return [
            // Имя пользователя: только латиница, с большой буквы, минимум 7 символов
            'username' => [
                'required',
                'string',
                'min:7',
                'regex:/^[A-Z][a-zA-Z]+$/',
            ],
            // Пароль: минимум 8 символов, 1 цифра, 1 спецсимвол, по 1 букве в верхнем и нижнем регистре
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d]).+$/',
            ],
        ];
    }

    /**
     * Возвращает DTO авторизации пользователя
     */
    public function toResource(string $accessToken, string $refreshToken, int $expiresIn): \App\DTO\LoginDTO
    {
        return new \App\DTO\LoginDTO(
            $accessToken,
            $refreshToken,
            $expiresIn
        );
    }
}
