<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Moneda base
    |--------------------------------------------------------------------------
    |
    | Código ISO de la moneda en la que se expresan los importes agregados:
    | `sales.total`, `sale_items.subtotal` y todas las cifras de las métricas.
    |
    | Es el valor con el que se siembra la moneda base y el que se usa como
    | respaldo si aún no hay filas en `currencies`. En tiempo de ejecución la
    | autoridad es la fila con `es_base = true` (ver `Currency::base()`), para
    | que cambiar este valor sin migrar los datos no reinterprete en silencio
    | los importes ya guardados.
    |
    */

    'moneda_base' => env('ALMACEN_MONEDA_BASE', 'CUP'),

    /*
    |--------------------------------------------------------------------------
    | Tasas iniciales
    |--------------------------------------------------------------------------
    |
    | Cuántas unidades de la moneda base vale 1 unidad de cada moneda, usadas
    | solo al **sembrar** `currencies`. El seeder no pisa una moneda que ya
    | existe, para no deshacer un ajuste hecho a mano: una vez sembrada, la
    | tasa se administra en la base de datos.
    |
    | El valor por defecto de USD es un marcador de posición: ajústalo a la
    | tasa con la que opere el negocio antes de sembrar en producción.
    |
    */

    'tasas' => [
        'USD' => (float) env('ALMACEN_TASA_USD', 420),
    ],

];
