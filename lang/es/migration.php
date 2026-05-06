<?php

return [
    // Banner (export side)
    'banner_label' => 'Nuevo servidor',
    'banner_body' => 'VirtuaFC tiene un nuevo servidor. Tus datos y partidas se copiarán automáticamente y podrás seguir jugando como siempre.',
    'banner_cta' => 'Llévame al nuevo servidor',

    // Handoff errors
    'handoff_invalid' => 'Este enlace de migración no es válido o ha caducado. Vuelve atrás e inténtalo de nuevo.',
    'handoff_user_missing' => 'No hemos podido encontrar tu cuenta en el nuevo servidor. Contacta con soporte.',

    // Import page (import side)
    'import_title' => 'Completa tu migración',
    'import_intro' => 'Pulsa el botón para copiar tu carrera de mánager y todas tus partidas al nuevo servidor.',
    'import_cta' => 'Copiar mis datos',
    'import_in_progress' => 'Copiando tus datos…',
    'import_progress_label' => ':percent% — :step',
    'import_step_starting' => 'Empezando',
    'import_step_user' => 'Perfil',
    'import_step_stats' => 'Estadísticas de mánager',
    'import_step_trophies' => 'Trofeos',
    'import_step_games' => 'Partidas (:current de :total)',
    'import_step_finalizing' => 'Finalizando',
    'import_completed' => '¡Listo! Bienvenido a bordo.',
    'import_failed' => 'Algo ha salido mal. Inténtalo de nuevo.',
    'import_retry' => 'Reintentar',
    'import_already_completed' => 'Ya has migrado. Continúa a tu panel.',
    'import_already_in_progress' => 'Ya hay una migración en curso para tu cuenta.',
    'import_no_data_found' => 'No hemos encontrado datos que migrar para tu cuenta.',

    // Export side — user has migrated and tries to use beta
    'completed_title' => 'Migración completada',
    'completed_body' => 'Tu cuenta y tus partidas viven ahora en :url. Este servidor es de solo lectura para los usuarios migrados.',
    'completed_cta' => 'Continuar al nuevo servidor',
];
