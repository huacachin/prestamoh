<?php

namespace Tests\Unit\Legal;

use App\Support\Legal\Genero;
use PHPUnit\Framework\TestCase;

/**
 * Concordancia gramatical de los contratos: una instancia por deudor (M/F)
 * y una del conjunto para las menciones colectivas. Reglas: el plural con al
 * menos un 'M' es masculino (LOS DEUDORES), solo mujeres da LAS DEUDORAS, y
 * la persona jurídica se redacta siempre como femenino singular (LA DEUDORA
 * = la empresa) sin importar el sexo registrado del representante.
 */
class GeneroTest extends TestCase
{
    public function test_deudor_tabla_de_verdad(): void
    {
        $this->assertSame('EL DEUDOR', Genero::de('M')->deudor());
        $this->assertSame('LA DEUDORA', Genero::de('F')->deudor());
        // Plural mixto → masculino
        $this->assertSame('LOS DEUDORES', Genero::conjunto(['M', 'F'])->deudor());
        // Solo mujeres → femenino plural
        $this->assertSame('LAS DEUDORAS', Genero::conjunto(['F', 'F'])->deudor());
        // Persona jurídica: femenino singular aunque el sexo no sea 'F'
        $this->assertSame('LA DEUDORA', Genero::de('X', juridica: true)->deudor());
    }

    public function test_deudor_solo_sin_articulo_para_titulos(): void
    {
        $this->assertSame('DEUDOR', Genero::de('M')->deudorSolo());
        $this->assertSame('DEUDORA', Genero::de('F')->deudorSolo());
        $this->assertSame('DEUDORES', Genero::conjunto(['M', 'F'])->deudorSolo());
        $this->assertSame('DEUDORAS', Genero::conjunto(['F', 'F'])->deudorSolo());
    }

    public function test_identificado_flexiona_genero_y_numero(): void
    {
        $this->assertSame('identificado', Genero::de('M')->identificado());
        $this->assertSame('identificada', Genero::de('F')->identificado());
        $this->assertSame('identificados', Genero::conjunto(['M', 'F'])->identificado());
        $this->assertSame('identificadas', Genero::conjunto(['F', 'F'])->identificado());
        // El error del modelo Word ("GINA ... IDENTIFICADO") queda imposible
        $this->assertSame('identificada', Genero::de('M', juridica: true)->identificado());
    }

    public function test_propietario_respeta_mayusculas(): void
    {
        $this->assertSame('PROPIETARIO', Genero::de('M')->propietario());
        $this->assertSame('PROPIETARIA', Genero::de('F')->propietario());
        $this->assertSame('PROPIETARIOS', Genero::conjunto(['M', 'M'])->propietario());
        $this->assertSame('PROPIETARIAS', Genero::conjunto(['F', 'F'])->propietario());
    }

    public function test_articulos_el_del_al(): void
    {
        $m = Genero::de('M');
        $f = Genero::de('F');
        $mp = Genero::conjunto(['M', 'F']);
        $fp = Genero::conjunto(['F', 'F']);

        $this->assertSame('el', $m->el());
        $this->assertSame('la', $f->el());
        $this->assertSame('los', $mp->el());
        $this->assertSame('las', $fp->el());

        $this->assertSame('del', $m->del());
        $this->assertSame('de la', $f->del());
        $this->assertSame('de los', $mp->del());
        $this->assertSame('de las', $fp->del());

        $this->assertSame('al', $m->al());
        $this->assertSame('a la', $f->al());
        $this->assertSame('a los', $mp->al());
        $this->assertSame('a las', $fp->al());
    }

    public function test_senor_en_las_cuatro_formas(): void
    {
        $this->assertSame('el señor', Genero::de('M')->senor());
        $this->assertSame('la señora', Genero::de('F')->senor());
        $this->assertSame('los señores', Genero::conjunto(['M', 'F'])->senor());
        $this->assertSame('las señoras', Genero::conjunto(['F', 'F'])->senor());
    }

    public function test_quien_su_firma_declara_solo_dependen_del_numero(): void
    {
        $singular = Genero::de('F');
        $plural = Genero::conjunto(['M', 'F']);

        $this->assertSame('quien', $singular->quien());
        $this->assertSame('quienes', $plural->quien());

        $this->assertSame('su', $singular->su());
        $this->assertSame('sus', $plural->su());

        $this->assertSame('firma', $singular->firma());
        $this->assertSame('firman', $plural->firma());

        $this->assertSame('declara', $singular->declara());
        $this->assertSame('declaran', $plural->declara());
    }

    public function test_flex_regular_de_palabras_en_o(): void
    {
        $this->assertSame('obligado', Genero::de('M')->flex('obligado'));
        $this->assertSame('obligada', Genero::de('F')->flex('obligado'));
        $this->assertSame('obligados', Genero::conjunto(['M', 'F'])->flex('obligado'));
        $this->assertSame('obligadas', Genero::conjunto(['F', 'F'])->flex('obligado'));
    }

    public function test_verbo_elige_singular_o_plural(): void
    {
        $this->assertSame('otorga', Genero::de('M')->verbo('otorga', 'otorgan'));
        $this->assertSame('otorga', Genero::de('F')->verbo('otorga', 'otorgan'));
        $this->assertSame('otorgan', Genero::conjunto(['M', 'F'])->verbo('otorga', 'otorgan'));
    }
}
