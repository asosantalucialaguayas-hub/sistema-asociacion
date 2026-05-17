<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['aplicaciones'])) {
    echo json_encode(['ok' => false, 'msg' => 'Datos inválidos o vacíos']);
    exit;
}

// ── Cargar todos los labels de preguntas de una vez ───────
$labelsMap = []; // id_pregunta → texto (label en minúsculas)
$stLabels  = $pdo->query("SELECT id_pregunta, LOWER(texto) as texto FROM ficha_preguntas");
foreach ($stLabels->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $labelsMap[(int)$row['id_pregunta']] = $row['texto'];
}

// ── Campos fijos que van en ficha_aplicaciones ────────────
// Mapeamos palabras clave del label → columna destino
$camposFijos = [
    'canton'      => ['cantón','canton','ciudad'],
    'parroquia'   => ['parroquia'],
    'sector'      => ['sector','comunidad','zona'],
    'coord_hogar_x' => ['coordenadas hogar','coord hogar','x hogar','x_hogar','hogar x'],
    'coord_hogar_y' => ['y hogar','y_hogar','hogar y'],
    'coord_hogar_z' => ['z hogar','z_hogar','hogar z','altitud hogar'],
    'coord_finca_x' => ['coordenadas finca','coord finca','x finca','x_finca','finca x'],
    'coord_finca_y' => ['y finca','y_finca','finca y'],
    'coord_finca_z' => ['z finca','z_finca','finca z','altitud finca'],
    'cultivo'     => ['cultivo'],
    'variedad'    => ['variedad'],
    'edad_cultivo'=> ['edad del cultivo','edad cultivo','edad'],
    'hectareas'   => ['has','hectáreas','hectareas'],
];

$ids_sincronizados = [];
$errores = [];

