<?php

use App\Contracts\ErrorEnumInterface;
use App\Enums\Exceptions\TikTokError;
use App\Exceptions\BaseException;
use App\Exceptions\TikTokException;

// RF-8 / D4: código de erro do TikTok -> mensagem pt-BR, sem body cru e sem token.
const TIKTOK_KNOWN_ERROR_CODES = [
    'spam_risk_too_many_posts',
    'reached_active_user_cap',
    'unaudited_client_can_only_post_to_private_accounts',
    'scope_not_authorized',
    'access_token_invalid',
    'url_ownership_unverified',
    'file_format_check_failed',
];

describe('TikTokException::fromApiCode', function () {

    it('é uma exceção de domínio (BaseException) que renderiza error + message', function () {
        $e = TikTokException::fromApiCode('spam_risk_too_many_posts');

        expect($e)->toBeInstanceOf(BaseException::class);
        $json = $e->render()->getData(true);
        expect($json)->toHaveKeys(['error', 'message'])
            ->and($json['message'])->toBe($e->getMessage());
    });

    it('mapeia o código conhecido para mensagem em pt-BR, sem repetir o código cru', function (string $code) {
        $message = TikTokException::fromApiCode($code)->getMessage();

        expect($message)->not->toBeEmpty()
            ->and($message)->not->toContain($code)
            ->and($message)->toMatch('/[áàâãéêíóôõúç]|conta|vídeo|tente|TikTok/iu');
    })->with(TIKTOK_KNOWN_ERROR_CODES);

    it('cada código conhecido tem mensagem própria', function () {
        $messages = collect(TIKTOK_KNOWN_ERROR_CODES)
            ->map(fn (string $c) => TikTokException::fromApiCode($c)->getMessage());

        expect($messages->unique()->count())->toBe(count(TIKTOK_KNOWN_ERROR_CODES));
    });

    it('app não auditado diz "App do TikTok não auditado" (mensagem já usada no teste anterior)', function () {
        expect(TikTokException::fromApiCode('unaudited_client_can_only_post_to_private_accounts')->getMessage())
            ->toContain('App do TikTok não auditado');
    });

    it('limite de posts diário (spam_risk_too_many_posts) é erro mapeado, não genérico', function () {
        $generic = TikTokException::fromApiCode('codigo_que_ninguem_conhece')->getMessage();

        expect(TikTokException::fromApiCode('spam_risk_too_many_posts')->getMessage())->not->toBe($generic);
    });

    it('código desconhecido vira mensagem genérica em pt-BR sem vazar o código', function () {
        $message = TikTokException::fromApiCode('codigo_interno_xyz_999')->getMessage();

        expect($message)->not->toBeEmpty()->and($message)->not->toContain('codigo_interno_xyz_999');
    });

    it('as factories de domínio existem e têm mensagem pt-BR', function () {
        expect(TikTokException::scopeMissing()->getMessage())->toContain('reconecte')
            ->and(TikTokException::privacyNotAllowed()->getMessage())->not->toBeEmpty()
            ->and(TikTokException::fromApiCode('duration_check_failed')->getMessage())->toContain('mais longo');
    });

    it('o código HTTP da exceção é um status HTTP válido (4xx/5xx)', function () {
        foreach (TIKTOK_KNOWN_ERROR_CODES as $code) {
            expect(TikTokException::fromApiCode($code)->getHttpCode())->toBeGreaterThanOrEqual(400)->toBeLessThan(600);
        }
    });

});

describe('TikTokError (enum)', function () {

    it('implementa ErrorEnumInterface', function () {
        expect(is_subclass_of(TikTokError::class, ErrorEnumInterface::class))->toBeTrue();
    });

});
