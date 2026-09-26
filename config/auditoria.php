<?php

use App\Models\ActuacionJudicial;
use App\Models\CashOpening;
use App\Models\Client;
use App\Models\ClientAttachment;
use App\Models\ClientAval;
use App\Models\ClientEmpresa;
use App\Models\Concept;
use App\Models\Contrato;
use App\Models\Credit;
use App\Models\DocumentoCliente;
use App\Models\EmpresaRepresentante;
use App\Models\ExchangeRate;
use App\Models\ExpedienteJudicial;
use App\Models\Expense;
use App\Models\ExpenseAttachment;
use App\Models\Garantia;
use App\Models\Headquarter;
use App\Models\Income;
use App\Models\IncomeAttachment;
use App\Models\LateFee;
use App\Models\LegalAdjunto;
use App\Models\LegalSetting;
use App\Models\MassDeletion;
use App\Models\Papeleta;
use App\Models\PapeletaRecurso;
use App\Models\Payment;
use App\Models\PlazoJudicial;
use App\Models\SigmAviso;
use App\Models\TramiteNotarial;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\VehiculoGpsReporte;
use App\Models\VehiculoGpsReporteFoto;

/*
 * Etiquetas del visor de auditoría (25/09, traído de newtaxivan).
 *
 * 'modulos': nombre legible de cada modelo auditado (columna "Afectado" y filtro Módulo).
 * 'etiquetas': por modelo, nombre legible de cada columna para el detalle
 *              (Antes / Después). Lo que no esté aquí se muestra con el nombre
 *              de la columna "humanizado" (fecha_prestamo → Fecha prestamo).
 */
