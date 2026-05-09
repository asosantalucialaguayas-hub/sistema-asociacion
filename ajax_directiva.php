<?php
// ============================================================
// ajax_directiva.php – Backend del módulo de Directiva
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { http_response_code(401); echo json_encode(['ok'=>false,'msg'=>'No autenticado']); exit; }

ob_start();
require __DIR__ . "/layout/bootstrap.php";
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');

$rol     = $_SESSION['rol'] ?? $_SESSION['tipo_usuario'] ?? 'viewer';
$id_usr  = (int)($_SESSION['id_usuario'] ?? 0);
if ($id_usr && function_exists('tienePermiso') && isset($pdo)) {
    $puede_ver       = tienePermiso($pdo, $id_usr, 'directiva', 'puede_ver');
    $puede_agregar   = tienePermiso($pdo, $id_usr, 'directiva', 'puede_agregar');
    $puede_modificar = tienePermiso($pdo, $id_usr, 'directiva', 'puede_modificar');
    $puede_eliminar  = tienePermiso($pdo, $id_usr, 'directiva', 'puede_eliminar');
} else {
    $fallback = in_array(strtolower($rol), ['admin','secretario','presidente','superadmin']) || $id_usr === 1;
    $puede_ver = $puede_agregar = $puede_modificar = $puede_eliminar = $fallback;
}

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

function jOk(array $d=[])  { echo json_encode(['ok'=>true]  + $d); exit; }
function jErr(string $msg) { echo json_encode(['ok'=>false,'msg'=>$msg]); exit; }

