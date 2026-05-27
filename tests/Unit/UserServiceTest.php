<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use App\Repositories\User\Contracts\UserRepositoryInterface;
use App\Services\User\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserService $userService;
    private $userRepositoryMock;
            protected function setUp(): void
            {
                parent::setUp();

                $this->userRepositoryMock = Mockery::mock(UserRepositoryInterface::class);

                $this->userService = new UserService(
                    $this->userRepositoryMock
                );
            }

    public function test_register_user_successfully(): void
    {
        $userData = [
            'national_id' => '1234567890',
            'national_id_country_code' => 'USA',
            'email' => 'test@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'password' => 'Password123!',
            'data_residency_region' => 'US',
        ];

        $this->userRepositoryMock
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                return isset($data['global_user_uuid']) &&
                       isset($data['national_id_hash']) &&
                       isset($data['email_hash']) &&
                       isset($data['password_hash']);
            }))
            ->andReturn(new User($userData));

        $user = $this->userService->register($userData);

        $this->assertInstanceOf(User::class, $user);
    }

    public function test_login_successfully(): void
    {
        $credentials = [
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ];

        $user = User::factory()->create([
            'email_hash' => hash('sha256', 'test@example.com'),
            'password_hash' => Hash::make('Password123!'),
            'failed_login_attempts' => 0,
        ]);

        $this->userRepositoryMock
            ->shouldReceive('findByEmailHash')
            ->with(hash('sha256', 'test@example.com'))
            ->once()
            ->andReturn($user);

        $this->userRepositoryMock
            ->shouldReceive('resetFailedAttempts')
            ->with($user)
            ->once()
            ->andReturn(true);

        $this->userRepositoryMock
            ->shouldReceive('updateLastLogin')
            ->with($user, '127.0.0.1', 'TestAgent')
            ->once()
            ->andReturn(true);

        $result = $this->userService->login($credentials, '127.0.0.1', 'TestAgent');

        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertInstanceOf(User::class, $result['user']);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid credentials');

        $credentials = [
            'email' => 'wrong@example.com',
            'password' => 'WrongPassword',
        ];

        $this->userRepositoryMock
            ->shouldReceive('findByEmailHash')
            ->with(hash('sha256', 'wrong@example.com'))
            ->once()
            ->andReturn(null);

        $this->userService->login($credentials, '127.0.0.1', 'TestAgent');
    }

    public function test_update_password_successfully(): void
    {
        $user = User::factory()->create([
            'password_hash' => Hash::make('OldPassword123!'),
        ]);

        $this->userRepositoryMock
            ->shouldReceive('findById')
            ->with($user->id)
            ->once()
            ->andReturn($user);

        $this->userRepositoryMock
            ->shouldReceive('update')
            ->with($user, Mockery::on(function ($data) {
                return Hash::check('NewPassword123!', $data['password_hash']);
            }))
            ->once()
            ->andReturn(true);

        $result = $this->userService->updatePassword(
            $user->id,
            'NewPassword123!',
            'OldPassword123!'
        );

        $this->assertTrue($result);
    }

    public function test_verify_identity_successfully(): void
    {
        $user = User::factory()->create(['identity_state' => 'pending']);
        $staffId = 1;
        $method = 'passport';

        $this->userRepositoryMock
            ->shouldReceive('findById')
            ->with($user->id)
            ->once()
            ->andReturn($user);

        $this->userRepositoryMock
            ->shouldReceive('update')
            ->with($user, [
                'identity_state' => 'verified',
                'identity_verified_at' => Mockery::type(\DateTimeInterface::class),
                'identity_verification_method' => $method,
                'identity_verified_by_staff_id' => $staffId,
            ])
            ->once()
            ->andReturn(true);

        $result = $this->userService->verifyIdentity($user->id, $staffId, $method);

        $this->assertInstanceOf(User::class, $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}