foreach ($input['aplicaciones'] as $ap) {
    if (empty($ap['id_socio']) || empty($ap['id_ficha'])) {
        $errores[] = "id_local={$ap['id_local']}: falta id_socio o id_ficha";
        continue;
    }

    $pdo->beginTransaction();
    try {
        $datos = json_decode($ap['datos_json'] ?? '{}', true) ?? [];
        $fecha = $ap['fecha_visita'] ?? date('Y-m-d H:i:s');

        // ── Mapear respuestas a campos fijos ──────────────────
        $fijos = array_fill_keys(array_keys($camposFijos), null);
        $fijos['riego'] = null;
        $fijos['fuente_agua'] = null;
        $fijos['poda_semestre'] = null;

        foreach ($datos as $id_str => $respObj) {
            $id_p = (int)$id_str;
            if ($id_p <= 0 || !is_array($respObj)) continue;

            $label = $labelsMap[$id_p] ?? '';
            $valor = $respObj['texto'] ?? ($respObj['valor'] ?? null);
            $sino  = isset($respObj['sino']) ? (int)$respObj['sino'] : null;

            // Coordenadas: el label puede ser solo "X", "Y", "Z"
            // Necesitamos saber si es de hogar o finca por la sección
            // Por ahora los tomamos en orden: primero X,Y,Z = hogar; segundo X,Y,Z = finca
            // (se maneja abajo con contador)

            // Detectar campo fijo por label
            foreach ($camposFijos as $col => $keywords) {
                foreach ($keywords as $kw) {
                    if (str_contains($label, $kw)) {
                        $fijos[$col] = $valor;
                        break 2;
                    }
                }
            }

            // Riego (si_no)
            if (str_contains($label, 'riego') && $sino !== null) {
                $fijos['riego'] = $sino;
            }
            // Fuente agua
            if (str_contains($label, 'fuente') || str_contains($label, 'agua')) {
                $fijos['fuente_agua'] = $valor;
            }
            // Poda
            if (str_contains($label, 'poda')) {
                $fijos['poda_semestre'] = $valor;
            }
        }

        // Manejar coordenadas X/Y/Z simples (label solo es "X","Y","Z")
        // Recorrer en orden y asignar hogar primero, finca después
        $coordSimples = []; // id_p → valor para labels X,Y,Z
        foreach ($datos as $id_str => $respObj) {
            $id_p  = (int)$id_str;
            $label = $labelsMap[$id_p] ?? '';
            $valor = $respObj['texto'] ?? null;
            if (in_array(trim($label), ['x','y','z']) && $valor !== null) {
                $coordSimples[] = ['label' => trim($label), 'valor' => $valor];
            }
        }
        // Asignar en orden: X→hogar, Y→hogar, Z→hogar, X→finca, Y→finca, Z→finca
        $asignados = ['x'=>0,'y'=>0,'z'=>0];
        foreach ($coordSimples as $cs) {
            $l = $cs['label'];
            if ($asignados[$l] === 0) {
                $fijos["coord_hogar_$l"] = $cs['valor'];
            } elseif ($asignados[$l] === 1) {
                $fijos["coord_finca_$l"] = $cs['valor'];
            }
            $asignados[$l]++;
        }

        // ── INSERT ficha_aplicaciones ─────────────────────────
        $stApp = $pdo->prepare("
            INSERT INTO ficha_aplicaciones
                (id_ficha, id_socio, id_usuario,
                 canton, parroquia, sector,
                 coord_hogar_x, coord_hogar_y, coord_hogar_z,
                 coord_finca_x, coord_finca_y, coord_finca_z,
                 cultivo, variedad, edad_cultivo, hectareas,
                 riego, fuente_agua, poda_semestre,
                 firma_inspector, firma_productor,
                 fecha_aplicacion, sincronizado)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)
        ");
        $stApp->execute([
            (int)$ap['id_ficha'],
            (int)$ap['id_socio'],
            null,
            $fijos['canton'],    $fijos['parroquia'],  $fijos['sector'],
            $fijos['coord_hogar_x'], $fijos['coord_hogar_y'], $fijos['coord_hogar_z'],
            $fijos['coord_finca_x'], $fijos['coord_finca_y'], $fijos['coord_finca_z'],
            $fijos['cultivo'],   $fijos['variedad'],
            $fijos['edad_cultivo'], $fijos['hectareas'],
            $fijos['riego'],     $fijos['fuente_agua'], $fijos['poda_semestre'],
            $ap['firma_inspector'] ?? null,
            $ap['firma_socio']     ?? null,
            $fecha,
        ]);
        $id_aplicacion = (int)$pdo->lastInsertId();

        // ── INSERT ficha_respuestas ───────────────────────────
        $stResp = $pdo->prepare("
            INSERT INTO ficha_respuestas
                (id_aplicacion, id_pregunta, respuesta_sino,
                 cumplimiento, observacion, respuesta_texto)
            VALUES (?,?,?,?,?,?)
        ");

        foreach ($datos as $id_str => $respObj) {
            $id_p = (int)$id_str;
            if ($id_p <= 0 || !is_array($respObj)) continue;

            // sino: 0=No 1=Sí 2=Aplica → en BD guardamos 0,1,null(Aplica)
            $sino = null;
            if (array_key_exists('sino', $respObj)) {
                $v = (int)$respObj['sino'];
                $sino = ($v === 2) ? null : $v;
            }

            $cumpl = null;
            if (!empty($respObj['cumplimiento'])) {
                $c = strtoupper(trim($respObj['cumplimiento']));
                if (in_array($c, ['B','R','M'])) $cumpl = $c;
            }

            $obs = $respObj['observacion'] ?? null;
            $txt = $respObj['texto']       ?? ($respObj['valor'] ?? null);

            $stResp->execute([
                $id_aplicacion, $id_p,
                $sino, $cumpl, $obs, $txt,
            ]);
        }

        // ── Respaldo en visitas_fichas ────────────────────────
        $pdo->prepare("
            INSERT INTO visitas_fichas
                (id_socio, id_ficha, datos_json, firma_inspector,
                 firma_socio, fecha_visita, sincronizado_en)
            VALUES (?,?,?,?,?,?,NOW())
        ")->execute([
            (int)$ap['id_socio'],
            (int)$ap['id_ficha'],
            $ap['datos_json'] ?? '{}',
            $ap['firma_inspector'] ?? null,
            $ap['firma_socio']     ?? null,
            $fecha,
        ]);

        $pdo->commit();
        $ids_sincronizados[] = $ap['id_local'];

    } catch (Exception $e) {
        $pdo->rollBack();
        $errores[] = "id_local={$ap['id_local']}: " . $e->getMessage();
    }
}

echo json_encode([
    'ok'      => !empty($ids_sincronizados),
    'ids'     => $ids_sincronizados,
    'msg'     => count($ids_sincronizados) . ' ficha(s) sincronizada(s)'
               . (empty($errores) ? '' : ' | Errores: ' . implode('; ', $errores)),
    'errores' => $errores,
]);