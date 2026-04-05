<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Sesión no válida'
    ]);
    exit;
}

require "config/conexion.php";

$id = $_GET['id'] ?? null;

if (!$id) {
    echo json_encode([
        'success' => false,
        'message' => 'ID no enviado'
    ]);
    exit;
}

try {

    /* =====================================================
       CONSULTA GENERAL RNA (TODO LO EXISTENTE EN TU BD)
    ===================================================== */
    $stmt = $pdo->prepare("
        SELECT
            /* PERSONA */
            p.id_persona,
            p.cedula,
            p.nombres,
            p.apellidos,
            p.genero,
            p.fecha_nacimiento,
            p.celular,
            p.correo,
            p.contrasena_correo,
            p.se_registra_como,
            p.nacionalidad,
            p.autoidentificacion,
            p.instruccion_formal,
            p.anios_educacion,
            p.lugar_nacimiento,
            p.situacion_movilidad,
            p.estado_completitud,

            /* USUARIO RNA */
            u.usuario_rna AS usuario_rna,
            u.contrasena_rna,
            u.fecha_registro,

            /* DOMICILIO */
            d.provincia   AS dom_provincia,
            d.canton      AS dom_canton,
            d.parroquia   AS dom_parroquia,
            d.recinto     AS dom_recinto,
            d.referencia,

            /* PREDIO */
            pr.id_predio,
            pr.nombre_predio,
            pr.provincia  AS pred_provincia,
            pr.canton     AS pred_canton,
            pr.parroquia  AS pred_parroquia,
            pr.recinto    AS pred_recinto,
            pr.vive_en_predio,
            pr.forma_tenencia,
            pr.area_has,

            /* GEO */
            g.x,
            g.y,
            g.z,

            /* ACTIVIDAD */
            a.actividad,
            a.rubro,
            a.principal_ingreso

        FROM rna_persona p
        LEFT JOIN rna_usuario u           ON u.id_persona = p.id_persona
        LEFT JOIN rna_domicilio d         ON d.id_persona = p.id_persona
        LEFT JOIN rna_predio pr           ON pr.id_persona = p.id_persona
        LEFT JOIN rna_georreferenciacion g ON g.id_predio = pr.id_predio
        LEFT JOIN rna_actividad a         ON a.id_predio = pr.id_predio
        WHERE p.id_persona = ?
        LIMIT 1
    ");

    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode([
            'success' => false,
            'message' => 'Registro no encontrado'
        ]);
        exit;
    }

    /* =====================================================
       RESPUESTA JSON ORDENADA (PARA TU MODAL)
    ===================================================== */
    echo json_encode([
        'success' => true,

        'persona' => [
            'id_persona'         => $row['id_persona'],
            'cedula'             => $row['cedula'],
            'nombres'            => $row['nombres'],
            'apellidos'          => $row['apellidos'],
            'genero'             => $row['genero'],
            'fecha_nacimiento'   => $row['fecha_nacimiento'],
            'celular'            => $row['celular'],
            'correo'             => $row['correo'],
            'contrasena_correo'  => $row['contrasena_correo'],
            'se_registra_como'   => $row['se_registra_como'],
            'nacionalidad'       => $row['nacionalidad'],
            'autoidentificacion' => $row['autoidentificacion'],
            'instruccion_formal' => $row['instruccion_formal'],
            'anios_educacion'    => $row['anios_educacion'],
            'lugar_nacimiento'   => $row['lugar_nacimiento'],
            'situacion_movilidad'=> $row['situacion_movilidad'],
            'estado_completitud' => $row['estado_completitud']
        ],

        'usuario' => [
            'usuario_rna'       => $row['usuario_rna'],
            'contrasena_rna'    => $row['contrasena_rna'],
            'fecha_registro'    => $row['fecha_registro']
        ],

        'domicilio' => [
            'provincia'  => $row['dom_provincia'],
            'canton'     => $row['dom_canton'],
            'parroquia'  => $row['dom_parroquia'],
            'recinto'    => $row['dom_recinto'],
            'referencia' => $row['referencia']
        ],

        'predio' => [
            'id_predio'      => $row['id_predio'],
            'nombre_predio'  => $row['nombre_predio'],
            'provincia'      => $row['pred_provincia'],
            'canton'         => $row['pred_canton'],
            'parroquia'      => $row['pred_parroquia'],
            'recinto'        => $row['pred_recinto'],
            'vive_en_predio' => $row['vive_en_predio'],
            'forma_tenencia' => $row['forma_tenencia'],
            'area_has'       => $row['area_has'] 
        ],

        'geo' => [
            'x' => $row['x'],
            'y' => $row['y'],
            'z' => $row['z']
        ],

        'actividad' => [
            'actividad'        => $row['actividad'],
            'rubro'            => $row['rubro'],
            'principal_ingreso'=> $row['principal_ingreso']
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor',
        'error'   => $e->getMessage()
    ]);
}