// ── INSTALAR TABLAS ───────────────────────────────────────────────────────────
if ($accion === 'instalar') {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS directiva_periodos (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                nombre          VARCHAR(150) NOT NULL,
                fecha_inicio    DATE NOT NULL,
                fecha_fin       DATE,
                duracion_anos   TINYINT DEFAULT 2,
                estado          ENUM('activo','cerrado') DEFAULT 'activo',
                documento_pdf   VARCHAR(300),
                notas           TEXT,
                creado_por      INT,
                creado_en       DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_estado (estado)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS directiva_miembros (
                id                  INT AUTO_INCREMENT PRIMARY KEY,
                periodo_id          INT NOT NULL,
                socio_id            INT,
                nombre_manual       VARCHAR(200),
                cedula_manual       VARCHAR(20),
                cargo               VARCHAR(50) NOT NULL,
                cargo_label         VARCHAR(100),
                tipo_junta          ENUM('directiva','vigilancia') DEFAULT 'directiva',
                orden_cargo         TINYINT DEFAULT 99,
                fecha_nombramiento  DATE,
                periodo_anos        TINYINT DEFAULT 2,
                creado_en           DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (periodo_id) REFERENCES directiva_periodos(id) ON DELETE CASCADE,
                INDEX idx_periodo (periodo_id),
                INDEX idx_tipo (tipo_junta)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        jOk(['msg'=>'Tablas creadas correctamente']);
    } catch (PDOException $e) {
        jErr('Error creando tablas: ' . $e->getMessage());
    }
}

// ── GET: obtener período ──────────────────────────────────────────────────────
if ($accion === 'get_periodo') {
    $id = intval($_GET['id']??0);
    if (!$id) jErr('ID requerido');
    $st = $pdo->prepare("SELECT * FROM directiva_periodos WHERE id=?");
    $st->execute([$id]);
    $p = $st->fetch(PDO::FETCH_ASSOC);
    if (!$p) jErr('Período no encontrado');
    jOk(['periodo'=>$p]);
}

// ── GET: obtener miembro ──────────────────────────────────────────────────────
if ($accion === 'get_miembro') {
    $id = intval($_GET['id']??0);
    if (!$id) jErr('ID requerido');
    $st = $pdo->prepare("
        SELECT m.*, s.nombre_completo, s.identificacion
        FROM directiva_miembros m
        LEFT JOIN socios s ON s.id_socio = m.socio_id
        WHERE m.id=?
    ");
    $st->execute([$id]);
    $m = $st->fetch(PDO::FETCH_ASSOC);
    if (!$m) jErr('Miembro no encontrado');
    jOk(['miembro'=>$m]);
}

// ── Validar permisos por tipo de acción ───────────────────────────────────────
if (in_array($accion, ['get_periodo','get_miembro'], true) && !$puede_ver) {
    jErr('Sin permisos');
}
if (in_array($accion, ['crear_periodo','crear_miembro'], true) && !$puede_agregar) {
    jErr('Sin permisos');
}
if (in_array($accion, ['editar_periodo','editar_miembro','subir_documento'], true) && !$puede_modificar) {
    jErr('Sin permisos');
}
if (in_array($accion, ['eliminar_periodo','eliminar_miembro'], true) && !$puede_eliminar) {
    jErr('Sin permisos');
}

// ── CREAR PERÍODO ─────────────────────────────────────────────────────────────
if ($accion === 'crear_periodo') {
    $nombre   = trim($_POST['nombre']??'');
    $inicio   = $_POST['fecha_inicio']??'';
    $duracion = intval($_POST['duracion_anos']??2);
    $estado   = $_POST['estado']??'activo';
    $notas    = trim($_POST['notas']??'');

    if (!$nombre || !$inicio) jErr('Nombre y fecha de inicio son requeridos');

    // Calcular fecha fin
    $fin = date('Y-m-d', strtotime("$inicio +$duracion years"));

    // Si nuevo período es activo, cerrar el anterior
    if ($estado === 'activo') {
        $pdo->exec("UPDATE directiva_periodos SET estado='cerrado' WHERE estado='activo'");
    }

    $pdo->prepare("
        INSERT INTO directiva_periodos (nombre,fecha_inicio,fecha_fin,duracion_anos,estado,notas,creado_por)
        VALUES (?,?,?,?,?,?,?)
    ")->execute([$nombre,$inicio,$fin,$duracion,$estado,$notas,$id_usr]);

    $new_id = $pdo->lastInsertId();

    // Subir documento si viene
    if (!empty($_FILES['documento']['tmp_name'])) {
        $url = subirPDF($_FILES['documento'], $new_id);
        if ($url) {
            $pdo->prepare("UPDATE directiva_periodos SET documento_pdf=? WHERE id=?")->execute([$url,$new_id]);
        }
    }

    jOk(['id'=>$new_id,'msg'=>'Período creado']);
}

// ── EDITAR PERÍODO ────────────────────────────────────────────────────────────
if ($accion === 'editar_periodo') {
    $id       = intval($_POST['id']??0);
    $nombre   = trim($_POST['nombre']??'');
    $inicio   = $_POST['fecha_inicio']??'';
    $duracion = intval($_POST['duracion_anos']??2);
    $estado   = $_POST['estado']??'activo';
    $notas    = trim($_POST['notas']??'');

    if (!$id || !$nombre || !$inicio) jErr('Datos incompletos');

    $fin = date('Y-m-d', strtotime("$inicio +$duracion years"));

    if ($estado === 'activo') {
        $pdo->prepare("UPDATE directiva_periodos SET estado='cerrado' WHERE estado='activo' AND id!=?")->execute([$id]);
    }

    $pdo->prepare("
        UPDATE directiva_periodos SET nombre=?,fecha_inicio=?,fecha_fin=?,duracion_anos=?,estado=?,notas=? WHERE id=?
    ")->execute([$nombre,$inicio,$fin,$duracion,$estado,$notas,$id]);

    if (!empty($_FILES['documento']['tmp_name'])) {
        $url = subirPDF($_FILES['documento'], $id);
        if ($url) $pdo->prepare("UPDATE directiva_periodos SET documento_pdf=? WHERE id=?")->execute([$url,$id]);
    }

    jOk(['msg'=>'Período actualizado']);
}

// ── ELIMINAR PERÍODO ──────────────────────────────────────────────────────────
if ($accion === 'eliminar_periodo') {
    $id = intval($_POST['id']??0);
    if (!$id) jErr('ID requerido');
    // Verificar que no está activo (protección)
    $st = $pdo->prepare("SELECT estado FROM directiva_periodos WHERE id=?");
    $st->execute([$id]);
    $p = $st->fetch();
    if ($p && $p['estado']==='activo') jErr('No puedes eliminar el período activo. Ciérralo primero.');
    $pdo->prepare("DELETE FROM directiva_periodos WHERE id=?")->execute([$id]);
    jOk(['msg'=>'Período eliminado']);
}

// ── CREAR MIEMBRO ─────────────────────────────────────────────────────────────
if ($accion === 'crear_miembro') {
    $periodo_id  = intval($_POST['periodo_id']??0);
    $socio_id    = intval($_POST['socio_id']??0) ?: null;
    $nombre      = trim($_POST['nombre_manual']??'');
    $cedula      = trim($_POST['cedula_manual']??'');
    $cargo       = trim($_POST['cargo']??'');
    $tipo_junta  = $_POST['tipo_junta']??'directiva';
    $fecha_nom   = $_POST['fecha_nombramiento']??null;
    $peri_anos   = intval($_POST['periodo_anos']??2);

    if (!$periodo_id || !$cargo || !$nombre) jErr('Datos incompletos');

    // Label del cargo
    $labels_dir = [
        'administrador'=>'Administrador/a','presidente'=>'Presidente/a','secretario'=>'Secretario/a',
        'vocal_principal_1'=>'Vocal Principal 1','vocal_principal_2'=>'Vocal Principal 2',
        'vocal_principal_3'=>'Vocal Principal 3','vocal_principal_4'=>'Vocal Principal 4',
        'vocal_principal_5'=>'Vocal Principal 5','vocal_suplente_1'=>'Vocal Suplente 1',
        'vocal_suplente_2'=>'Vocal Suplente 2','vocal_suplente_3'=>'Vocal Suplente 3',
        'vocal_suplente_4'=>'Vocal Suplente 4','vocal_suplente_5'=>'Vocal Suplente 5',
    ];
    $cargo_label = $labels_dir[$cargo] ?? ucfirst(str_replace('_',' ',$cargo));

    // Orden
    $orden_map = ['administrador'=>1,'presidente'=>2,'secretario'=>3,'vocal_principal_1'=>4,'vocal_principal_2'=>5,'vocal_principal_3'=>6,'vocal_principal_4'=>7,'vocal_principal_5'=>8,'vocal_suplente_1'=>9,'vocal_suplente_2'=>10,'vocal_suplente_3'=>11,'vocal_suplente_4'=>12,'vocal_suplente_5'=>13];
    $orden = $orden_map[$cargo] ?? 99;

    $pdo->prepare("
        INSERT INTO directiva_miembros
            (periodo_id,socio_id,nombre_manual,cedula_manual,cargo,cargo_label,tipo_junta,orden_cargo,fecha_nombramiento,periodo_anos)
        VALUES (?,?,?,?,?,?,?,?,?,?)
    ")->execute([$periodo_id,$socio_id,$nombre,$cedula,$cargo,$cargo_label,$tipo_junta,$orden,$fecha_nom,$peri_anos]);

    jOk(['msg'=>'Miembro registrado']);
}

// ── EDITAR MIEMBRO ────────────────────────────────────────────────────────────
if ($accion === 'editar_miembro') {
    $id         = intval($_POST['id']??0);
    $socio_id   = intval($_POST['socio_id']??0) ?: null;
    $nombre     = trim($_POST['nombre_manual']??'');
    $cedula     = trim($_POST['cedula_manual']??'');
    $cargo      = trim($_POST['cargo']??'');
    $tipo_junta = $_POST['tipo_junta']??'directiva';
    $fecha_nom  = $_POST['fecha_nombramiento']??null;
    $peri_anos  = intval($_POST['periodo_anos']??2);

    if (!$id || !$cargo || !$nombre) jErr('Datos incompletos');

    $labels = ['administrador'=>'Administrador/a','presidente'=>'Presidente/a','secretario'=>'Secretario/a','vocal_principal_1'=>'Vocal Principal 1','vocal_principal_2'=>'Vocal Principal 2','vocal_principal_3'=>'Vocal Principal 3','vocal_principal_4'=>'Vocal Principal 4','vocal_principal_5'=>'Vocal Principal 5','vocal_suplente_1'=>'Vocal Suplente 1','vocal_suplente_2'=>'Vocal Suplente 2','vocal_suplente_3'=>'Vocal Suplente 3','vocal_suplente_4'=>'Vocal Suplente 4','vocal_suplente_5'=>'Vocal Suplente 5'];
    $orden_map = ['administrador'=>1,'presidente'=>2,'secretario'=>3,'vocal_principal_1'=>4,'vocal_principal_2'=>5,'vocal_principal_3'=>6,'vocal_principal_4'=>7,'vocal_principal_5'=>8,'vocal_suplente_1'=>9,'vocal_suplente_2'=>10,'vocal_suplente_3'=>11,'vocal_suplente_4'=>12,'vocal_suplente_5'=>13];

    $pdo->prepare("
        UPDATE directiva_miembros SET
            socio_id=?,nombre_manual=?,cedula_manual=?,cargo=?,cargo_label=?,
            tipo_junta=?,orden_cargo=?,fecha_nombramiento=?,periodo_anos=?
        WHERE id=?
    ")->execute([
        $socio_id,$nombre,$cedula,$cargo,
        $labels[$cargo]??$cargo,$tipo_junta,
        $orden_map[$cargo]??99,$fecha_nom,$peri_anos,$id
    ]);

    jOk(['msg'=>'Miembro actualizado']);
}

// ── ELIMINAR MIEMBRO ──────────────────────────────────────────────────────────
if ($accion === 'eliminar_miembro') {
    $id = intval($_POST['id']??0);
    if (!$id) jErr('ID requerido');
    $pdo->prepare("DELETE FROM directiva_miembros WHERE id=?")->execute([$id]);
    jOk(['msg'=>'Miembro eliminado']);
}

// ── SUBIR DOCUMENTO ───────────────────────────────────────────────────────────
if ($accion === 'subir_documento') {
    $periodo_id = intval($_POST['periodo_id']??0);
    if (!$periodo_id) jErr('Falta periodo_id');
    if (empty($_FILES['documento']['tmp_name'])) jErr('No se recibió archivo');

    $url = subirPDF($_FILES['documento'], $periodo_id);
    if (!$url) jErr('Error al guardar el PDF');

    $pdo->prepare("UPDATE directiva_periodos SET documento_pdf=? WHERE id=?")->execute([$url,$periodo_id]);
    jOk(['url'=>$url,'msg'=>'Documento subido']);
}

// ── Helper: subir PDF ─────────────────────────────────────────────────────────
function subirPDF(array $file, int $id): ?string {
    if ($file['error'] !== 0) return null;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'pdf') return null;
    $dir = __DIR__ . '/uploads/directiva/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $nombre = "directiva_{$id}_" . date('Ymd_His') . '.pdf';
    if (move_uploaded_file($file['tmp_name'], $dir . $nombre)) {
        return 'uploads/directiva/' . $nombre;
    }
    return null;
}

jErr('Acción no reconocida: ' . $accion);
