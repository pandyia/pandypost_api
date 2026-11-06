<?php

use App\Enums\Exceptions\InstagramError;
use App\Exceptions\BaseException;
use App\Exceptions\InstagramException;
use App\Exceptions\ScheduledPostException;
use App\Services\Instagram\InstagramApi;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

const IG_SECRET_TOKEN = 'SEGREDO-TOKEN-IG-123';

beforeEach(fn () => Http::preventStrayRequests());

/** Cada chamada do cliente (menos permalink, que tem erro próprio), com a resposta de sucesso que ela espera. */
dataset('instagram api calls', [
    'containerStatus' => [fn (InstagramApi $api) => $api->containerStatus(IG_SECRET_TOKEN, 'c-1'), ['status_code' => 'FINISHED'], 'FINISHED'],
    'createContainer' => [fn (InstagramApi $api) => $api->createContainer(IG_SECRET_TOKEN, 'ig-1', ['caption' => 'x']), ['id' => 'c-2'], 'c-2'],
    'publish' => [fn (InstagramApi $api) => $api->publish(IG_SECRET_TOKEN, 'ig-1', 'c-1'), ['id' => 'm-1'], 'm-1'],
    'exchangeCode' => [fn (InstagramApi $api) => $api->exchangeCode('codigo')['user_id'], ['access_token' => 'a', 'user_id' => 'u-1'], 'u-1'],
    'profile' => [fn (InstagramApi $api) => $api->profile(IG_SECRET_TOKEN)['username'], ['id' => '1', 'username' => 'perfil'], 'perfil'],
    'longLivedToken' => [fn (InstagramApi $api) => $api->longLivedToken(IG_SECRET_TOKEN)['expires_in'], ['access_token' => 'l', 'expires_in' => 100], 100],
    'refreshToken' => [fn (InstagramApi $api) => $api->refreshToken(IG_SECRET_TOKEN)['access_token'], ['access_token' => 'novo', 'expires_in' => 100], 'novo'],
]);

describe('InstagramApi: sucesso', function () {

    it('devolve o dado esperado', function (Closure $call, array $body, mixed $expected) {
        Http::fake(['*' => Http::response($body)]);

        expect($call(new InstagramApi))->toBe($expected);
    })->with('instagram api calls');

    it('permalink devolve o link público', function () {
        Http::fake(['*' => Http::response(['permalink' => 'https://www.instagram.com/p/A/'])]);

        expect((new InstagramApi)->permalink('t', 'm-1'))->toBe('https://www.instagram.com/p/A/');
    });

    it('refreshToken assume 60 dias quando a Meta não manda expires_in', function () {
        Http::fake(['*' => Http::response(['access_token' => 'novo'])]);

        expect((new InstagramApi)->refreshToken('t')['expires_in'])->toBe(5184000);
    });

    it('publish tolera resposta sem id (devolve null)', function () {
        Http::fake(['*' => Http::response([])]);

        expect((new InstagramApi)->publish('t', 'ig-1', 'c-1'))->toBeNull();
    });

});

describe('InstagramApi: erros nunca vazam body nem token', function () {

    it('resposta de erro da Meta vira InstagramException em pt-BR, sem o texto cru', function (Closure $call) {
        Http::fake(['*' => Http::response(['error' => ['message' => 'Texto cru da Meta '.IG_SECRET_TOKEN, 'code' => 100, 'fbtrace_id' => 'TRACE']], 400)]);

        try {
            $call(new InstagramApi);
            $this->fail('deveria lançar');
        } catch (InstagramException $e) {
            expect($e)->toBeInstanceOf(BaseException::class)
                ->and($e->getMessage())->toBe(InstagramError::UNKNOWN->message())
                ->and($e->getMessage())->not->toContain('Texto cru')
                ->and($e->getMessage())->not->toContain(IG_SECRET_TOKEN);
        }
    })->with('instagram api calls');

    it('ConnectionException vira connectionFailed sem a URL (que traz o access_token) na mensagem nem no log', function (Closure $call) {
        Log::spy();
        Http::fake(['*' => fn () => throw new ConnectionException('cURL error 28: timed out for https://graph.instagram.com/v25.0/c-1?access_token='.IG_SECRET_TOKEN)]);

        try {
            $call(new InstagramApi);
            $this->fail('deveria lançar');
        } catch (InstagramException $e) {
            expect($e->getMessage())->toBe(InstagramError::API_UNAVAILABLE->message())
                ->and($e->getMessage())->not->toContain(IG_SECRET_TOKEN)
                ->and($e->isUnavailable())->toBeTrue();
        }

        Log::shouldHaveReceived('warning')->withArgs(fn (string $m) => ! str_contains($m, IG_SECRET_TOKEN))->once();
    })->with('instagram api calls');

    it('createContainer sem id na resposta é resposta inesperada', function () {
        Http::fake(['*' => Http::response(['foo' => 'bar'])]);

        expect(fn () => (new InstagramApi)->createContainer('t', 'ig-1', []))->toThrow(InstagramException::class, InstagramError::INVALID_RESPONSE->message());
    });

    it('permalink indisponível (erro, sem campo ou sem conexão) vira ScheduledPostException sem vazar', function (callable $fake) {
        Http::fake(['*' => $fake]);

        expect(fn () => (new InstagramApi)->permalink(IG_SECRET_TOKEN, 'm-1'))->toThrow(ScheduledPostException::class);
    })->with([
        'erro 500' => [fn () => Http::response(['error' => ['message' => 'x']], 500)],
        'sem permalink' => [fn () => Http::response(['id' => 'm-1'])],
        'sem conexão' => [fn () => throw new ConnectionException('timeout '.IG_SECRET_TOKEN)],
    ]);

});

describe('InstagramException::fromApiCode', function () {

    it('token inválido/permissão (10, 102, 190, 200-299) vira ACCESS_DENIED permanente', function (int $code) {
        $e = InstagramException::fromApiCode($code);

        expect($e->getMessage())->toBe(InstagramError::ACCESS_DENIED->message())
            ->and($e->isPermanent())->toBeTrue()
            ->and($e->getHttpCode())->toBe(409);
    })->with([10, 102, 190, 200, 283, 299]);

    it('qualquer outro código, ou nenhum, vira mensagem genérica não permanente', function (?int $code) {
        $e = InstagramException::fromApiCode($code);

        expect($e->getMessage())->toBe(InstagramError::UNKNOWN->message())
            ->and($e->isPermanent())->toBeFalse()
            ->and($e->isUnavailable())->toBeFalse();
    })->with([100, 4, 17, 613, 2207026, 300, null]);

    it('toda mensagem está em pt-BR e nenhuma repete o código cru', function () {
        foreach (InstagramError::cases() as $error) {
            expect($error->message())->toMatch('/[áàâãéêíóôõúç]|Instagram|tente/iu')->and($error->message())->not->toContain($error->value);
        }
    });

});
