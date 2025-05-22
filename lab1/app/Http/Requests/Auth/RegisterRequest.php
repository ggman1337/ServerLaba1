<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

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
                'regex:/^[A-Z][a-zA-Z0-9]+$/',
                // Проверка уникальности логина без учёта регистра
                function($attribute, $value, $fail) {
                    if (DB::table('users')
                        ->whereRaw('LOWER(username) = ?', [mb_strtolower($value)])
                        ->exists()) {
                        $fail('Дублирование логина');
                    }
                },
            ],
            // Email: корректный, уникальный без учёта регистра
            'email' => [
                'required',
                'email',
                // Проверка уникальности email без учёта регистра
                function($attribute, $value, $fail) {
                    if (DB::table('users')
                        ->whereRaw('LOWER(email) = ?', [mb_strtolower($value)])
                        ->exists()) {
                        $fail('Дублирование email');
                    }
                },
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
     * Сообщения об ошибках валидации
     * @return array
     */
    public function messages(): array
    {
        return [
            'username.required' => 'Логин обязателен',
            'username.min' => 'Логин должен быть минимум 7 символов',
            'username.regex' => 'Неверный формат логина',
            'username.0' => 'Дублирование логина',

            'email.required' => 'Email обязателен',
            'email.email' => 'Некорректный формат email',
            'email.0' => 'Дублирование email',

            'password.required' => 'Пароль обязателен',
            'password.min' => 'Пароль должен быть минимум 8 символов',
            'password.regex' => 'Неверный пароль',

            'c_password.same' => 'Пароли не совпадают',

            'birthday.required' => 'Дата рождения обязательна',
            'birthday.date_format' => 'Неверный формат даты рождения',
            'birthday.before_or_equal' => 'Возраст менее 14 лет',
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

    /**
     * Обработка неудачной валидации: возвращаем первую ошибку в message
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        $first = $validator->errors()->first();
        $response = response()->json([
            'message' => $first,
        ], 422);

        throw new \Illuminate\Http\Exceptions\HttpResponseException($response);
    }
}
