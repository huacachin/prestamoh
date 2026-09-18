<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\Documentos\GeneradorAnexo1;
use App\Services\Documentos\RenderDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** El Anexo 1 debe caber en UNA hoja (pedido 28/08), con 1 o varios vehículos. */
class Anexo1UnaHojaTest extends TestCase
{
    use RefreshDatabase;

    private function mundo(int $cuotas, int $vehiculos = 1, bool $conCodeudor = false): array
    {
        $sufijo = $cuotas.'-'.$vehiculos.($conCodeudor ? '-c' : '');
        $this->actingAs(User::factory()->create(['username' => 'anx-hoja-'.$sufijo]));
        $client = Client::create([
            'expediente' => (string) (700 + $cuotas * 10 + $vehiculos + ($conCodeudor ? 5000 : 0)), 'nombre' => 'Cliente De Prueba Con Nombre Largo',
            'apellido_pat' => 'Apellido', 'apellido_mat' => 'Materno',
            'tipo_documento' => 'DNI', 'documento' => str_pad((string) (10000000 + $cuotas * 100 + $vehiculos + ($conCodeudor ? 500000 : 0)), 8, '0', STR_PAD_LEFT), 'sexo' => 'M', 'status' => 'active',
            // Peor caso REAL (16/09): con esta dirección de 6 líneas, 28 cuotas
            // se salían a una segunda hoja y la última fila pisaba el pie. El
            // fixture anterior traía una dirección corta y no lo veía.
            'direccion' => 'CA. LOS GERANIOS MZ. J LT. 12 ASOC. UNIÓN SANTA CRUZ DE CAJAMARQUILLA, '
                .'LURIGANCHO, DISTRITO DE LURIGANCHO, PROVINCIA Y DEPARTAMENTO DE LIMA',
            'email' => 'adonishugolaurentelliuyacc@gmail.com', 'celular1' => '999888777',
        ]);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => now()->format('Y-m-d'),
            'importe' => 20000, 'cuotas' => $cuotas, 'tipo_planilla' => 1, 'interes' => 10,
            'interes_total' => 2000, 'situacion' => 'Activo', 'estado' => 1,
        ]);
        for ($i = 1; $i <= $cuotas; $i++) {
            CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $i,
                'fecha_vencimiento' => now()->addWeeks($i)->format('Y-m-d'),
                'importe_cuota' => 400, 'importe_interes' => 50, 'pagado' => 0,
            ]);
        }
        $lista = collect();
        for ($v = 1; $v <= $vehiculos; $v++) {
            $lista->push(Vehiculo::create([
                'client_id' => $client->id, 'placa' => "P{$sufijo}X{$v}",
                'marca' => 'Mercedes Benz', 'modelo' => 'Sprinter 515',
                'nro_serie' => "9BM3840{$cuotas}{$vehiculos}{$v}KB1234".($conCodeudor ? 'C' : ''), 'valor' => 45000 + $v,
            ]));
        }

        // 18/09: el codeudor del Anexo 1 es el COPROPIETARIO de un vehículo
        // anexado. Se le da la MISMA dirección larga que al titular: es el
        // caso real (conviven) y el peor para el alto de la cabecera, porque
        // con dos columnas cada dirección se parte en más renglones.
        if ($conCodeudor && $lista->isNotEmpty()) {
            $codeudor = Client::create([
                'expediente' => (string) (9000 + $cuotas * 10 + $vehiculos),
                'nombre' => 'Codeudora De Prueba Con Nombre Largo',
                'apellido_pat' => 'Segundo', 'apellido_mat' => 'Deudor',
                'tipo_documento' => 'DNI',
                'documento' => str_pad((string) (20000000 + $cuotas * 100 + $vehiculos), 8, '0', STR_PAD_LEFT),
                'sexo' => 'F', 'status' => 'active',
                'direccion' => 'CA. LOS GERANIOS MZ. J LT. 12 ASOC. UNIÓN SANTA CRUZ DE CAJAMARQUILLA, '
                    .'LURIGANCHO, DISTRITO DE LURIGANCHO, PROVINCIA Y DEPARTAMENTO DE LIMA',
                'email' => 'miguelalcides.mejiavillanueva@gmail.com', 'celular1' => '953243546',
            ]);
            $lista->first()->copropietarios()->attach($codeudor->id);
            $lista = $lista->map(fn ($v) => $v->load('copropietarios'));
        }

        return [$client, $credit, $lista];
    }

    private function paginas(string $pdf): int
    {
        // dompdf escribe /Type /Page por página; /Pages es el nodo raíz.
        return preg_match_all('#/Type\s*/Page[^s]#', $pdf);
    }

    /**
     * Hasta dónde llega el contenido por la DERECHA, en puntos.
     *
     * Contar páginas no ve el otro desborde: salirse de la hoja a lo ancho no
     * agrega hojas, simplemente imprime cortado. Así se descubrió el 18/09 que
     * el cronograma a cuatro columnas llegaba a 614,9 pt —fuera del papel—
     * desde que existe el reparto en columnas, sin que ningún test chistara.
     *
     * Se leen los flujos de contenido del PDF y se toma la X mayor de los
     * rectángulos (bordes de tabla) y de los operadores de posición de texto.
     */
    private function bordeDerecho(string $pdf): float
    {
        $max = 0.0;
        preg_match_all('/stream\r?\n(.*?)endstream/s', $pdf, $m);
        foreach ($m[1] as $crudo) {
            $texto = @gzuncompress(trim($crudo, "\r\n"));
            if ($texto === false) {
                continue;
            }
            preg_match_all('/([\d.-]+)\s+([\d.-]+)\s+([\d.-]+)\s+([\d.-]+)\s+re/', $texto, $r, PREG_SET_ORDER);
            foreach ($r as $op) {
                $max = max($max, (float) $op[1] + (float) $op[3]);
            }
            preg_match_all('/([\d.-]+)\s+([\d.-]+)\s+(?:Td|Tm)/', $texto, $t, PREG_SET_ORDER);
            foreach ($t as $op) {
                $max = max($max, (float) $op[1]);
            }
        }

        return round($max, 1);
    }

    /**
     * Hasta dónde PUEDE llegar el contenido, leído de la propia plantilla.
     *
     * No se fija a mano: la plantilla declara su caja de página en
     * `@page { margin: sup der inf izq }` y de ahí sale el borde. Así, si
     * alguien cambia los márgenes del anexo, el test sigue midiendo contra
     * los de verdad en vez de contra un número copiado.
     */
    private function bordeUtil(array $snapshot): float
    {
        $html = RenderDocumento::html($snapshot, 'anexo1', 'pdf');
        $this->assertMatchesRegularExpression('/@page\s*\{\s*margin:/', $html, 'la plantilla ya no declara @page');
        preg_match('/@page\s*\{\s*margin:\s*([\d.]+)cm\s+([\d.]+)cm/', $html, $m);
        $this->assertNotEmpty($m, 'no se pudo leer el margen derecho de la plantilla');

        // A4 son 595,28 pt de ancho; 1 cm son 28,3465 pt.
        return round(595.28 - (float) $m[2] * 28.3465, 1);
    }

    /** Una hoja Y dentro del margen: las dos reglas, en una sola aserción. */
    private function assertCabeEnLaHoja(string $pdf, array $snapshot, string $caso): void
    {
        $this->assertSame(1, $this->paginas($pdf), "{$caso}: debe caber en 1 hoja");
        $borde = $this->bordeUtil($snapshot);
        $this->assertLessThanOrEqual($borde + 0.5, $this->bordeDerecho($pdf),
            "{$caso}: el contenido se sale por la derecha del área útil ({$borde} pt)");
    }

    public function test_una_hoja_con_cronogramas_de_distinto_largo(): void
    {
        Storage::fake('public');

        foreach ([4, 12, 24, 26, 28, 30, 32, 36, 48, 72, 96] as $cuotas) {
            [$client, $credit, $vehiculos] = $this->mundo($cuotas);
            $doc = app(GeneradorAnexo1::class)->generar($client, $credit, $vehiculos);
            $pdf = Storage::disk('public')->get($doc->pdf_path);

            $this->assertCabeEnLaHoja($pdf, $doc->snapshot, "con {$cuotas} cuotas");
        }
    }

    /**
     * La altura disponible depende de los DATOS: una dirección larga o un
     * segundo vehículo empujan el cronograma hacia abajo. El barrido de arriba
     * usa el peor caso; este recorre las combinaciones que lo rodean, que es
     * donde el corte fijo anterior fallaba sin que nadie lo viera.
     */
    public function test_una_hoja_en_las_combinaciones_de_datos(): void
    {
        foreach ([1, 2, 3] as $vehiculos) {
            foreach ([4, 20, 24, 28, 36, 48, 72] as $cuotas) {
                [$client, $credit, $vs] = $this->mundo($cuotas, $vehiculos);
                $doc = GeneradorAnexo1::generar($client, $credit, $vs);
                $this->assertCabeEnLaHoja(Storage::disk('public')->get($doc->pdf_path),
                    $doc->snapshot, "con {$vehiculos} vehículo(s) y {$cuotas} cuotas");
            }
        }
    }

    public function test_una_hoja_con_tres_vehiculos_y_48_cuotas(): void
    {
        Storage::fake('public');
        [$client, $credit, $vehiculos] = $this->mundo(48, 3);

        $doc = app(GeneradorAnexo1::class)->generar($client, $credit, $vehiculos);
        $pdf = Storage::disk('public')->get($doc->pdf_path);

        $this->assertCount(3, $doc->snapshot['vehiculos']);
        $this->assertCabeEnLaHoja($pdf, $doc->snapshot, 'con 3 vehículos y 48 cuotas');
    }

    /**
     * CON CODEUDOR (18/09) la cabecera se lleva una columna más: las
     * direcciones se parten en más renglones y el cronograma arranca más
     * abajo. Es el caso que puede empujar el anexo a una segunda hoja, así
     * que se barre igual que el del titular solo.
     */
    public function test_una_hoja_con_codeudor(): void
    {
        Storage::fake('public');

        foreach ([1, 2, 3] as $vehiculos) {
            foreach ([4, 20, 24, 28, 36, 48, 72] as $cuotas) {
                [$client, $credit, $vs] = $this->mundo($cuotas, $vehiculos, conCodeudor: true);
                $doc = GeneradorAnexo1::generar($client, $credit, $vs);

                $this->assertCount(2, $doc->snapshot['clientes'],
                    "con {$vehiculos} vehículo(s) el anexo debe llevar titular y codeudor");
                $this->assertCabeEnLaHoja(Storage::disk('public')->get($doc->pdf_path),
                    $doc->snapshot, "con codeudor, {$vehiculos} vehículo(s) y {$cuotas} cuotas");
            }
        }
    }

    /** El documento imprime los DOS deudores completos, como el maestro. */
    public function test_el_documento_trae_los_datos_de_ambos_deudores(): void
    {
        Storage::fake('public');
        [$client, $credit, $vs] = $this->mundo(28, 1, conCodeudor: true);

        $doc = GeneradorAnexo1::generar($client, $credit, $vs);
        [$titular, $codeudor] = $doc->snapshot['clientes'];

        // Nombre, DNI, dirección, celular y correo de cada uno, cada dato con
        // el valor de SU ficha (no el del titular repetido).
        foreach (['nombre', 'documento', 'domicilio', 'celular', 'correo'] as $campo) {
            $this->assertNotSame('', trim((string) $titular[$campo]), "falta {$campo} del titular");
            $this->assertNotSame('', trim((string) $codeudor[$campo]), "falta {$campo} del codeudor");
        }
        // Los propios de cada persona sí difieren. El DOMICILIO no entra: en el
        // caso real —y en el maestro del área— los dos deudores conviven y la
        // dirección es la misma.
        foreach (['nombre', 'documento', 'celular', 'correo'] as $campo) {
            $this->assertNotSame($titular[$campo], $codeudor[$campo], "{$campo} no puede ser el mismo en ambos");
        }

        // Y salen los dos en la hoja, bajo la cabecera en PLURAL.
        $html = RenderDocumento::html($doc->snapshot, 'anexo1', 'pdf');
        $this->assertStringContainsString('DATOS DE LOS CLIENTES', $html);
        $this->assertStringContainsString('>Clientes<', $html);
        foreach ([$titular, $codeudor] as $c) {
            $this->assertStringContainsString($c['nombre'], $html);
            $this->assertStringContainsString($c['documento'], $html);
            $this->assertStringContainsString($c['celular'], $html);
            $this->assertStringContainsString($c['correo'], $html);
        }
    }

    /**
     * Si los deudores no comparten tipo de documento, el número de cada uno
     * NO puede salir bajo la etiqueta del otro: el anexo se firma ante
     * notaría y ahí un carné de extranjería rotulado 'DNI' es un dato falso.
     */
    public function test_con_tipos_de_documento_distintos_cada_numero_lleva_el_suyo(): void
    {
        Storage::fake('public');
        [$client, $credit, $vs] = $this->mundo(28, 1, conCodeudor: true);
        $vs->first()->copropietarios->first()->update(['tipo_documento' => 'CE']);
        $vs = $vs->map(fn ($v) => $v->load('copropietarios'));

        $doc = GeneradorAnexo1::generar($client, $credit, $vs);
        [$titular, $codeudor] = $doc->snapshot['clientes'];
        $html = RenderDocumento::html($doc->snapshot, 'anexo1', 'pdf');

        $this->assertSame('CE', $codeudor['documento_tipo']);
        // Rótulo genérico y cada número con su tipo delante.
        $this->assertStringContainsString('>Documento<', $html);
        $this->assertStringContainsString('DNI '.$titular['documento'], $html);
        $this->assertStringContainsString('CE '.$codeudor['documento'], $html);
    }

    /** Compartiendo tipo, el maestro rotula una sola vez y el número va solo. */
    public function test_con_el_mismo_tipo_la_fila_se_rotula_una_vez(): void
    {
        Storage::fake('public');
        [$client, $credit, $vs] = $this->mundo(28, 1, conCodeudor: true);

        $html = RenderDocumento::html(GeneradorAnexo1::generar($client, $credit, $vs)->snapshot, 'anexo1', 'pdf');

        $this->assertStringContainsString('>DNI<', $html);
        $this->assertStringNotContainsString('>Documento<', $html);
    }

    /** Sin codeudor nada cambia: cabecera en singular y una sola columna. */
    public function test_sin_codeudor_la_cabecera_sigue_en_singular(): void
    {
        Storage::fake('public');
        [$client, $credit, $vs] = $this->mundo(28, 1);

        $doc = GeneradorAnexo1::generar($client, $credit, $vs);
        $html = RenderDocumento::html($doc->snapshot, 'anexo1', 'pdf');

        $this->assertCount(1, $doc->snapshot['clientes']);
        $this->assertStringContainsString('DATOS DEL CLIENTE', $html);
        $this->assertStringNotContainsString('DATOS DE LOS CLIENTES', $html);
        $this->assertStringContainsString('>Cliente<', $html);
    }

    /** El interruptor del modal deja fuera al codeudor. */
    public function test_se_puede_emitir_solo_con_el_titular(): void
    {
        Storage::fake('public');
        [$client, $credit, $vs] = $this->mundo(28, 1, conCodeudor: true);

        $doc = GeneradorAnexo1::generar($client, $credit, $vs, ['sin_codeudores' => true]);
        $html = RenderDocumento::html($doc->snapshot, 'anexo1', 'pdf');

        $this->assertCount(1, $doc->snapshot['clientes']);
        $this->assertStringContainsString('DATOS DEL CLIENTE', $html);
        $this->assertStringNotContainsString('DATOS DE LOS CLIENTES', $html);
    }

    /**
     * Los anexos emitidos ANTES del 18/09 no tienen 'clientes' en su snapshot:
     * tienen que seguir imprimiéndose igual, con una sola columna.
     */
    public function test_los_snapshots_viejos_se_siguen_viendo(): void
    {
        Storage::fake('public');
        [$client, $credit, $vs] = $this->mundo(28, 1);
        $snapshot = GeneradorAnexo1::construirSnapshot($client, $credit, $vs);
        unset($snapshot['clientes']);   // como los emitidos antes del cambio

        $html = RenderDocumento::html($snapshot, 'anexo1', 'pdf');

        $this->assertStringContainsString('DATOS DEL CLIENTE', $html);
        $this->assertStringContainsString($snapshot['cliente']['nombre'], $html);
    }
}
