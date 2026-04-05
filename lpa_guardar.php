<?php
ob_start();
header('Content-Type: application/json');

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        exit;
    }

    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['usuario'])) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
        exit;
    }

    require_once 'config/conexion.php';
    require __DIR__ . "/config/periodo_guard.php";

    // FIX COLLATION: forzar utf8mb4_unicode_ci en toda la conexión
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

    // ── Período ───────────────────────────────────────────────────────────────
    $id_periodo_post = !empty($_POST['id_periodo']) ? (int)$_POST['id_periodo'] : null;
    if ($id_periodo_post) {
        $stmtP = $pdo->prepare("SELECT id_periodo FROM periodo_comercializacion WHERE id_periodo = ? LIMIT 1");
        $stmtP->execute([$id_periodo_post]);
        $id_periodo = $stmtP->fetchColumn() ?: null;
        if (!$id_periodo) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'El período seleccionado no existe.']);
            exit;
        }
    } else {
        $periodo    = require_periodo_abierto_json($pdo);
        $id_periodo = $periodo['id_periodo'];
    }

    // ── Socio ─────────────────────────────────────────────────────────────────
    $id_socio = !empty($_POST['id_socio']) ? $_POST['id_socio'] : null;
    $cedula   = !empty($_POST['sel_identificacion']) ? trim($_POST['sel_identificacion']) : null;

    // Buscar por cédula si no viene id_socio
    if (empty($id_socio) && !empty($cedula)) {
        $stmtBuscar = $pdo->prepare("SELECT id_socio FROM socios WHERE identificacion = :c LIMIT 1");
        $stmtBuscar->execute([':c' => $cedula]);
        $id_socio = $stmtBuscar->fetchColumn() ?: null;
    }

    if (empty($id_socio)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'No se encontró el socio (cédula: ' . $cedula . '). Verifique que esté registrado en la tabla socios.']);
        exit;
    }

    // ── Campos ────────────────────────────────────────────────────────────────
    $anio                        = !empty($_POST['anio'])   ? $_POST['anio']   : date('Y');
    $zona                        = $_POST['zona']                        ?? '';
    $comunidad_grupo             = $_POST['comunidad_grupo']             ?? '';
    $en_acercamiento             = $_POST['en_acercamiento']             ?? 'NO';
    $otra_org_fairtrade          = $_POST['otra_org_fairtrade']          ?? 'NO';
    $area_total_ha               = $_POST['area_total_ha']               ?? 0;
    $area_cacao_ha               = $_POST['area_cacao_ha']               ?? 0;
    $num_matas_ha                = $_POST['num_matas_ha']                ?? 0;
    $certificacion_organica      = $_POST['certificacion_organica']      ?? 'NO';
    $volumen_produccion_estimado = $_POST['volumen_produccion_estimado'] ?? 0;
    $volumen_entregado_org       = $_POST['volumen_entregado_org']       ?? 0;
    $adendum                     = !empty($_POST['adendum']) ? (int)$_POST['adendum'] : 1;
    $estado_lpa                  = 'activo';

    $sexo             = !empty($_POST['sel_sexo'])            ? $_POST['sel_sexo']            : null;
    $celular          = !empty($_POST['sel_telefono'])         ? $_POST['sel_telefono']         : null;
    $fecha_nacimiento = !empty($_POST['sel_fecha_nacimiento']) ? $_POST['sel_fecha_nacimiento'] : null;
    $fecha_ingreso    = !empty($_POST['sel_fecha_ingreso'])    ? $_POST['sel_fecha_ingreso']    : null;

    // ── Meses ─────────────────────────────────────────────────────────────────
    $enero      = $_POST['enero']      ?? 0;
    $febrero    = $_POST['febrero']    ?? 0;
    $marzo      = $_POST['marzo']      ?? 0;
    $abril      = $_POST['abril']      ?? 0;
    $mayo       = $_POST['mayo']       ?? 0;
    $junio      = $_POST['junio']      ?? 0;
    $julio      = $_POST['julio']      ?? 0;
    $agosto     = $_POST['agosto']     ?? 0;
    $septiembre = $_POST['septiembre'] ?? 0;
    $octubre    = $_POST['octubre']    ?? 0;
    $noviembre  = $_POST['noviembre']  ?? 0;
    $diciembre  = $_POST['diciembre']  ?? 0;

    $pdo->beginTransaction();

    $sql = "
        INSERT INTO tabla_lpa (
            id_periodo, id_socio, anio, zona, comunidad_grupo,
            en_acercamiento, otra_org_fairtrade,
            area_total_ha, area_cacao_ha, num_matas_ha, certificacion_organica,
            volumen_produccion_estimado, volumen_entregado_org, estado_lpa, adendum,
            sexo, celular, fecha_nacimiento, fecha_ingreso,
            enero, febrero, marzo, abril, mayo, junio, julio, agosto,
            septiembre, octubre, noviembre, diciembre
        ) VALUES (
            :id_periodo, :id_socio, :anio, :zona, :comunidad_grupo,
            :en_acercamiento, :otra_org_fairtrade,
            :area_total_ha, :area_cacao_ha, :num_matas_ha, :certificacion_organica,
            :volumen_produccion_estimado, :volumen_entregado_org, :estado_lpa, :adendum,
            :sexo, :celular, :fecha_nacimiento, :fecha_ingreso,
            :enero, :febrero, :marzo, :abril, :mayo, :junio, :julio, :agosto,
            :septiembre, :octubre, :noviembre, :diciembre
        )
    ";

    $pdo->prepare($sql)->execute([
        ':id_periodo'                  => $id_periodo,
        ':id_socio'                    => $id_socio,
        ':anio'                        => $anio,
        ':zona'                        => $zona,
        ':comunidad_grupo'             => $comunidad_grupo,
        ':en_acercamiento'             => $en_acercamiento,
        ':otra_org_fairtrade'          => $otra_org_fairtrade,
        ':area_total_ha'               => $area_total_ha,
        ':area_cacao_ha'               => $area_cacao_ha,
        ':num_matas_ha'                => $num_matas_ha,
        ':certificacion_organica'      => $certificacion_organica,
        ':volumen_produccion_estimado' => $volumen_produccion_estimado,
        ':volumen_entregado_org'       => $volumen_entregado_org,
        ':estado_lpa'                  => $estado_lpa,
        ':adendum'                     => $adendum,
        ':sexo'                        => $sexo,
        ':celular'                     => $celular,
        ':fecha_nacimiento'            => $fecha_nacimiento,
        ':fecha_ingreso'               => $fecha_ingreso,
        ':enero'                       => $enero,
        ':febrero'                     => $febrero,
        ':marzo'                       => $marzo,
        ':abril'                       => $abril,
        ':mayo'                        => $mayo,
        ':junio'                       => $junio,
        ':julio'                       => $julio,
        ':agosto'                      => $agosto,
        ':septiembre'                  => $septiembre,
        ':octubre'                     => $octubre,
        ':noviembre'                   => $noviembre,
        ':diciembre'                   => $diciembre,
    ]);

    if (!empty($sexo)) {
        $pdo->prepare("UPDATE socios SET sexo = ? WHERE id_socio = ?")
            ->execute([$sexo, $id_socio]);
    }

    $pdo->commit();
    ob_end_clean();
    echo json_encode(['success' => true, 'message' => 'LPA guardada correctamente']);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $output = ob_get_clean();
    error_log("lpa_guardar FATAL: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug'   => $output
    ]);
}