return [
    'modulos' => [
        // Operativa
        Client::class => 'Cliente',
        Credit::class => 'Crédito',
        Payment::class => 'Pago',
        Income::class => 'Ingreso',
        Expense::class => 'Egreso',
        CashOpening::class => 'Apertura de caja',
        LateFee::class => 'Mora',
        MassDeletion::class => 'Cobro por lotes',
        ClientAval::class => 'Aval',
        ClientEmpresa::class => 'Empresa del cliente',
        EmpresaRepresentante::class => 'Representante legal',
        Vehiculo::class => 'Vehículo',
        DocumentoCliente::class => 'Documento',
        // Maestros
        User::class => 'Usuario',
        Concept::class => 'Concepto',
        ExchangeRate::class => 'Tipo de cambio',
        Headquarter::class => 'Sucursal',
        // Área legal
        Contrato::class => 'Contrato',
        Garantia::class => 'Garantía',
        SigmAviso::class => 'Aviso SIGM',
        TramiteNotarial::class => 'Trámite notarial',
        ExpedienteJudicial::class => 'Expediente judicial',
        ActuacionJudicial::class => 'Actuación judicial',
        PlazoJudicial::class => 'Plazo judicial',
        Papeleta::class => 'Papeleta',
        PapeletaRecurso::class => 'Recurso de papeleta',
        LegalSetting::class => 'Configuración legal',
        VehiculoGpsReporte::class => 'Reporte GPS',
        VehiculoGpsReporteFoto::class => 'Foto de reporte GPS',
        // Adjuntos
        ClientAttachment::class => 'Adjunto de cliente',
        IncomeAttachment::class => 'Adjunto de ingreso',
        ExpenseAttachment::class => 'Adjunto de egreso',
        LegalAdjunto::class => 'Adjunto legal',
    ],

    'etiquetas' => [
        User::class => [
            'name' => 'Nombre', 'username' => 'Usuario', 'email' => 'Correo', 'password' => 'Contraseña',
            'headquarter_id' => 'Sucursal', 'status' => 'Estado',
        ],

        Client::class => [
            'expediente' => 'Expediente', 'nombre' => 'Nombre', 'apellido_pat' => 'Apellido paterno',
            'apellido_mat' => 'Apellido materno', 'tipo_documento' => 'Tipo de documento', 'documento' => 'Documento',
            'fecha_registro' => 'Fecha de registro', 'usuario' => 'Usuario', 'fecha_nacimiento' => 'Fecha de nacimiento',
            'sexo' => 'Sexo', 'nacionalidad' => 'Nacionalidad', 'email' => 'Correo', 'giro' => 'Giro',
            'ocupacion' => 'Ocupación', 'estado_civil' => 'Estado civil', 'capital' => 'Capital',
            'celular1' => 'Celular 1', 'celular2' => 'Celular 2', 'direccion' => 'Dirección',
            'referencia' => 'Referencia', 'distrito' => 'Distrito', 'provincia' => 'Provincia',
            'departamento' => 'Departamento', 'zona' => 'Zona', 'contacto_emergencia' => 'Contacto de emergencia',
            'telefono_contacto' => 'Teléfono del contacto', 'banco_haberes' => 'Banco de haberes',
            'cuenta_haberes' => 'Cuenta de haberes', 'banco_cts' => 'Banco CTS', 'cuenta_cts' => 'Cuenta CTS',
            'afp' => 'AFP', 'cussp' => 'CUSPP', 'latitud' => 'Latitud', 'longitud' => 'Longitud',
            'latitud2' => 'Latitud 2', 'longitud2' => 'Longitud 2', 'imagen' => 'Imagen',
            'observaciones' => 'Observaciones', 'asesor_id' => 'Asesor', 'headquarter_id' => 'Sucursal',
            'status' => 'Estado', 'es_relacionado' => 'Persona relacionada',
        ],

        Credit::class => [
            'client_id' => 'Cliente', 'fecha_prestamo' => 'Fecha de préstamo',
            'fecha_actualizacion' => 'Fecha de actualización', 'importe' => 'Importe', 'cuotas' => 'Cuotas',
            'tipo_planilla' => 'Tipo de planilla', 'interes' => 'Interés', 'interes_total' => 'Interés total',
            'mora' => 'Mora', 'mora1' => 'Mora 1', 'mora2' => 'Mora 2', 'moneda' => 'Moneda',
            'documento' => 'Documento', 'glosa' => 'Glosa', 'situacion' => 'Situación', 'estado' => 'Estado',
            'refinanciado' => 'Refinanciado', 'cancelado_por_refi' => 'Cancelado por refinanciación',
            'cod_rem' => 'Código de refinanciación', 'gat' => 'GAT', 'idcan' => 'Crédito anterior',
            'fecha_vencimiento' => 'Fecha de vencimiento', 'fecha_cancelacion' => 'Fecha de cancelación',
            'asesor' => 'Asesor', 'user_id' => 'Usuario', 'usuario' => 'Usuario (nombre)',
            'headquarter_id' => 'Sucursal',
        ],

        Payment::class => [
            'credit_id' => 'Crédito', 'installment_id' => 'Cuota', 'modo' => 'Modo', 'tipo' => 'Tipo',
            'documento' => 'Documento', 'nro_recibo' => 'N.° de recibo', 'fecha' => 'Fecha', 'hora' => 'Hora',
            'monto' => 'Monto', 'moneda' => 'Moneda', 'tipo_cambio' => 'Tipo de cambio', 'detalle' => 'Detalle',
            'asesor' => 'Asesor', 'user_id' => 'Usuario', 'usuario' => 'Usuario (nombre)',
            'headquarter_id' => 'Sucursal', 'latitud' => 'Latitud', 'longitud' => 'Longitud',
        ],

        Income::class => [
            'date' => 'Fecha', 'reason' => 'Motivo', 'modo' => 'Modo', 'documento' => 'Documento',
            'asesor' => 'Asesor', 'detail' => 'Detalle', 'total' => 'Total', 'image_path' => 'Imagen',
            'user_id' => 'Usuario', 'headquarter_id' => 'Sucursal', 'caja' => 'Caja',
            'parent_id' => 'Registro origen',
        ],

        Expense::class => [
            'date' => 'Fecha', 'reason' => 'Motivo', 'modo' => 'Modo', 'documento' => 'Documento',
            'detail' => 'Detalle', 'total' => 'Total', 'document_type' => 'Tipo de documento',
            'in_charge' => 'Responsable', 'image_path' => 'Imagen', 'user_id' => 'Usuario',
            'headquarter_id' => 'Sucursal', 'caja' => 'Caja', 'parent_id' => 'Registro origen',
            'mass_deletion_id' => 'Cobro por lotes',
        ],

        CashOpening::class => [
            'fecha' => 'Fecha', 'hora' => 'Hora', 'saldo_inicial' => 'Saldo inicial', 'saldo_final' => 'Saldo final',
            'estado' => 'Estado', 'moneda' => 'Moneda', 'user_id' => 'Usuario', 'headquarter_id' => 'Sucursal',
        ],

        Vehiculo::class => [
            'client_id' => 'Cliente', 'propietario_tipo' => 'Tipo de propietario',
            'propietario_nombre' => 'Nombre del propietario', 'propietario_documento' => 'Documento del propietario',
            'placa' => 'Placa', 'marca' => 'Marca', 'modelo' => 'Modelo', 'nro_motor' => 'N.° de motor',
            'nro_serie' => 'N.° de serie', 'categoria' => 'Categoría', 'anio_modelo' => 'Año del modelo',
            'carroceria' => 'Carrocería', 'color' => 'Color', 'combustible' => 'Combustible',
            'partida_registral' => 'Partida registral', 'valor' => 'Valor', 'soat_vence' => 'Vencimiento SOAT',
            'revision_tecnica_vence' => 'Vencimiento revisión técnica',
            'habilitacion_atu_vence' => 'Vencimiento habilitación ATU', 'estado' => 'Estado',
            'observaciones' => 'Observaciones',
        ],

        Concept::class => [
            'code' => 'Código', 'name' => 'Nombre', 'type' => 'Tipo', 'factor_ingreso' => 'Factor de ingreso',
            'factor_egreso' => 'Factor de egreso', 'status' => 'Estado',
        ],

        Headquarter::class => [
            'name' => 'Nombre', 'empresa' => 'Empresa', 'ruc' => 'RUC', 'slogan' => 'Eslogan',
            'direccion' => 'Dirección', 'telefono' => 'Teléfono', 'email' => 'Correo',
            'responsable' => 'Responsable', 'sort_order' => 'Orden', 'status' => 'Estado',
        ],

        ExchangeRate::class => [
            'fecha' => 'Fecha', 'compra' => 'Compra', 'venta' => 'Venta',
        ],

        ClientAval::class => [
            'client_id' => 'Cliente', 'nombre' => 'Nombre', 'dni' => 'DNI', 'direccion' => 'Dirección',
            'telefono' => 'Teléfono',
        ],

        Garantia::class => [
            'credit_id' => 'Crédito', 'client_id' => 'Cliente', 'codeudor_client_id' => 'Codeudor', 'tipo' => 'Tipo',
            'tipo_persona' => 'Tipo de persona', 'gps' => 'GPS', 'custodia' => 'Custodia',
            'monto_gravamen' => 'Monto del gravamen', 'estado' => 'Estado', 'vigencia_hasta' => 'Vigencia hasta',
            'fecha_constitucion' => 'Fecha de constitución', 'requiere_revision' => 'Requiere revisión',
            'observaciones' => 'Observaciones', 'registrado_por' => 'Registrado por',
        ],

        ExpedienteJudicial::class => [
            'client_id' => 'Cliente', 'credit_id' => 'Crédito', 'garantia_id' => 'Garantía',
            'exp_interno' => 'Expediente interno', 'nro_expediente' => 'N.° de expediente', 'cuaderno' => 'Cuaderno',
            'expediente_padre_id' => 'Expediente principal', 'juzgado' => 'Juzgado',
            'distrito_judicial' => 'Distrito judicial', 'materia' => 'Materia', 'proceso' => 'Proceso',
            'juez' => 'Juez', 'secretario' => 'Secretario', 'via' => 'Vía', 'forma_medida' => 'Forma de la medida',
            'bien_descripcion' => 'Bien', 'monto_pretension' => 'Monto de la pretensión', 'estado' => 'Estado',
            'asesor_responsable_id' => 'Asesor responsable', 'fecha_inicio' => 'Fecha de inicio',
            'requiere_revision' => 'Requiere revisión', 'observaciones' => 'Observaciones',
        ],

        Papeleta::class => [
            'vehiculo_id' => 'Vehículo', 'entidad' => 'Entidad', 'nro_papeleta' => 'N.° de papeleta',
            'codigo_falta' => 'Código de falta', 'puntos' => 'Puntos', 'fecha_infraccion' => 'Fecha de infracción',
            'monto' => 'Monto', 'responsable_pago' => 'Responsable del pago', 'conductor_nombre' => 'Conductor',
            'conductor_documento' => 'Documento del conductor', 'estado' => 'Estado',
            'requiere_revision' => 'Requiere revisión', 'nota' => 'Nota', 'registrado_por' => 'Registrado por',
        ],

        TramiteNotarial::class => [
            'garantia_id' => 'Garantía', 'contrato_id' => 'Contrato', 'client_id' => 'Cliente', 'tipo' => 'Tipo',
            'descripcion' => 'Descripción', 'notaria' => 'Notaría', 'estado' => 'Estado',
            'estado_desde' => 'En estado desde', 'fecha_ingreso_notaria' => 'Fecha de ingreso a notaría',
            'fecha_firma' => 'Fecha de firma', 'fecha_recojo' => 'Fecha de recojo', 'costo' => 'Costo',
            'expense_id' => 'Egreso', 'ubicacion_archivo' => 'Ubicación en archivo', 'nota' => 'Nota',
            'responsable_id' => 'Responsable', 'requiere_revision' => 'Requiere revisión',
        ],
    ],
];
