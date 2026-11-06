<?php

describe('D7: config services.tiktok', function () {

    it('não existe mais default_privacy (privacy_level não tem valor padrão)', function () {
        expect(config('services.tiktok'))->not->toHaveKey('default_privacy');
    });

    it('mantém as credenciais e o redirect do OAuth', function () {
        expect(config('services.tiktok'))->toHaveKeys(['client_key', 'client_secret', 'redirect']);
    });

});
