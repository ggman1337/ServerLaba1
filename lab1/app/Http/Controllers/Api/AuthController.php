<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\DTO\AuthUserDTO;
use App\DTO\LoginDTO;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Services\TokenService;
use App\Services\UserTokenCacheService;

class AuthController extends Controller
{
    // Все комментарии в этом контроллере будут на русском языке
    //
    /**
     * Метод регистрации пользователя
     * @param RegisterRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(RegisterRequest $request)
    {
        // Проверяем, что пользователь с таким username или email не существует (без учёта регистра)
        $username = $request->input('username');
        $email = $request->input('email');
        $birthday = $request->input('birthday');
        $password = $request->input('password');

        // Создаём пользователя
        $user = User::create([
            'username' => $username,
            'email' => $email,
            'birthday' => $birthday,
            'password' => Hash::make($password),
        ]);

        // DTO для возврата
        $dto = $request->toResource($user);

        // Возвращаем успешный ответ
        return response()->json($dto, 201);
    }

    /**
     * Метод авторизации пользователя
     * @param LoginRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(LoginRequest $request)
    {
        // Получаем пользователя по username (без учёта регистра)
        $username = $request->input('username');
        $password = $request->input('password');
        $user = User::whereRaw('LOWER(username) = ?', [mb_strtolower($username)])->first();
        if (!$user || !Hash::check($password, $user->password)) {
            return response()->json(['message' => 'Неверный логин или пароль'], 422);
        }

        // Получаем параметры из конфига/окружения
        $ttl = (int) (env('TOKEN_TTL_MINUTES', 60));
        $refreshTtl = (int) (env('REFRESH_TOKEN_TTL_MINUTES', 1440));
        $maxTokens = (int) (env('MAX_USER_TOKENS', 5));

        // Генерируем токены
        $accessToken = TokenService::generateAccessToken($user->id, $ttl);
        $refreshToken = TokenService::generateRefreshToken($user->id, $refreshTtl);
        $accessJti = TokenService::extractJti($accessToken);
        $refreshJti = TokenService::extractJti($refreshToken);

        // Добавляем jti токенов в кэш активных токенов пользователя
        UserTokenCacheService::addUserToken($user->id, $accessJti, $maxTokens, $ttl * 60);
        UserTokenCacheService::addUserToken($user->id, $refreshJti, $maxTokens, $refreshTtl * 60);

        $dto = $request->toResource($accessToken, $refreshToken, $ttl * 60);
        return response()->json($dto, 200);
    }

    /**
     * Получение информации об авторизованном пользователе
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request)
    {
        // Получаем user_id из атрибутов запроса (установлен в middleware)
        $userId = $request->attributes->get('user_id');
        if (!$userId) {
            return response()->json(['message' => 'Пользователь не найден'], 401);
        }
        $user = User::find($userId);
        if (!$user) {
            return response()->json(['message' => 'Пользователь не найден'], 404);
        }
        $dto = new AuthUserDTO($user->id, $user->username, $user->email, $user->birthday);
        return response()->json($dto);
    }

    /**
     * Разлогирование пользователя (отзыв текущего токена)
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        // Получаем access token из заголовка Authorization
        $token = $this->extractToken($request);
        if (!$token) {
            return response()->json(['message' => 'Токен не найден'], 401);
        }
        $userId = TokenService::extractUserId($token);
        $jti = TokenService::extractJti($token);
        if (!$userId || !$jti) {
            return response()->json(['message' => 'Некорректный токен'], 401);
        }
        // Получаем все jti токенов перед отзывом
        $tokens = UserTokenCacheService::getUserTokens($userId);
        asort($tokens);
        $jtiKeys = array_keys($tokens);
        $index = array_search($jti, $jtiKeys, true);
        if ($index !== false) {
            // Отзываем access jti
            UserTokenCacheService::revokeUserToken($userId, $jti);
            // Определяем парный refresh jti: следующий или предыдущий в списке
            $pairedRefreshJti = null;
            if (isset($jtiKeys[$index + 1])) {
                $pairedRefreshJti = $jtiKeys[$index + 1];
            } elseif (isset($jtiKeys[$index - 1])) {
                $pairedRefreshJti = $jtiKeys[$index - 1];
            }
            if ($pairedRefreshJti) {
                UserTokenCacheService::revokeUserToken($userId, $pairedRefreshJti);
            }
        }
        return response()->json(['message' => 'Выход выполнен успешно']);
    }

    /**
     * Разлогирование всех токенов пользователя
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logoutAll(Request $request)
    {
        // Получаем access token из заголовка Authorization
        $token = $this->extractToken($request);
        if (!$token) {
            return response()->json(['message' => 'Токен не найден'], 401);
        }
        $userId = TokenService::extractUserId($token);
        if (!$userId) {
            return response()->json(['message' => 'Некорректный токен'], 401);
        }
        UserTokenCacheService::revokeAllUserTokens($userId);
        return response()->json(['message' => 'Все токены отозваны']);
    }

    /**
     * Получение списка токенов авторизованного пользователя
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function tokens(Request $request)
    {
        // Получаем access token из заголовка Authorization
        $token = $this->extractToken($request);
        if (!$token) {
            return response()->json(['message' => 'Токен не найден'], 401);
        }
        $userId = TokenService::extractUserId($token);
        if (!$userId) {
            return response()->json(['message' => 'Некорректный токен'], 401);
        }
        $tokens = UserTokenCacheService::getUserTokens($userId);
        return response()->json(['tokens' => array_keys($tokens)]);
    }

    /**
     * Обновление токена доступа
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh(Request $request)
    {
        // Получаем refresh token из заголовка Authorization
        $refreshToken = $this->extractToken($request);
        if (!$refreshToken) {
            return response()->json(['message' => 'Требуется refresh token'], 401);
        }
        // Проверяем валидность refresh token
        if (!TokenService::validateRefreshToken($refreshToken)) {
            return response()->json(['message' => 'Недействительный или истёкший refresh token'], 401);
        }
        $userId = TokenService::extractUserId($refreshToken);
        $refreshJti = TokenService::extractJti($refreshToken);
        if (!$userId || !$refreshJti) {
            return response()->json(['message' => 'Некорректный refresh token'], 401);
        }
        // Проверяем, что этот refresh jti активен
        if (!UserTokenCacheService::isTokenActive($userId, $refreshJti)) {
            // При повторном использовании refresh-токена отзываем все токены
            UserTokenCacheService::revokeAllUserTokens($userId);
            return response()->json(['message' => 'Refresh token отозван или не найден'], 401);
        }
        // Определяем парный access jti для отзыва
        $tokens = UserTokenCacheService::getUserTokens($userId);
        asort($tokens);
        $jtiKeys = array_keys($tokens);
        $pairedAccessJti = null;
        $index = array_search($refreshJti, $jtiKeys, true);
        if ($index !== false && $index > 0) {
            $pairedAccessJti = $jtiKeys[$index - 1];
        }
        // Генерируем новые токены
        $ttl = (int) (env('TOKEN_TTL_MINUTES', 60));
        $refreshTtl = (int) (env('REFRESH_TOKEN_TTL_MINUTES', 1440));
        $maxTokens = (int) (env('MAX_USER_TOKENS', 6));
        $accessToken = TokenService::generateAccessToken($userId, $ttl);
        $newRefreshToken = TokenService::generateRefreshToken($userId, $refreshTtl);
        $accessJti = TokenService::extractJti($accessToken);
        $newRefreshJti = TokenService::extractJti($newRefreshToken);
        // Отзываем старый refresh jti
        UserTokenCacheService::revokeUserToken($userId, $refreshJti);
        // Отзываем парный старый access jti, если найден
        if ($pairedAccessJti) {
            UserTokenCacheService::revokeUserToken($userId, $pairedAccessJti);
        }
        // Сохраняем новые jti
        UserTokenCacheService::addUserToken($userId, $accessJti, $maxTokens, $ttl * 60);
        UserTokenCacheService::addUserToken($userId, $newRefreshJti, $maxTokens, $refreshTtl * 60);
        $dto = new LoginDTO($accessToken, $newRefreshToken, $ttl * 60);
        return response()->json($dto, 200);
    }

    /**
     * Изменение пароля пользователя с подтверждением текущего пароля
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changePassword(Request $request)
    {
        // Получаем user_id из атрибутов запроса (middleware)
        $userId = $request->attributes->get('user_id');
        if (!$userId) {
            return response()->json(['message' => 'Пользователь не найден'], 401);
        }
        $user = User::find($userId);
        if (!$user) {
            return response()->json(['message' => 'Пользователь не найден'], 404);
        }
        // Получаем старый и новый пароли из запроса
        $oldPassword = $request->input('old_password');
        $newPassword = $request->input('new_password');
        if (!$oldPassword || !$newPassword) {
            return response()->json(['message' => 'Необходимо указать старый и новый пароли'], 400);
        }
        // Проверяем старый пароль
        if (!Hash::check($oldPassword, $user->password)) {
            return response()->json(['message' => 'Старый пароль неверен'], 422);
        }
        // Валидация нового пароля по требованиям: минимум 8 символов, цифра, заглавная и строчная буквы, спецсимвол
        $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d]).{8,}$/';
        if (!preg_match($pattern, $newPassword)) {
            return response()->json([
                'message' => 'Новый пароль должен быть минимум 8 символов, содержать цифру, заглавную и строчную буквы и специальный символ'
            ], 422);
        }
        // Меняем пароль
        $user->password = Hash::make($newPassword);
        $user->save();
        // Отзываем все токены пользователя
        UserTokenCacheService::revokeAllUserTokens($userId);
        return response()->json(['message' => 'Пароль успешно изменён. Все сессии завершены, выполните вход заново.']);
    }

    /**
     * Вспомогательный метод для извлечения access token из заголовка Authorization
     */
    protected function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization');
        if (!$header || !preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
            return null;
        }
        return $matches[1];
    }
}
