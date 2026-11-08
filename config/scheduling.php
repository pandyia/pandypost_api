<?php

return [
    // Antecedência mínima (em minutos) do agendamento: validada no clique de agendar e no reenvio.
    'min_lead_minutes' => (int) env('SCHEDULE_MIN_LEAD_MINUTES', 5),

    // Horas que um post fica em `awaiting_upload` (envio do vídeo em segundo plano) antes de virar `failed`.
    'upload_expiration_hours' => (int) env('SCHEDULE_UPLOAD_EXPIRATION_HOURS', 24),
];
