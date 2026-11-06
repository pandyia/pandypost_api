<?php

use App\Enums\Platform;
use App\Models\ScheduledPost;
use App\Services\Factories\PayloadBuilderFactory;
use App\Services\Payloads\Builders\GenericPayloadBuilder;
use App\Services\Payloads\Builders\TikTokPayloadBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

// ASSUNÇÃO (ver perguntas do relatório): os campos do TikTok chegam no corpo do request com os nomes
// da spec, sem prefixo (como os youtube_* chegam hoje), e o builder os move para `payload`.

function tiktokBuild(array $input): array
{
    return app(PayloadBuilderFactory::class)->make(Platform::TIKTOK)->build($input)->payload();
}

describe('TikTokPayloadBuilder (RF-4 / D5)', function () {

    it('está registrado na PayloadBuilderFactory e não é mais o builder genérico', function () {
        $builder = app(PayloadBuilderFactory::class)->make(Platform::TIKTOK);

        expect($builder)->toBeInstanceOf(TikTokPayloadBuilder::class)
            ->and(get_parent_class($builder))->not->toBe(GenericPayloadBuilder::class);
    });

    it('copia privacy_level, disable_* e toggles de marca para o payload', function () {
        $payload = tiktokBuild([
            'privacy_level' => 'PUBLIC_TO_EVERYONE',
            'disable_comment' => true,
            'disable_duet' => false,
            'disable_stitch' => true,
            'brand_content_toggle' => true,
            'brand_organic_toggle' => false,
            'title' => 'x',
        ]);

        expect($payload)->toMatchArray([
            'privacy_level' => 'PUBLIC_TO_EVERYONE',
            'disable_comment' => true,
            'disable_duet' => false,
            'disable_stitch' => true,
            'brand_content_toggle' => true,
            'brand_organic_toggle' => false,
        ]);
    });

    it('privacy_level é obrigatório e não tem valor padrão', function () {
        expect(fn () => tiktokBuild(['title' => 'sem privacidade']))->toThrow(ValidationException::class);
    });

    it('rejeita privacy_level que não existe no TikTok', function () {
        expect(fn () => tiktokBuild(['privacy_level' => 'TODO_MUNDO']))->toThrow(ValidationException::class);
    });

    it('conteúdo de marca não pode ser SELF_ONLY', function () {
        expect(fn () => tiktokBuild(['privacy_level' => 'SELF_ONLY', 'brand_content_toggle' => true]))
            ->toThrow(ValidationException::class);
    });

    it('conteúdo de marca com privacidade pública é aceito', function () {
        expect(tiktokBuild(['privacy_level' => 'PUBLIC_TO_EVERYONE', 'brand_content_toggle' => true]))
            ->toMatchArray(['brand_content_toggle' => true]);
    });

    it('divulgação orgânica (brand_organic_toggle) com SELF_ONLY é aceita: a regra é só do conteúdo de marca', function () {
        expect(tiktokBuild(['privacy_level' => 'SELF_ONLY', 'brand_organic_toggle' => true]))
            ->toMatchArray(['privacy_level' => 'SELF_ONLY', 'brand_organic_toggle' => true]);
    });

    it('o erro de validação aponta o campo privacy_level', function () {
        try {
            tiktokBuild(['title' => 'x']);
            $this->fail('deveria lançar ValidationException');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('privacy_level');
        }
    });

});

describe('POST /api/scheduled-posts com conta TikTok', function () {

    beforeEach(function () {
        $this->withoutMiddleware(ThrottleRequests::class);
        Queue::fake();
        Http::fake();
        $this->user = createUserWithPermissions(['posts.create']);
        // Com o escopo de publicação: sem ele o agendamento dá 422 (spec 019, D2).
        $this->account = connectedSocialAccount($this->user, 'tiktok', ['scopes' => TIKTOK_SCOPES_OK]);
        $this->media = tiktokMedia($this->user);
    });

    function tiktokStoreBody(array $extra = []): array
    {
        return array_merge([
            'media_storage_path' => test()->media,
            'social_account_uuids' => [test()->account->uuid],
            'title' => 'Meu TikTok',
            'caption' => 'Legenda',
        ], $extra);
    }

    it('cria o post com o payload do TikTok validado (201)', function () {
        $this->withToken($this->user->test_token)->postJson('/api/scheduled-posts', tiktokStoreBody([
            'privacy_level' => 'SELF_ONLY',
            'disable_comment' => true,
        ]))->assertCreated();

        $post = ScheduledPost::withoutGlobalScopes()->firstOrFail();
        expect($post->payload)->toMatchArray(['privacy_level' => 'SELF_ONLY', 'disable_comment' => true]);
    });

    it('422 sem privacy_level e nada é criado', function () {
        $this->withToken($this->user->test_token)->postJson('/api/scheduled-posts', tiktokStoreBody())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['privacy_level']);

        expect(ScheduledPost::withoutGlobalScopes()->count())->toBe(0);
    });

    it('422 com privacy_level inválido e nada é criado', function () {
        $this->withToken($this->user->test_token)->postJson('/api/scheduled-posts', tiktokStoreBody(['privacy_level' => 'XYZ']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['privacy_level']);

        expect(ScheduledPost::withoutGlobalScopes()->count())->toBe(0);
    });

    it('422 para conteúdo de marca + SELF_ONLY e nada é criado', function () {
        $this->withToken($this->user->test_token)->postJson('/api/scheduled-posts', tiktokStoreBody([
            'privacy_level' => 'SELF_ONLY',
            'brand_content_toggle' => true,
        ]))->assertUnprocessable();

        expect(ScheduledPost::withoutGlobalScopes()->count())->toBe(0);
    });

    it('422 quando disable_comment não é booleano', function () {
        $this->withToken($this->user->test_token)->postJson('/api/scheduled-posts', tiktokStoreBody([
            'privacy_level' => 'SELF_ONLY',
            'disable_comment' => 'talvez',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['disable_comment']);
    });

    it('422 quando a legenda passa de 2200 caracteres (limite do TikTok)', function () {
        $this->withToken($this->user->test_token)->postJson('/api/scheduled-posts', tiktokStoreBody([
            'privacy_level' => 'SELF_ONLY',
            'caption' => str_repeat('a', 2201),
        ]))->assertUnprocessable();

        expect(ScheduledPost::withoutGlobalScopes()->count())->toBe(0);
    });

    it('401 sem autenticação', function () {
        $this->postJson('/api/scheduled-posts', tiktokStoreBody(['privacy_level' => 'SELF_ONLY']))->assertUnauthorized();
    });

    it('403 sem posts.create', function () {
        $semPermissao = createUserWithPermissions(['posts.view']);

        $this->withToken($semPermissao->test_token)->postJson('/api/scheduled-posts', tiktokStoreBody(['privacy_level' => 'SELF_ONLY']))
            ->assertForbidden();
    });

});
