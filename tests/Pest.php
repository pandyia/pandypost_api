<?php

use App\Models\Access;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function signupUser(array $overrides = []): array
{
    $data = array_merge([
        'name' => 'Teste User',
        'email' => 'teste@gmail.com',
        'password' => '12345678G',
        'password_confirmation' => '12345678G',
    ], $overrides);

    $response = test()->postJson('/api/auth/signup', $data);

    return [
        'response' => $response,
        'user' => User::where('email', $data['email'])->first(),
        'token' => $response->json('token'),
    ];
}

function loginAs(User $user, string $password = 'password'): string
{
    $response = test()->postJson('/api/login', [
        'email' => $user->email,
        'password' => $password,
    ]);

    return $response->json('token');
}

// ---------------------------------------------------------------------------
// Helpers compartilhados entre testes
// ---------------------------------------------------------------------------

function permission(string $name): Permission
{
    return Permission::firstOrCreate(
        ['name' => $name],
        ['name' => $name, 'description' => $name]
    );
}

/**
 * Cria um usuário verificado com workspace, role e permissões.
 */
function createUserWithPermissions(array $permissionNames = [], bool $isPersonalTeam = true, ?Workspace $workspace = null): User
{
    $user = User::factory()->create();

    $workspace ??= Workspace::withoutGlobalScopes()->create([
        'name' => 'Test Workspace',
        'is_personal_team' => $isPersonalTeam,
    ]);

    $role = Role::withoutGlobalScopes()->create([
        'name' => 'Test Role ' . uniqid(),
        'workspace_id' => $workspace->id,
    ]);

    if (!empty($permissionNames)) {
        foreach ($permissionNames as $name) {
            permission($name);
        }
        $ids = Permission::whereIn('name', $permissionNames)->pluck('id');
        $role->permissions()->sync($ids);
    }

    $access = Access::create([
        'user_id' => $user->id,
        'role_id' => $role->id,
        'workspace_id' => $workspace->id,
    ]);

    $user->update(['access_id' => $access->id]);
    $user->test_token = $user->createToken('test')->plainTextToken;

    return $user;
}

function createUserWithoutPermissions(): User
{
    return createUserWithPermissions([]);
}

/**
 * Adiciona um usuário a um workspace existente.
 */
function addUserToWorkspace(Workspace $workspace, ?User $user = null): User
{
    $user ??= User::factory()->create();

    $role = Role::withoutGlobalScopes()->create([
        'name' => 'Member Role ' . uniqid(),
        'workspace_id' => $workspace->id,
    ]);

    $access = Access::create([
        'user_id' => $user->id,
        'role_id' => $role->id,
        'workspace_id' => $workspace->id,
    ]);

    $user->update(['access_id' => $access->id]);

    return $user;
}

