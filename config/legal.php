<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Área Legal: módulo visible o no
    |--------------------------------------------------------------------------
    |
    | 11/10/2026 (Antony): "hay que ocultar el módulo del área legal en todo lo
    | que competa: permisos, notificaciones, menú". Con false (valor por
    | defecto) desaparecen el menú lateral "Área Legal", la campana de alertas
    | legales, el grupo "Área Legal" en /users/{id}/perms, el rol area-legal en
    | los selectores de rol y las rutas legal/* responden 404. Los datos, los
    | permisos ya asignados y el código quedan intactos: para volver a verlo
    | basta AREA_LEGAL_ACTIVO=true en el .env.
    |
    | No cubre lo que el área de préstamos usa a diario y vive fuera del
    | módulo: la pestaña Documentos del cliente (contratos y anexos), los
    | vehículos y las garantías de la ficha.
    |
    */

    'activo' => (bool) env('AREA_LEGAL_ACTIVO', false),

];
