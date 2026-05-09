<?php
require_once __DIR__ . '/config.php';

try {
    // Por ahora devuelve la ficha Fairtrade hardcodeada
    // Después la haremos dinámica desde BD
    $fichas = [
        [
            'id'          => 1,
            'nombre'      => 'Ficha Fairtrade',
            'descripcion' => 'Ficha de datos generales de productores y fincas',
            'version'     => '1.0',
            'activa'      => true,
            'secciones'   => [
                [
                    'titulo' => 'Datos del Cultivo',
                    'campos' => [
                        ['id'=>'cultivo',  'label'=>'Cultivo',         'tipo'=>'texto'],
                        ['id'=>'variedad', 'label'=>'Variedad',        'tipo'=>'texto'],
                        ['id'=>'edad',     'label'=>'Edad del Cultivo','tipo'=>'numero'],
                        ['id'=>'has',      'label'=>'Has',             'tipo'=>'numero'],
                        ['id'=>'riego',    'label'=>'Riego',           'tipo'=>'radio',
                         'opciones'=>['Sí','No']],
                        ['id'=>'fuente_agua','label'=>'Fuente de agua','tipo'=>'radio',
                         'opciones'=>['Pozo','Albarrada']],
                        ['id'=>'poda',     'label'=>'Poda',            'tipo'=>'radio',
                         'opciones'=>['1er semestre','2do semestre']],
                    ]
                ],
                [
                    'titulo' => 'Campo Definitivo',
                    'campos' => [
                        ['id'=>'plantacion_nueva',   'label'=>'Tiene plantación nueva',        'tipo'=>'checklist'],
                        ['id'=>'sombra_temporal',    'label'=>'Hay sombra temporal y permanente','tipo'=>'checklist'],
                    ]
                ],
                [
                    'titulo' => 'Conservación de Suelos',
                    'campos' => [
                        ['id'=>'barreras_vivas',  'label'=>'Barreras Vivas',  'tipo'=>'checklist'],
                        ['id'=>'barreras_muertas','label'=>'Barreras muertas','tipo'=>'checklist'],
                        ['id'=>'cobertura_muerta','label'=>'Cobertura muerta','tipo'=>'checklist'],
                        ['id'=>'cobertura_viva',  'label'=>'Cobertura viva',  'tipo'=>'checklist'],
                    ]
                ],
                [
                    'titulo' => 'Fertilización',
                    'campos' => [
                        ['id'=>'organica', 'label'=>'Orgánica', 'tipo'=>'checklist'],
                        ['id'=>'bioles',   'label'=>'Bioles',   'tipo'=>'checklist'],
                        ['id'=>'humus',    'label'=>'Humus',    'tipo'=>'checklist'],
                        ['id'=>'quimica',  'label'=>'Química',  'tipo'=>'checklist'],
                    ]
                ],
                [
                    'titulo' => 'Control de Plagas y Enfermedades',
                    'campos' => [
                        ['id'=>'moniliasis',  'label'=>'Moniliasis',  'tipo'=>'checklist'],
                        ['id'=>'escoba_bruja','label'=>'Escoba de Bruja','tipo'=>'checklist'],
                        ['id'=>'phytopthora', 'label'=>'Phytopthora', 'tipo'=>'checklist'],
                    ]
                ],
                [
                    'titulo' => 'Control de Malezas',
                    'campos' => [
                        ['id'=>'maleza_manual',   'label'=>'Manual',   'tipo'=>'checklist'],
                        ['id'=>'maleza_mecanica', 'label'=>'Mecánica', 'tipo'=>'checklist'],
                        ['id'=>'maleza_quimica',  'label'=>'Química',  'tipo'=>'checklist'],
                    ]
                ],
                [
                    'titulo' => 'Bodega',
                    'campos' => [
                        ['id'=>'senaleticas',       'label'=>'Señaléticas',                'tipo'=>'checklist'],
                        ['id'=>'almacena_quimicos', 'label'=>'Almacena químicos',          'tipo'=>'checklist'],
                        ['id'=>'mantiene_ordenado', 'label'=>'Mantiene ordenado materiales','tipo'=>'checklist'],
                    ]
                ],
                [
                    'titulo' => 'Varios',
                    'campos' => [
                        ['id'=>'compra_cacao',      'label'=>'Compra cacao a terceros',         'tipo'=>'checklist'],
                        ['id'=>'servicios_basicos', 'label'=>'Tiene servicios básicos',         'tipo'=>'checklist'],
                        ['id'=>'ninos_menores',     'label'=>'Tiene niños menores de edad',     'tipo'=>'checklist'],
                        ['id'=>'estudian_menores',  'label'=>'Estudian los menores de edad',    'tipo'=>'checklist'],
                        ['id'=>'botiquin',          'label'=>'Tiene botiquín de primeros auxilios','tipo'=>'checklist'],
                        ['id'=>'conoce_certificacion','label'=>'Conoce la importancia de la certificación','tipo'=>'checklist'],
                        ['id'=>'conoce_tecnico',    'label'=>'Conoce a su técnico',             'tipo'=>'checklist'],
                        ['id'=>'agroquimico',       'label'=>'Aplicó algún agroquímico últimamente','tipo'=>'checklist'],
                        ['id'=>'trabajadores_permanentes','label'=>'Tiene trabajadores permanentes','tipo'=>'checklist'],
                    ]
                ]
            ]
        ]
    ];

    echo json_encode(['ok' => true, 'data' => $fichas]);

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}