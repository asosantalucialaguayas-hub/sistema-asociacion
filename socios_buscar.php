<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['error' => 'no_session']);
    exit;
}
require "config/conexion.php";

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q === '') {
    echo json_encode([]);
    exit;
}

try {
    $qSinEspacios = str_replace(' ', '', $q);
    $qLike        = '%' . $q . '%';
    $qCedula      = '%' . $qSinEspacios . '%';

    // ── 1️⃣ Buscar en tabla socios ──────────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT 
            s.id_socio,
            s.identificacion,
            s.nombre_completo,
            s.sexo,
            s.telefono,
            s.fecha_nacimiento,
            s.fecha_ingreso,
            s.direccion,
            s.correo,
            a.provincia                              AS zona,
            COALESCE(a.sector, a.parroquia, '')      AS comunidad_grupo
        FROM socios s
        LEFT JOIN acuerdo_productor a ON a.cedula = s.identificacion
        WHERE s.estado = 'activo'
          AND (
              REPLACE(s.identificacion, ' ', '') LIKE :qCedula
              OR s.nombre_completo LIKE :qLike
          )
        ORDER BY s.nombre_completo
        LIMIT 10
    ");
    $stmt->execute([
        ':qCedula' => $qCedula,
        ':qLike'   => $qLike,
    ]);
    $socios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($socios) > 0) {
        echo json_encode($socios);
        exit;
    }

    // ── 2️⃣ Si no está en socios, buscar en solicitud_ingreso ───────────────
    //    FIX: id_socio = NULL hacía que el formulario rechazara al socio.
    //    Ahora también traemos zona/comunidad desde acuerdo_productor.
    $stmt2 = $pdo->prepare("
        SELECT 
            NULL                                         AS id_socio,
            si.identificacion,
            si.nombres_completos                         AS nombre_completo,
            ''                                           AS sexo,
            si.celular                                   AS telefono,
            si.fecha_nacimiento,
            NULL                                         AS fecha_ingreso,
            ''                                           AS direccion,
            ''                                           AS correo,
            COALESCE(ap.provincia, '')                   AS zona,
            COALESCE(ap.sector, ap.parroquia, '')        AS comunidad_grupo
        FROM solicitud_ingreso si
        LEFT JOIN acuerdo_productor ap ON ap.cedula = si.identificacion
        WHERE si.estado_solicitud = 'APROBADO'
          AND (
              REPLACE(si.identificacion, ' ', '') LIKE :qCedula
              OR si.nombres_completos LIKE :qLike
          )
        LIMIT 10
    ");
    $stmt2->execute([
        ':qCedula' => $qCedula,
        ':qLike'   => $qLike,
    ]);
    $personas = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($personas);

} catch (Exception $e) {
    error_log("Error en socios_buscar.php: " . $e->getMessage());
    echo json_encode(['error' => 'exception', 'message' => $e->getMessage()]);
}