<?php

return [
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
    'import_failed' => 'Algo ha salido mal con la migración.',
    'import_failed_body' => 'Se ha enviado un aviso automático y se revisará. Tus datos están a salvo en el servidor antiguo y no se ha perdido nada — podrás terminar la migración cuando esté solucionado.',
    'import_already_completed' => 'Ya has migrado. Continúa a tu panel.',
    'import_already_in_progress' => 'Ya hay una migración en curso para tu cuenta.',
    'import_no_data_found' => 'No hemos encontrado datos que migrar para tu cuenta.',
    'import_skip_link' => 'O empieza desde cero sin copiar nada',
    'import_skip_modal_title' => '¿Empezar desde cero?',
    'import_skip_modal_body' => 'Tu carrera y partidas anteriores se quedarán en el servidor antiguo, pero ya no podrás acceder a ellas. Empezarás con una cuenta limpia aquí. Esto no se puede deshacer.',
    'import_skip_modal_confirm' => 'Sí, empezar desde cero',
    'import_skip_modal_cancel' => 'Cancelar',
    'import_skip_not_allowed' => 'Esta cuenta no puede saltarse la migración en su estado actual.',

    // Export side — user has migrated and tries to use beta
    'completed_title' => 'Migración completada',
    'completed_body' => 'Tu cuenta y tus partidas viven ahora en :url. Este servidor es de solo lectura para los usuarios migrados.',
    'completed_cta' => 'Continuar al nuevo servidor',

    // Export side — pending user is forced to migrate before they can keep playing
    'required_title' => 'Hora de mudarse',
    'required_body' => 'VirtuaFC tiene un nuevo servidor. Para seguir jugando necesitas mover tu cuenta y tus partidas — solo lleva un momento y no se pierde nada.',
    'required_cta' => 'Llévame al nuevo servidor',
];
