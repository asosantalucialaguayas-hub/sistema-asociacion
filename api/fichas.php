<?php
require_once __DIR__ . '/config.php';

try {
    // ── 1. Traer todas las fichas activas ──────────────────────
    $stFichas = $pdo->query("
        SELECT id_ficha, nombre, descripcion, activa
        FROM fichas
        WHERE activa = 1
        ORDER BY id_ficha ASC
    ");
    $fichas = $stFichas->fetchAll(PDO::FETCH_ASSOC);

    $resultado = [];

    foreach ($fichas as $ficha) {
        $id_ficha = (int)$ficha['id_ficha'];

        // ── 2. Traer secciones de esta ficha ──────────────────
        $stSecs = $pdo->prepare("
            SELECT id_seccion, titulo, orden
            FROM ficha_secciones
            WHERE id_ficha = ?
            ORDER BY orden ASC
        ");
        $stSecs->execute([$id_ficha]);
        $secciones = $stSecs->fetchAll(PDO::FETCH_ASSOC);

        $secciones_out = [];

        foreach ($secciones as $sec) {
            $id_seccion = (int)$sec['id_seccion'];

            // ── 3. Traer preguntas de esta sección ────────────
            $stPregs = $pdo->prepare("
                SELECT id_pregunta, texto, tipo, opciones_json, orden
                FROM ficha_preguntas
                WHERE id_seccion = ?
                ORDER BY orden ASC
            ");
            $stPregs->execute([$id_seccion]);
            $preguntas = $stPregs->fetchAll(PDO::FETCH_ASSOC);

            $campos = [];
            foreach ($preguntas as $preg) {
                $tipo = $preg['tipo'] ?: 'texto';

                $campo = [
                    'id'    => (int)$preg['id_pregunta'],
                    'label' => $preg['texto'],
                    'tipo'  => $tipo,
                ];

                // Si tiene opciones JSON, decodificarlas
                if (!empty($preg['opciones_json'])) {
                    $opts = json_decode($preg['opciones_json'], true);
                    if ($opts) $campo['opciones'] = $opts;
                }

                $campos[] = $campo;
            }

            $secciones_out[] = [
                'id'     => $id_seccion,
                'titulo' => $sec['titulo'],
                'orden'  => (int)$sec['orden'],
                'campos' => $campos,
            ];
        }

        $resultado[] = [
            'id'          => $id_ficha,
            'nombre'      => $ficha['nombre'],
            'descripcion' => $ficha['descripcion'] ?? '',
            'activa'      => (bool)$ficha['activa'],
            'version'     => '2.0',
            'secciones'   => $secciones_out,
        ];
    }

    echo json_encode(['ok' => true, 'data' => $resultado]);

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}