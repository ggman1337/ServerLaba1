<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Date;

class RegisterRequest extends FormRequest
{
    /**
     * Определяет, авторизован ли пользователь для выполнения этого запроса.
     * Возвращает true, чтобы разрешить доступ к регистрации.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Правила валидации для регистрации пользователя.
     *
     * @return array Правила валидации
     */
    public function rules(): array
    {
        // Проверка возраста
        $minBirthday = now()->subYears(14)->format('Y-m-d');
        return [
            // Имя пользователя: только латиница, с большой буквы, минимум 7 символов, уникальное (без учёта регистра)
            'username' => [
                'required',
                'string',
                'min:7',
                'regex:/^[A-Z][a-zA-Z]+$/',
                Rule::unique('users', 'username')->where(function ($query) {
                    $query->whereRaw('LOWER(username) = ?', [strtolower($this->input('username'))]);
                }),
            ],
            // Email: корректный, уникальный
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email'),
            ],
            // Пароль: минимум 8 символов, 1 цифра, 1 спецсимвол, по 1 букве в верхнем и нижнем регистре
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d]).+$/',
            ],
            // Подтверждение пароля
            'c_password' => [
                'required',
                'same:password',
            ],            
            // Дата рождения: формат, возраст не менее 14 лет
            'birthday' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:' . $minBirthday,
            ],
        ];
    }

    /**
     * Возвращает DTO регистрации пользователя
     */
    public function toResource(object $user): \App\DTO\RegisterDTO
    {
        return new \App\DTO\RegisterDTO(
            $user->id,
            $user->username,
            $user->email,
            $user->birthday
        );
    }
}
