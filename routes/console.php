<?php

use App\Models\SocialAccountMetricSnapshot;
use Illuminate\Support\Facades\Schedule;

// O Polling de Posts Pendentes foi removido pois agora utilizamos Delay Nativos na Esteira da Estratégia B.

Schedule::command('posts:warmup')->everyMinute(); // comando para aquecer o cache, evitar delay no insta
// Schedule::command('subscription:reset-quota')->monthly(); //comando para resetar a cota de posts
Schedule::command('invites:clean-expired')->dailyAt('01:00'); // comando para limpar convites expirados as 01:00 da manhã
Schedule::command('audits:prune')->cron('0 2 1 */6 *'); // comando para remover logs de auditoria com mais de 6 meses. Roda a cada 6 meses no dia 1 as 02:00 (Minuto 0, Hora 2 → às 02:00, Dia 1 → primeiro dia do mês, */6 → a cada 6 meses (Janeiro e Julho))
Schedule::command('analytics:snapshot')->dailyAt('03:00'); // snapshot diário das métricas das contas Instagram/TikTok (as APIs não guardam histórico)
Schedule::command('model:prune', ['--model' => [SocialAccountMetricSnapshot::class]])->dailyAt('04:00'); // remove snapshots com mais de 400 dias
Schedule::command('notifications:prune')->dailyAt('05:00'); // apaga notificações lidas há mais de 30 dias (não lidas nunca)
Schedule::command('posts:purge-failed-media')->dailyAt('06:00'); // apaga do bucket a mídia de posts com falha há mais de 5 dias sem reenvio
