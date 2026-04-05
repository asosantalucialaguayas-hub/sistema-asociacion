<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}
require __DIR__ . "/config/conexion.php";

try {
    $body    = json_decode(file_get_contents('php://input'), true);
    $seccion = $body['seccion'] ?? '';

    // ── SECCIÓN: nombre completo del socio ───────────────────────────────────
    if ($seccion === 'nombre') {
        $id = intval($body['id_socio'] ?? 0);
        if (!$id) { echo json_encode(['success'=>false,'message'=>'ID requerido']); exit; }
        $nombre = trim($body['nombre_completo'] ?? '');
        if (!$nombre) { echo json_encode(['success'=>false,'message'=>'Nombre requerido']); exit; }

        $pdo->prepare("
            UPDATE socios SET nombre_completo = :nombre WHERE id_socio = :id
        ")->execute([':nombre' => $nombre, ':id' => $id]);

        echo json_encode(['success'=>true,'message'=>'Nombre actualizado']);
        exit;
    }

    // ── SECCIÓN: datos personales del socio ──────────────────────────────────
    if ($seccion === 'socio') {
        $id = intval($body['id_socio'] ?? 0);
        if (!$id) { echo json_encode(['success'=>false,'message'=>'ID requerido']); exit; }

        // Actualizar datos en tabla socios
        $pdo->prepare("
            UPDATE socios SET
                telefono         = :telefono,
                correo           = :correo,
                sexo             = :sexo,
                fecha_nacimiento = :fnac,
                fecha_ingreso    = :fingreso,
                direccion        = :direccion,
                estado           = :estado
            WHERE id_socio = :id
        ")->execute([
            ':telefono'  => $body['telefono']         ?? '',
            ':correo'    => $body['correo']            ?? '',
            ':sexo'      => $body['sexo']              ?? '',
            ':fnac'      => $body['fecha_nacimiento']  ?? '',
            ':fingreso'  => $body['fecha_ingreso']     ?? null,
            ':direccion' => $body['direccion']         ?? '',
            ':estado'    => $body['estado']            ?? 'activo',
            ':id'        => $id,
        ]);

        // Si tiene LPA, actualizar también tabla_lpa.fecha_ingreso
        $fechaIngreso = $body['fecha_ingreso'] ?? '';
        if ($fechaIngreso) {
            $pdo->prepare("
                UPDATE tabla_lpa
                SET fecha_ingreso = :fi
                WHERE id_socio = :id
                ORDER BY id_lpa DESC
                LIMIT 1
            ")->execute([':fi' => $fechaIngreso, ':id' => $id]);
        }

        echo json_encode(['success'=>true,'message'=>'Datos del socio actualizados']);
        exit;
    }

    // ── SECCIÓN: coordenadas GPS ─────────────────────────────────────────────
    if ($seccion === 'coordenadas') {
        $id = intval($body['id_socio'] ?? 0);
        if (!$id) { echo json_encode(['success'=>false,'message'=>'ID requerido']); exit; }

        $lat = trim($body['latitud']  ?? '');
        $lng = trim($body['longitud'] ?? '');

        $pdo->prepare("
            UPDATE socios SET
                latitud  = :lat,
                longitud = :lng
            WHERE id_socio = :id
        ")->execute([
            ':lat' => ($lat !== '') ? floatval($lat) : null,
            ':lng' => ($lng !== '') ? floatval($lng) : null,
            ':id'  => $id,
        ]);

        echo json_encode(['success'=>true,'message'=>'Coordenadas actualizadas']);
        exit;
    }

    // ── SECCIÓN: ubicación del acuerdo ───────────────────────────────────────
    if ($seccion === 'ubicacion') {
        $id = intval($body['id_socio'] ?? 0);
        $stCheck = $pdo->prepare("SELECT id_acuerdo FROM acuerdo_productor WHERE id_socio=? ORDER BY id_acuerdo DESC LIMIT 1");
        $stCheck->execute([$id]);
        $idAc = $stCheck->fetchColumn();
        if (!$idAc) { echo json_encode(['success'=>false,'message'=>'Sin acuerdo']); exit; }

        $pdo->prepare("
            UPDATE acuerdo_productor SET
                provincia = :provincia,
                canton    = :canton,
                parroquia = :parroquia,
                sector    = :sector
            WHERE id_acuerdo = :id
        ")->execute([
            ':provincia' => $body['provincia'] ?? '',
            ':canton'    => $body['canton']    ?? '',
            ':parroquia' => $body['parroquia'] ?? '',
            ':sector'    => $body['sector']    ?? '',
            ':id'        => $idAc,
        ]);
        echo json_encode(['success'=>true,'message'=>'Ubicación actualizada']);
        exit;
    }

    // ── SECCIÓN: cacao ───────────────────────────────────────────────────────
    if ($seccion === 'cacao') {
        $id = intval($body['id_socio'] ?? 0);
        $stCheck = $pdo->prepare("SELECT id_acuerdo FROM acuerdo_productor WHERE id_socio=? ORDER BY id_acuerdo DESC LIMIT 1");
        $stCheck->execute([$id]);
        $idAc = $stCheck->fetchColumn();
        if (!$idAc) { echo json_encode(['success'=>false,'message'=>'Sin acuerdo']); exit; }

        $pdo->prepare("
            UPDATE acuerdo_productor SET
                cacao_nacional_has           = :cn_ha,
                estimado_produccion_nacional = :cn_qq,
                cacao_ccn51_has              = :cc_ha,
                estimado_produccion_ccn51    = :cc_qq
            WHERE id_acuerdo = :id
        ")->execute([
            ':cn_ha' => floatval($body['cacao_nacional_has']           ?? 0),
            ':cn_qq' => floatval($body['estimado_produccion_nacional']  ?? 0),
            ':cc_ha' => floatval($body['cacao_ccn51_has']               ?? 0),
            ':cc_qq' => floatval($body['estimado_produccion_ccn51']     ?? 0),
            ':id'    => $idAc,
        ]);

        // Sincronizar area_cacao_ha en tabla_lpa
        $totalHa = floatval($body['cacao_nacional_has'] ?? 0) + floatval($body['cacao_ccn51_has'] ?? 0);
        $pdo->prepare("
            UPDATE tabla_lpa
            SET area_cacao_ha = ?
            WHERE id_socio = ?
            ORDER BY id_lpa DESC
            LIMIT 1
        ")->execute([$totalHa, $id]);

        echo json_encode(['success'=>true,'message'=>'Datos de cacao actualizados']);
        exit;
    }

    // ── SECCIÓN: otros datos del acuerdo ─────────────────────────────────────
    if ($seccion === 'otros') {
        $id = intval($body['id_socio'] ?? 0);
        $stCheck = $pdo->prepare("SELECT id_acuerdo FROM acuerdo_productor WHERE id_socio=? ORDER BY id_acuerdo DESC LIMIT 1");
        $stCheck->execute([$id]);
        $idAc = $stCheck->fetchColumn();
        if (!$idAc) { echo json_encode(['success'=>false,'message'=>'Sin acuerdo']); exit; }

        $pdo->prepare("
            UPDATE acuerdo_productor SET
                posee_riego              = :riego,
                periodo_de_fertilizacion = :fert,
                fecha_firma              = :ffirma
            WHERE id_acuerdo = :id
        ")->execute([
            ':riego'  => $body['posee_riego']           ?? 'NO',
            ':fert'   => $body['periodo_fertilizacion'] ?? '2',
            ':ffirma' => $body['fecha_firma']            ?? '',
            ':id'     => $idAc,
        ]);
        echo json_encode(['success'=>true,'message'=>'Datos del acuerdo actualizados']);
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'Sección no reconocida']);

} catch (PDOException $e) {
    error_log("consulta_general_editar: " . $e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Error BD: '.$e->getMessage()]);
}
?>