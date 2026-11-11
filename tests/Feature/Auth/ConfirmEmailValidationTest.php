<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Caracterização — POST /auth/confirm-email sem token (spec 022, task 0.2)
|--------------------------------------------------------------------------
|
| Trava o 422 de validação antes da troca do $request->validate() inline
| pelo ConfirmEmailRequest (task 1.1). O caminho feliz já está coberto em
| AuthenticationTest.
*/

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

describe('validação do confirm-email', function () {

    it('retorna 422 quando o token não é enviado', function () {
        $this->postJson('/api/auth/confirm-email', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    });
});
