<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_login_tokens_and_refresh_flow(): void
    {
        // Регистрация
        $payload = [
            'username' => 'TestUser',
            'email' => 'test@example.com',
            'birthday' => '1990-01-01',
            'password' => 'Password1!',
            'c_password' => 'Password1!'
        ];
        $this->postJson('/api/auth/register', $payload)
             ->assertStatus(201)
             ->assertJsonFragment(['username' => 'TestUser', 'email' => 'test@example.com']);

        // Логин
        $login = $this->postJson('/api/auth/login', [
            'username' => 'TestUser',
            'password' => 'Password1!'
        ]);
        $login->assertStatus(200)
              ->assertJsonStructure(['accessToken', 'refreshToken', 'expiresIn']);

        $access = $login->json('accessToken');
        $refresh = $login->json('refreshToken');

        // Получение списка JTI
        $this->withHeaders(['Authorization' => "Bearer $access"])
             ->getJson('/api/auth/tokens')
             ->assertStatus(200)
             ->assertJsonStructure(['tokens']);

        // Refresh
        $refreshResp = $this->withHeaders(['Authorization' => "Bearer $refresh"])
                            ->postJson('/api/auth/refresh');
        $refreshResp->assertStatus(200)
                    ->assertJsonStructure(['accessToken', 'refreshToken', 'expiresIn']);
    }

    public function test_protected_route_requires_auth(): void
    {
        $this->getJson('/api/auth/me')
             ->assertStatus(401)
             ->assertExactJson(['message' => 'Требуется авторизация']);
    }

    public function test_me_endpoint_returns_user_data_with_valid_token(): void
    {
        $user = User::create([
            'username' => 'TestUser',
            'email' => 'test@example.com',
            'birthday' => '1990-01-01',
            'password' => Hash::make('Password1!')
        ]);
        $login = $this->postJson('/api/auth/login', ['username' => 'TestUser', 'password' => 'Password1!']);
        $access = $login->json('accessToken');

        $this->withHeaders(['Authorization' => "Bearer $access"])
             ->getJson('/api/auth/me')
             ->assertStatus(200)
             ->assertJsonFragment(['username' => 'TestUser', 'email' => 'test@example.com']);
    }

    public function test_out_revokes_tokens(): void
    {
        $user = User::create([
            'username' => 'TestUser',
            'email' => 'test@example.com',
            'birthday' => '1990-01-01',
            'password' => Hash::make('Password1!')
        ]);
        $login = $this->postJson('/api/auth/login', ['username' => 'TestUser', 'password' => 'Password1!']);
        $access = $login->json('accessToken');
        $this->withHeaders(['Authorization' => "Bearer $access"])
             ->postJson('/api/auth/out')
             ->assertStatus(200)
             ->assertExactJson(['message' => 'Выход выполнен успешно']);
        $this->withHeaders(['Authorization' => "Bearer $access"])
             ->getJson('/api/auth/me')
             ->assertStatus(401)
             ->assertExactJson(['message' => 'Токен отозван или не найден']);
    }

    public function test_out_all_revokes_all_tokens(): void
    {
        $user = User::create([
            'username' => 'TestUser',
            'email' => 'test@example.com',
            'birthday' => '1990-01-01',
            'password' => Hash::make('Password1!')
        ]);
        $login = $this->postJson('/api/auth/login', ['username' => 'TestUser', 'password' => 'Password1!']);
        $access = $login->json('accessToken');
        $this->withHeaders(['Authorization' => "Bearer $access"])
             ->postJson('/api/auth/out_all')
             ->assertStatus(200)
             ->assertExactJson(['message' => 'Все токены отозваны']);
        $this->withHeaders(['Authorization' => "Bearer $access"])
             ->getJson('/api/auth/me')
             ->assertStatus(401)
             ->assertExactJson(['message' => 'Токен отозван или не найден']);
    }

    public function test_change_password_successful(): void
    {
        $user = User::create([
            'username' => 'TestUser',
            'email' => 'test@example.com',
            'birthday' => '1990-01-01',
            'password' => Hash::make('Password1!')
        ]);
        $login = $this->postJson('/api/auth/login', ['username' => 'TestUser', 'password' => 'Password1!']);
        $access = $login->json('accessToken');
        $this->withHeaders(['Authorization' => "Bearer $access"])
             ->postJson('/api/auth/change-password', [
                 'old_password' => 'Password1!',
                 'new_password' => 'NewPass1!'
             ])
             ->assertStatus(200)
             ->assertExactJson(['message' => 'Пароль успешно изменён. Все сессии завершены, выполните вход заново.']);
        $this->withHeaders(['Authorization' => "Bearer $access"])
             ->getJson('/api/auth/me')
             ->assertStatus(401)
             ->assertExactJson(['message' => 'Токен отозван или не найден']);
        $this->postJson('/api/auth/login', ['username' => 'TestUser', 'password' => 'Password1!'])
             ->assertStatus(422);
        $this->postJson('/api/auth/login', ['username' => 'TestUser', 'password' => 'NewPass1!'])
             ->assertStatus(200)
             ->assertJsonStructure(['accessToken', 'refreshToken', 'expiresIn']);
    }

    public function test_change_password_invalid_old_password(): void
    {
        $user = User::create([
            'username' => 'TestUser',
            'email' => 'test@example.com',
            'birthday' => '1990-01-01',
            'password' => Hash::make('Password1!')
        ]);
        $login = $this->postJson('/api/auth/login', ['username' => 'TestUser', 'password' => 'Password1!']);
        $access = $login->json('accessToken');
        $this->withHeaders(['Authorization' => "Bearer $access"])
             ->postJson('/api/auth/change-password', [
                 'old_password' => 'WrongPass!',
                 'new_password' => 'NewPass1!'
             ])
             ->assertStatus(422)
             ->assertExactJson(['message' => 'Старый пароль неверен']);
    }

    public function test_change_password_invalid_new_password(): void
    {
        $user = User::create([
            'username' => 'TestUser',
            'email' => 'test@example.com',
            'birthday' => '1990-01-01',
            'password' => Hash::make('Password1!')
        ]);
        $login = $this->postJson('/api/auth/login', ['username' => 'TestUser', 'password' => 'Password1!']);
        $access = $login->json('accessToken');
        $this->withHeaders(['Authorization' => "Bearer $access"])
             ->postJson('/api/auth/change-password', [
                 'old_password' => 'Password1!',
                 'new_password' => 'short'
             ])
             ->assertStatus(422)
             ->assertExactJson(['message' => 'Новый пароль должен быть минимум 8 символов, содержать цифру, заглавную и строчную буквы и специальный символ']);
    }
}
