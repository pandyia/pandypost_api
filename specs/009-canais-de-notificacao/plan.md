# Plano

- `notification_preferences` (JSON do usuário) ganha a chave `in_app` por família; campos soltos seguem sendo o e-mail. Alternativa descartada: reestruturar tudo em `{email:{…}, in_app:{…}}`, que quebraria o contrato e os testes atuais.
- `User::inAppPreferences()` e `wantsInAppFor()` (billing e `post_published` sempre `true`: o segundo já é decidido na criação por `wantsPostPublished()`).
- `CentralNotification::via()` só inclui `database` + `broadcast` se `wantsInAppFor()`; e-mail inalterado.
- `NotificationService::updatePreferences` faz merge parcial de `in_app` sem perder o e-mail (e vice-versa).
- Risco: dedupe depende da linha no banco (ver spec).
