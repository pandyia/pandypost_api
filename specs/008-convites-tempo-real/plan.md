# Plano

- `app/Events/InviteEvent.php`: igual ao `RoleEvent`, com `ShouldDispatchAfterCommit` (envio e aceite rodam em `DB::transaction`) e `broadcastWith()` enxuto. Alternativa descartada: reaproveitar `RoleEvent`, por semântica diferente.
- `app/Observers/InviteObserver.php` registrado via `#[ObservedBy]` em `Invite`.
- Risco: evento disparado antes do commit faria a UI recarregar dado antigo, daí o `ShouldDispatchAfterCommit`.
