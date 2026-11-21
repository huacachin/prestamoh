<?php

namespace App\Support;

use App\Models\Client;
use App\Models\DocumentoCliente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ruta del expediente: el paso a paso de un préstamo (20/11/2026, Antony: "un flujo de creación de
 * clientes hasta la generación de sus documentos… que les sea amigable"). Siete pasos —Cliente → Vehículo
 * → Préstamo → Documentos → SIGM → GPS → Adjuntos—, cada uno hecho, pendiente o no aplica. Los adjuntos
 * van al final (21/11, Antony: "en el paso a paso los adjuntos deben de ir al final"): las fotos se
 * suben cuando el préstamo ya existe.
 *
 * El estado se CALCULA con lo que ya está guardado: nadie marca nada a mano. La ruta solo orienta: no
 * bloquea ningún paso ni cambia las reglas de las pantallas.
 *
 * Es de CADA PRÉSTAMO (20/11, tras hablar con el cliente): con un préstamo nuevo todos sus pasos empiezan
 * de cero —también Vehículo, Adjuntos y GPS, que antes eran del cliente y se quedaban como hechos—.
 *  · Documentos y SIGM llevan el préstamo anotado.
 *  · Fotos, vehículos y cuentas GPS no: pertenecen al préstamo vigente cuando se registraron (ver
 *    expedientesDe y esDe). Lo que vino del sistema anterior es de los préstamos antiguos.
 *  · El vehículo que nombra el contrato emitido del préstamo es suyo, se registrara cuando se registrara.
 *  · El mismo vehículo puede volver a prendarse (Antony: "un cliente puede prendar el mismo vehículo si ya
 *    ha pagado parte de su préstamo"): se dice en la pestaña Vehículos y queda en `credit_vehiculo`, igual
 *    que la confirmación de que su cuenta GPS vale para el préstamo nuevo.
 *
 * Por defecto se sigue el crédito Activo más reciente del cliente que no viene de una refinanciación.
 * Vehículo, Documentos, SIGM y GPS se piden a los "T. Crédito" con garantía sobre un vehículo (config
 * expediente); a los demás les salen como "No aplica", salvo que ya tengan algo. La refinanciación no abre
 * expediente: sigue siendo el del préstamo original (config expediente.refinanciacion_pide_documentos).
 */
final class RutaExpediente
{
    public const HECHO = 'hecho';

    public const PENDIENTE = 'pendiente';

    public const NO_APLICA = 'no_aplica';

    /** Paso => [nombre, pestaña de la ficha, lo que hay que hacer]. En el orden de la ruta. */
    public const PASOS = [
        'cliente' => ['Cliente', 'datos', 'completar los datos del cliente'],
        'vehiculo' => ['Vehículo', 'vehiculos', 'registrar o elegir el vehículo'],
        'prestamo' => ['Préstamo', 'prestamos', 'crear el préstamo'],
        'documentos' => ['Documentos', 'documentos', 'emitir los documentos'],
        'sigm' => ['SIGM', 'documentos', 'registrar la constitución del SIGM'],
        'gps' => ['GPS', 'gps', 'registrar o confirmar la cuenta GPS'],
        'adjuntos' => ['Adjuntos', 'adjuntos', 'subir los adjuntos'],
    ];

    /**
     * Ruta de un cliente para uno de sus préstamos. Sin préstamo indicado —o si el indicado no es un
     * crédito Activo suyo— es la del préstamo de la ruta (ver creditosEnCurso).
     *
     * @return array{pasos: list<array{clave: string, num: int, nombre: string, tab: string, estado: string, detalle: string}>, hechos: int, total: int, siguiente: ?array{clave: string, nombre: string, tab: string, accion: string}, credito: ?int, completa: bool}
     */
    public static function de(Client|int $client, ?int $creditId = null): array
    {
        $id = $client instanceof Client ? (int) $client->id : $client;

        return self::rutas([self::par($id, $creditId)])[0] ?? self::armar(self::sinDatos());
    }

    /**
     * Ruta mientras se da de alta al cliente, que todavía no existe. Sigue al wizard del alta, cuyos dos
     * pasos SON los dos primeros de la ruta: 1 los datos del cliente y 2 sus vehículos (20/11, Antony: "ya
     * existe un wizard de vehículo… pide otra vez vehículo, es confuso"). Así el alta lleva un solo
     * indicador de pasos, y dice ahí mismo qué pasa si el vehículo se deja para después.
     *
     * Lo escrito aún no está guardado: el vehículo no se da por hecho, se anota cuántos van "por guardar".
     *
     * @param  int  $pasoDelWizard  1 = datos del cliente, 2 = vehículos
     * @param  int  $vehiculos  vehículos agregados en el formulario
     * @return array<string, mixed> la ruta, más 'actual' (paso en el que se está) y 'aviso' (qué se hace ahí)
     */
    public static function enAlta(int $pasoDelWizard, ?string $tipoCredito, int $vehiculos): array
    {
        $enVehiculos = $pasoDelWizard >= 2;
        // Sin "T. Crédito" elegido todavía, se enseña la ruta entera.
        $pideVehiculo = trim((string) $tipoCredito) === '' || self::conGarantiaMobiliaria($tipoCredito);
        $pasos = self::armar(['existe' => $enVehiculos, 'con_garantia' => $pideVehiculo] + self::sinDatos())['pasos'];

        foreach ($pasos as &$paso) {
            if ($paso['clave'] === 'cliente' && $enVehiculos) {
                $paso['detalle'] = 'Datos listos';
            }
            if ($paso['clave'] === 'vehiculo' && $vehiculos > 0) {
                $paso['estado'] = self::PENDIENTE;
                $paso['detalle'] = $vehiculos.' por guardar';
            }
        }
        unset($paso);

        $aviso = match (true) {
            ! $enVehiculos => 'Paso 1: los datos del cliente. Al continuar sigue el vehículo, en esta misma pantalla.',
            $vehiculos > 0 => 'Paso 2: el vehículo se guarda junto con el cliente. Después sigue el préstamo, ya en su ficha.',
            $pideVehiculo => 'Paso 2: agrega aquí el vehículo. Si todavía no tienes sus datos, guarda al cliente: su ficha te lo pedirá como el paso que sigue.',
            default => 'Este tipo de crédito no pide vehículo: puedes guardar al cliente sin registrarlo. Después sigue el préstamo, ya en su ficha.',
        };

        return ['actual' => $enVehiculos ? 'vehiculo' : 'cliente', 'aviso' => $aviso] + self::resumir($pasos, null);
    }

    /**
     * Rutas de varios clientes, cada una para su préstamo de la ruta, con un número fijo de consultas.
     *
     * @param  iterable<int>  $clientIds
     * @return array<int, array<string, mixed>> por id de cliente
     */
    public static function deClientes(iterable $clientIds): array
    {
        $ids = self::enteros($clientIds);
        $enCurso = self::creditosEnCurso($ids);
        $pares = [];
        foreach ($ids as $id) {
            $pares[$id] = ['cliente' => $id, 'credito' => $enCurso[$id]['id'] ?? null, 'refinanciacion' => $enCurso[$id]['refinanciacion'] ?? false];
        }

        return self::rutas($pares);
    }

    /**
     * Rutas de varios préstamos (20/11/2026: el expediente es de cada préstamo, no solo del cliente), con
     * un número fijo de consultas.
     *
     * @param  iterable<int>  $creditIds
     * @return array<int, array<string, mixed>> por id de crédito
     */
    public static function dePrestamos(iterable $creditIds): array
    {
        $ids = self::enteros($creditIds);
        $pares = [];
        if ($ids !== []) {
            foreach (DB::table('credits')->whereIn('id', $ids)->get(['id', 'client_id', 'refinanciado']) as $credito) {
                $pares[(int) $credito->id] = ['cliente' => (int) $credito->client_id, 'credito' => (int) $credito->id, 'refinanciacion' => (bool) $credito->refinanciado];
            }
        }

        return self::rutas($pares);
    }

    /**
     * Préstamos Activos del cliente entre los que se puede elegir la ruta, primero el de la ruta.
     *
     * @return list<array{id: int, fecha: ?string, importe: float, refinanciacion: bool}>
     */
    public static function prestamosActivos(int $clientId): array
    {
        return DB::table('credits')->where('client_id', $clientId)->where('situacion', 'Activo')
            ->orderBy('refinanciado')->orderByDesc('fecha_prestamo')->orderByDesc('id')
            ->get(['id', 'fecha_prestamo', 'importe', 'refinanciado'])
            ->map(fn ($c) => [
                'id' => (int) $c->id,
                'fecha' => $c->fecha_prestamo ? date('d/m/Y', strtotime((string) $c->fecha_prestamo)) : null,
                'importe' => (float) $c->importe,
                'refinanciacion' => (bool) $c->refinanciado,
            ])
            ->all();
    }

    /**
     * @param  iterable<int>  $ids
     * @return list<int>
     */
    private static function enteros(iterable $ids): array
    {
        return array_values(array_unique(array_map('intval', is_array($ids) ? $ids : iterator_to_array($ids, false))));
    }

    /**
     * Arma la ruta de cada par cliente–préstamo, con un número fijo de consultas.
     *
     * @param  array<int, array{cliente: int, credito: ?int, refinanciacion: bool}>  $pares
     * @return array<int, array<string, mixed>> con las mismas claves; sin los clientes que no existen
     */
    private static function rutas(array $pares): array
    {
        $ids = self::enteros(array_column($pares, 'cliente'));
        if ($ids === []) {
            return [];
        }

        $tipos = DB::table('clients')->whereIn('id', $ids)->pluck('zona', 'id');
        $reparto = self::repartir($pares);

        $creditos = self::enteros(array_filter(array_column($pares, 'credito')));
        $emitidos = [];
        $constituidos = [];
        if ($creditos !== []) {
            foreach (DB::table('documentos_cliente')->whereIn('credit_id', $creditos)->where('estado', 'emitido')
                ->distinct()->get(['credit_id', 'tipo']) as $doc) {
                $emitidos[(int) $doc->credit_id][] = (string) $doc->tipo;
            }
            // Misma regla que GeneradorSigm::constitucionDe(): la garantía más reciente del crédito tiene un
            // aviso de constitución que no está anulado.
            $constituidos = DB::table('garantias')
                ->join('sigm_avisos', 'sigm_avisos.garantia_id', '=', 'garantias.id')
                ->whereIn('garantias.credit_id', $creditos)
                ->whereRaw('garantias.id = (select max(g.id) from garantias g where g.credit_id = garantias.credit_id)')
                ->where('sigm_avisos.tipo', 'constitucion')->where('sigm_avisos.estado', '!=', 'anulado')
                ->distinct()->pluck('garantias.credit_id')->map(fn ($c) => (int) $c)->all();
        }

        $rutas = [];
        foreach ($pares as $clave => $par) {
            $id = $par['cliente'];
            if (! $tipos->has($id)) {
                continue; // el cliente no existe
            }
            $credito = $par['credito'];
            $suyo = $reparto[$clave];
            $rutas[$clave] = self::armar([
                'existe' => true,
                'con_garantia' => self::conGarantiaMobiliaria($tipos[$id]),
                'abre' => $suyo['abre'],
                'vehiculos' => count(array_filter($suyo['vehiculos'], fn (array $v) => $v['del_prestamo'])),
                'vehiculos_cliente' => count($suyo['vehiculos']),
                'con_cuenta_gps' => count(array_filter($suyo['vehiculos'], fn (array $v) => $v['del_prestamo'] && $v['gps'])),
                'adjuntos' => $suyo['fotos'],
                'adjuntos_cliente' => $suyo['fotos_cliente'],
                'credito' => $credito,
                'emitidos' => $credito !== null ? ($emitidos[$credito] ?? []) : [],
                'constituido' => $credito !== null && in_array($credito, $constituidos, true),
            ]);
        }

        return $rutas;
    }

    /**
     * Vehículos del cliente vistos desde uno de sus préstamos (el indicado si es un crédito Activo suyo;
     * si no, el de la ruta): cuáles son de ese préstamo, cuáles se prendaron a mano y si su cuenta GPS
     * vale para él. Para las pestañas Vehículos y GPS de la ficha.
     *
     * @return array{credito: ?int, abre: bool, vehiculos: array<int, array{del_prestamo: bool, prendado: bool, cuenta: bool, gps: bool}>}
     */
    public static function vehiculosDelPrestamo(int $clientId, ?int $creditId = null): array
    {
        $par = self::par($clientId, $creditId);

        return ['credito' => $par['credito']] + array_intersect_key(self::repartir([$par])[0], ['abre' => 0, 'vehiculos' => 0]);
    }

    /**
     * El par cliente–préstamo de una ruta: el préstamo indicado si es un crédito Activo del cliente; si
     * no, el de la ruta.
     *
     * @return array{cliente: int, credito: ?int, refinanciacion: bool}
     */
    private static function par(int $clientId, ?int $creditId): array
    {
        if ($creditId !== null) {
            $credito = DB::table('credits')->where('id', $creditId)->where('client_id', $clientId)
                ->where('situacion', 'Activo')->first(['id', 'refinanciado']);
            if ($credito !== null) {
                return ['cliente' => $clientId, 'credito' => (int) $credito->id, 'refinanciacion' => (bool) $credito->refinanciado];
            }
        }
        $enCurso = self::creditosEnCurso([$clientId])[$clientId] ?? null;

        return ['cliente' => $clientId, 'credito' => $enCurso['id'] ?? null, 'refinanciacion' => $enCurso['refinanciacion'] ?? false];
    }

    /**
     * Reparte lo del cliente —fotos, vehículos y cuentas GPS— entre sus préstamos y devuelve, de cada par,
     * lo que es de su préstamo. Sin préstamo (cliente sin crédito Activo) es lo que contaría para el
     * próximo que se cree.
     *
     * @param  array<int, array{cliente: int, credito: ?int, refinanciacion: bool}>  $pares
     * @return array<int, array{abre: bool, fotos: int, fotos_cliente: int, vehiculos: array<int, array{del_prestamo: bool, prendado: bool, cuenta: bool, gps: bool}>}>
     */
    private static function repartir(array $pares): array
    {
        $ids = self::enteros(array_column($pares, 'cliente'));
        if ($ids === []) {
            return [];
        }
        [$expedientes, $ultimoCreado] = self::expedientesDe($ids);

        $fotos = [];
        foreach (DB::table('client_attachments')->whereIn('client_id', $ids)->get(['client_id', 'created_at']) as $foto) {
            $fotos[(int) $foto->client_id][] = self::momento($foto->created_at);
        }
        $vehiculos = [];
        $placas = [];
        foreach (DB::table('vehiculos')->whereIn('client_id', $ids)->orderBy('id')->get(['id', 'client_id', 'placa', 'created_at']) as $vehiculo) {
            $vehiculos[(int) $vehiculo->client_id][(int) $vehiculo->id] = self::momento($vehiculo->created_at);
            $placas[(int) $vehiculo->id] = self::placa($vehiculo->placa);
        }
        $cuentas = [];
        foreach (DB::table('vehiculo_gps_cuentas')->join('vehiculos', 'vehiculos.id', '=', 'vehiculo_gps_cuentas.vehiculo_id')
            ->whereIn('vehiculos.client_id', $ids)
            ->get(['vehiculo_gps_cuentas.vehiculo_id', 'vehiculo_gps_cuentas.created_at', 'vehiculo_gps_cuentas.updated_at']) as $cuenta) {
            $cuentas[(int) $cuenta->vehiculo_id] = [self::momento($cuenta->created_at), self::momento($cuenta->updated_at)];
        }
        $vinculos = [];
        $creditos = self::enteros(array_filter(array_column($pares, 'credito')));
        if ($creditos !== []) {
            foreach (DB::table('credit_vehiculo')->whereIn('credit_id', $creditos)->get(['credit_id', 'vehiculo_id', 'prendado', 'gps_confirmado_at']) as $vinculo) {
                $vinculos[(int) $vinculo->credit_id][(int) $vinculo->vehiculo_id] = ['prendado' => (bool) $vinculo->prendado, 'gps' => $vinculo->gps_confirmado_at !== null];
            }
        }

        // Las placas que nombra el contrato emitido del préstamo: ese vehículo es suyo, se registrara cuando
        // se registrara (20/11: en producción, 13 préstamos con el contrato ya emitido pedían "elegir el vehículo").
        $enContrato = [];
        if ($creditos !== []) {
            foreach (DB::table('documentos_cliente')->whereIn('credit_id', $creditos)->where('tipo', 'contrato')->where('estado', 'emitido')
                ->selectRaw("credit_id, JSON_EXTRACT(snapshot, '$.bienes[*].placa') as placas")->get() as $contrato) {
                foreach ((array) json_decode((string) $contrato->placas, true) as $placa) {
                    $enContrato[(int) $contrato->credit_id][self::placa($placa)] = true;
                }
            }
        }

        $previos = max(0, (int) config('expediente.dias_previos', 0)) * 86400;
        $reparto = [];
        foreach ($pares as $clave => $par) {
            $cliente = $par['cliente'];
            $suyos = $expedientes[$cliente] ?? [];
            $credito = $par['credito'];
            if ($credito === null) {
                // Sin préstamo activo: lo que se registre ahora es del próximo (clave 0).
                $suyos[0] = max(strtotime('today') - $previos, $ultimoCreado[$cliente] ?? PHP_INT_MIN);
                $credito = 0;
            }
            // Un préstamo que no abre expediente (refinanciación) no tiene nada propio.
            $abre = isset($suyos[$credito]);
            $esSuyo = fn (int $momento): bool => $abre && self::esDe($suyos, $momento, $credito);

            $delCliente = [];
            foreach ($vehiculos[$cliente] ?? [] as $vehiculoId => $registrado) {
                $vinculo = $vinculos[$credito][$vehiculoId] ?? ['prendado' => false, 'gps' => false];
                $cuenta = $cuentas[$vehiculoId] ?? null;
                $delCliente[$vehiculoId] = [
                    'del_prestamo' => $abre && ($vinculo['prendado'] || $esSuyo($registrado) || isset($enContrato[$credito][$placas[$vehiculoId]])),
                    'prendado' => $vinculo['prendado'],
                    'cuenta' => $cuenta !== null,
                    // La cuenta vale para este préstamo si se registró o se volvió a guardar durante él, o se confirmó.
                    'gps' => $cuenta !== null && ($vinculo['gps'] || $esSuyo($cuenta[0]) || $esSuyo($cuenta[1])),
                ];
            }
            $reparto[$clave] = [
                'abre' => $abre,
                'fotos' => count(array_filter($fotos[$cliente] ?? [], $esSuyo)),
                'fotos_cliente' => count($fotos[$cliente] ?? []),
                'vehiculos' => $delCliente,
            ];
        }

        return $reparto;
    }

    /**
     * Préstamos de cada cliente que abren expediente (ni eliminados ni, salvo configuración, los que vienen
     * de una refinanciación), en orden, con el momento DESDE el que lo registrado cuenta para cada uno:
     *  · los que vinieron del sistema anterior no tienen fecha real de registro: PHP_INT_MIN, "antiguos";
     *  · los demás, desde `expediente.dias_previos` días antes del día en que se crearon (el cliente trae
     *    sus papeles antes de que se registre el préstamo), pero nunca antes de que se creara el anterior.
     *
     * @param  list<int>  $clientIds
     * @return array{0: array<int, array<int, int>>, 1: array<int, int>} [cliente => [crédito => desde]] y [cliente => creación del último]
     */
    private static function expedientesDe(array $clientIds): array
    {
        $migradoHasta = (int) strtotime((string) config('expediente.migrado_hasta', '1970-01-01'));
        $previos = max(0, (int) config('expediente.dias_previos', 0)) * 86400;

        $expedientes = [];
        $ultimoCreado = [];
        foreach (DB::table('credits')->whereIn('client_id', $clientIds)->where('situacion', '!=', 'Eliminado')
            ->when(! self::refinanciacionAbreExpediente(), fn ($q) => $q->where('refinanciado', false))
            ->orderBy('created_at')->orderBy('id')->get(['id', 'client_id', 'created_at']) as $credito) {
            $cliente = (int) $credito->client_id;
            $creado = (int) strtotime((string) $credito->created_at);
            if ($creado <= $migradoHasta) {
                $expedientes[$cliente][(int) $credito->id] = PHP_INT_MIN;

                continue;
            }
            $expedientes[$cliente][(int) $credito->id] = max(strtotime(date('Y-m-d', $creado)) - $previos, $ultimoCreado[$cliente] ?? PHP_INT_MIN);
            $ultimoCreado[$cliente] = $creado;
        }

        return [$expedientes, $ultimoCreado];
    }

    /** La placa sin guiones ni espacios y en mayúsculas, para comparar la de la ficha con la del contrato. */
    private static function placa(mixed $placa): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $placa));
    }

    /** Momento en que se registró algo; PHP_INT_MIN si vino del sistema anterior (su fecha es la de la migración). */
    private static function momento(?string $fecha): int
    {
        $momento = (int) strtotime((string) $fecha);

        return $momento <= (int) strtotime((string) config('expediente.migrado_hasta', '1970-01-01')) ? PHP_INT_MIN : $momento;
    }

    /**
     * ¿Lo registrado en ese momento es de este préstamo? Es del último préstamo cuyo "desde" ya había
     * llegado; lo anterior a todos, del primero (cliente nuevo: se registra todo y luego el préstamo).
     * Lo que vino del sistema anterior es de los préstamos antiguos —de todos: no hay forma de saber de
     * cuál—, nunca de uno nuevo.
     *
     * @param  array<int, int>  $expedientes  [crédito => desde] en orden (ver expedientesDe)
     */
    private static function esDe(array $expedientes, int $momento, int $creditId): bool
    {
        if (! isset($expedientes[$creditId])) {
            return false;
        }
        if ($momento === PHP_INT_MIN && in_array(PHP_INT_MIN, $expedientes, true)) {
            return $expedientes[$creditId] === PHP_INT_MIN;
        }
        $dueno = array_key_first($expedientes);
        foreach ($expedientes as $id => $desde) {
            if ($desde !== PHP_INT_MIN && $desde <= $momento) {
                $dueno = $id;
            }
        }

        return $dueno === $creditId;
    }

    /** El préstamo de la ruta del cliente (ver creditosEnCurso); null si no tiene ninguno activo. */
    public static function creditoEnCurso(int $clientId): ?int
    {
        return self::creditosEnCurso([$clientId])[$clientId]['id'] ?? null;
    }

    /**
     * ¿A un préstamo de este "T. Crédito" se le piden documentos y SIGM? Sí con garantía mobiliaria,
     * salvo que venga de una refinanciación (sus documentos son los del préstamo original).
     */
    public static function pideDocumentos(?string $tipoCredito, bool $esRefinanciacion): bool
    {
        return self::conGarantiaMobiliaria($tipoCredito) && ! self::refinanciacionSinDocumentos($esRefinanciacion);
    }

    /** ¿Un préstamo que viene de una refinanciación abre expediente (pide documentos y SIGM propios)? */
    public static function refinanciacionAbreExpediente(): bool
    {
        return (bool) config('expediente.refinanciacion_pide_documentos', false);
    }

    private static function refinanciacionSinDocumentos(bool $esRefinanciacion): bool
    {
        return $esRefinanciacion && ! self::refinanciacionAbreExpediente();
    }

    /**
     * El préstamo de la ruta de cada cliente: su crédito Activo más reciente (por fecha del préstamo; el id
     * desempata), prefiriendo los que NO vienen de una refinanciación.
     *
     * @param  list<int>  $clientIds
     * @return array<int, array{id: int, refinanciacion: bool}> por id de cliente
     */
    private static function creditosEnCurso(array $clientIds): array
    {
        $enCurso = [];
        foreach (DB::table('credits')->whereIn('client_id', $clientIds)->where('situacion', 'Activo')
            ->orderBy('refinanciado')->orderByDesc('fecha_prestamo')->orderByDesc('id')
            ->get(['id', 'client_id', 'refinanciado']) as $credito) {
            $enCurso[(int) $credito->client_id] ??= ['id' => (int) $credito->id, 'refinanciacion' => (bool) $credito->refinanciado];
        }

        return $enCurso;
    }

    /** ¿El "T. Crédito" del cliente lleva garantía mobiliaria (vehículo, documentos, SIGM y GPS)? */
    public static function conGarantiaMobiliaria(?string $tipoCredito): bool
    {
        $tipo = Str::of((string) $tipoCredito)->ascii()->lower()->squish()->toString();
        foreach ((array) config('expediente.tipos_con_garantia_mobiliaria', []) as $inicio) {
            if ($tipo !== '' && str_starts_with($tipo, Str::of((string) $inicio)->ascii()->lower()->toString())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Documentos del paso, en orden, con su nombre: tipo => etiqueta.
     *
     * @return array<string, string>
     */
    public static function documentos(): array
    {
        $out = [];
        foreach ((array) config('expediente.documentos', []) as $tipo) {
            $out[$tipo] = DocumentoCliente::TIPOS[$tipo] ?? $tipo;
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private static function sinDatos(): array
    {
        return ['existe' => false, 'con_garantia' => false, 'abre' => true, 'vehiculos' => 0, 'vehiculos_cliente' => 0, 'con_cuenta_gps' => 0,
            'adjuntos' => 0, 'adjuntos_cliente' => 0, 'credito' => null, 'emitidos' => [], 'constituido' => false];
    }

    /**
     * Convierte los conteos en la ruta: estado y detalle de cada paso, avance y lo que sigue.
     *
     * @param  array{existe: bool, con_garantia: bool, abre: bool, vehiculos: int, vehiculos_cliente: int, con_cuenta_gps: int, adjuntos: int, adjuntos_cliente: int, credito: ?int, emitidos: list<string>, constituido: bool}  $d
     * @return array<string, mixed>
     */
    private static function armar(array $d): array
    {
        // Los pasos de la garantía (vehículo, documentos, SIGM, GPS) se piden si el tipo de crédito la lleva
        // y el préstamo abre expediente (la refinanciación no: su expediente es el del original).
        $garantia = $d['con_garantia'] && $d['abre'];
        $requeridos = array_keys(self::documentos());
        $emitidos = count(array_intersect($requeridos, $d['emitidos']));
        $pideDocumentos = $garantia || $d['emitidos'] !== [];
        $pedir = fn (bool $hecho, bool $aplica) => $hecho ? self::HECHO : ($aplica ? self::PENDIENTE : self::NO_APLICA);
        $plural = fn (int $n, string $uno, string $varios) => $n.' '.($n === 1 ? $uno : $varios);
        $conPrestamo = $d['credito'] !== null;

        $estados = [
            'cliente' => [$d['existe'] ? self::HECHO : self::PENDIENTE, $d['existe'] ? 'Registrado' : 'Datos del cliente'],
            'vehiculo' => [
                $pedir($d['vehiculos'] > 0, $garantia),
                match (true) {
                    $d['vehiculos'] > 0 => $plural($d['vehiculos'], 'vehículo', 'vehículos'),
                    // Tiene vehículos, pero de préstamos anteriores: puede prendar el mismo o registrar otro.
                    $d['vehiculos_cliente'] > 0 => 'Elige el vehículo',
                    default => 'Sin vehículos',
                },
            ],
            'adjuntos' => [
                $pedir($d['adjuntos'] > 0, $d['abre']),
                match (true) {
                    $d['adjuntos'] > 0 => $plural($d['adjuntos'], 'foto', 'fotos'),
                    $d['adjuntos_cliente'] > 0 => 'Faltan las de este préstamo',
                    default => 'Sin fotos',
                },
            ],
            'prestamo' => [
                $conPrestamo ? self::HECHO : self::PENDIENTE,
                $conPrestamo ? '#'.$d['credito'] : 'Sin préstamo activo',
            ],
            'documentos' => [
                $pedir($requeridos !== [] && $emitidos === count($requeridos), $pideDocumentos),
                $conPrestamo ? $emitidos.' de '.count($requeridos) : 'Falta el préstamo',
            ],
            'sigm' => [
                $pedir($d['constituido'], $pideDocumentos),
                $d['constituido'] ? 'Constituido' : ($conPrestamo ? 'Sin constituir' : 'Falta el préstamo'),
            ],
            'gps' => [
                $pedir($d['vehiculos'] > 0 && $d['con_cuenta_gps'] >= $d['vehiculos'], $garantia || $d['con_cuenta_gps'] > 0),
                $d['vehiculos'] === 0 ? 'Falta el vehículo' : $d['con_cuenta_gps'].' de '.$plural($d['vehiculos'], 'placa', 'placas'),
            ],
        ];

        $pasos = [];
        $num = 0;
        foreach (self::PASOS as $clave => [$nombre, $tab]) {
            [$estado, $detalle] = $estados[$clave];
            $pasos[] = [
                'clave' => $clave, 'num' => ++$num, 'nombre' => $nombre, 'tab' => $tab,
                'estado' => $estado, 'detalle' => $estado === self::NO_APLICA ? 'No aplica' : $detalle,
            ];
        }

        return self::resumir($pasos, $d['credito']);
    }

    /**
     * De los pasos ya resueltos saca el avance (los "no aplica" no cuentan) y el que sigue: el primero pendiente.
     *
     * @param  list<array{clave: string, num: int, nombre: string, tab: string, estado: string, detalle: string}>  $pasos
     * @return array<string, mixed>
     */
    private static function resumir(array $pasos, ?int $credito): array
    {
        $siguiente = null;
        foreach ($pasos as $paso) {
            if ($paso['estado'] === self::PENDIENTE) {
                [$nombre, $tab, $accion] = self::PASOS[$paso['clave']];
                $siguiente = ['clave' => $paso['clave'], 'nombre' => $nombre, 'tab' => $tab, 'accion' => $accion];
                break;
            }
        }

        return [
            'pasos' => $pasos,
            'hechos' => count(array_filter($pasos, fn (array $p) => $p['estado'] === self::HECHO)),
            'total' => count(array_filter($pasos, fn (array $p) => $p['estado'] !== self::NO_APLICA)),
            'siguiente' => $siguiente,
            'credito' => $credito,
            'completa' => $siguiente === null,
        ];
    }
}
