<?php

namespace Tests\Feature;

use App\Modules\Access\Http\Requests\UpdateUserRequest;
use App\Modules\Catalog\Http\Requests\StoreProductUnitRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * En una petición real el modelo de la ruta siempre llega enlazado, pero
 * `scribe:generate` instancia los Form Requests fuera de una petición para
 * leer sus `rules()`. Si esas reglas desreferencian el modelo sin más, la
 * generación de la documentación revienta.
 */
class FormRequestRulesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<array{class-string<FormRequest>}>
     */
    public static function requestsConModeloDeRuta(): array
    {
        return [
            [UpdateUserRequest::class],
            [StoreProductUnitRequest::class],
        ];
    }

    /**
     * @param  class-string<FormRequest>  $requestClass
     */
    #[DataProvider('requestsConModeloDeRuta')]
    public function test_las_reglas_se_leen_sin_modelo_de_ruta_enlazado(string $requestClass): void
    {
        $rules = (new $requestClass)->rules();

        $this->assertNotEmpty($rules);
    }
}
