<?php

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\StoreRoleRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| QA — mecânica $formRequests/validatedInput() do BaseController (spec 022, D1)
|--------------------------------------------------------------------------
|
| O contrato da refatoração: a base NUNCA volta ao $request->all() cru.
| Ação sem FormRequest declarado → LogicException clara em dev (não um
| passthrough silencioso); ação declarada → o dado só sai validado.
*/

function baseControllerWithFormRequests(array $formRequests): BaseController
{
    return new class($formRequests) extends BaseController
    {
        // Redeclarada para não escrever no estático compartilhado do BaseController.
        protected static array $formRequests = [];

        public function __construct(array $formRequests)
        {
            self::$formRequests = $formRequests;
            parent::__construct(new stdClass);
        }

        public function callValidatedInput(string $action): array
        {
            return $this->validatedInput($action);
        }
    };
}

describe('validatedInput() do BaseController', function () {

    it('lança LogicException quando a ação não tem FormRequest declarado', function () {
        $controller = baseControllerWithFormRequests([]);

        expect(fn () => $controller->callValidatedInput('store'))
            ->toThrow(LogicException::class, "Nenhum FormRequest declarado para a ação 'store'");
    });

    it('não cai no request cru: ação declarada passa pela validação do FormRequest', function () {
        $controller = baseControllerWithFormRequests(['store' => StoreRoleRequest::class]);

        // Sem payload válido no request corrente, a validação precisa barrar (nunca devolver cru).
        expect(fn () => $controller->callValidatedInput('store'))
            ->toThrow(ValidationException::class);
    });
});
