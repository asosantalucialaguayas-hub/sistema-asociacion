<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require "config/conexion.php";

try {
    $pdo->beginTransaction();

    /* =========================
       NORMALIZAR DATOS
    ========================= */

    $genero = $_POST['genero'] === 'M' ? 'M' : ($_POST['genero'] === 'F' ? 'F' : null);

    $principal_ingreso_raw = strtolower(trim($_POST['principal_ingreso'] ?? ''));
    $mapIngreso = [
        'agricola' => 'Agricola',
        'agrícola' => 'Agricola',
        'pecuario' => 'Pecuario',
        'otro' => 'Otro'
    ];
    $principal_ingreso = $mapIngreso[$principal_ingreso_raw] ?? ($_POST['principal_ingreso'] ?? null);

    /* =========================
       Helpers: sanear coordenadas
    ========================= */
    function sanitize_coord($val) {
        $v = trim($val ?? '');
        if ($v === '') return null;
        $v = str_replace(',', '.', $v);
        $v = preg_replace('/[^0-9\.\-eE]/', '', $v);
        if ($v === '' || !is_numeric($v)) return null;
        $f = floatval($v);
        if (!is_finite($f)) return null;
        if (abs($f) > 1e7) return null;
        return $f;
    }

    /* =========================
       1. PERSONA
    ========================= */
    $stmt = $pdo->prepare("INSERT INTO rna_persona (
            cedula, nombres, apellidos, genero, fecha_nacimiento,
            celular, correo, contrasena_correo, se_registra_como, nacionalidad, autoidentificacion,
            instruccion_formal, anios_educacion, lugar_nacimiento, situacion_movilidad, estado_completitud
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $_POST['cedula'] ?? null,
        $_POST['nombres'] ?? null,
        $_POST['apellidos'] ?? null,
        $genero,
        $_POST['fecha_nacimiento'] ?? null,
        $_POST['celular'] ?? null,
        $_POST['correo'] ?? null,
        $_POST['correo_password'] ?? null,        // contrasena_correo
        $_POST['registro_como'] ?? null,          // se_registra_como
        $_POST['nacionalidad'] ?? null,
        $_POST['autoidentificacion'] ?? null,
        $_POST['instruccion_formal'] ?? null,
        $_POST['anios_educacion'] ?? null,
        $_POST['lugar_nacimiento'] ?? null,
        $_POST['situacion_movilidad'] ?? null,
        'COMPLETO'
    ]);

    $id_persona = $pdo->lastInsertId();

    /* =========================
       2. DOMICILIO
    ========================= */
    $stmt = $pdo->prepare("
        INSERT INTO rna_domicilio (
            id_persona, provincia, canton, parroquia, recinto, referencia
        ) VALUES (?,?,?,?,?,?)
    ");
    $stmt->execute([
        $id_persona,
        $_POST['provincia'],
        $_POST['canton'],
        $_POST['parroquia'],
        $_POST['recinto'],
        $_POST['referencia']
    ]);

    /* =========================
       3. PREDIO
    ========================= */
    $stmt = $pdo->prepare("
        INSERT INTO rna_predio (
            id_persona, nombre_predio, provincia, canton, parroquia,
            recinto, vive_en_predio, forma_tenencia, area_has
        ) VALUES (?,?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([
        $id_persona,
        $_POST['nombre_predio'],
        $_POST['provincia_predio'],
        $_POST['canton_predio'],
        $_POST['parroquia_predio'],
        $_POST['recinto_predio'],
        $_POST['vive_predio'],
        $_POST['forma_tenencia'] ?? null,
        $_POST['has']
    ]);

    $id_predio = $pdo->lastInsertId();

    /* =========================
       4. GEOREFERENCIACIÓN
    ========================= */
    $stmt = $pdo->prepare("
        INSERT INTO rna_georreferenciacion (
            id_predio, x, y, z
        ) VALUES (?,?,?,?)
    ");
    $x_val = sanitize_coord($_POST['x'] ?? null);
    $y_val = sanitize_coord($_POST['y'] ?? null);
    $z_val = sanitize_coord($_POST['z'] ?? null);

    $stmt->execute([
        $id_predio,
        $x_val,
        $y_val,
        $z_val
    ]);

    /* =========================
       5. ACTIVIDAD
    ========================= */
    $stmt = $pdo->prepare("INSERT INTO rna_actividad (
            id_predio, actividad, rubro, principal_ingreso
        ) VALUES (?,?,?,?)");
    $stmt->execute([
        $id_predio,
        $_POST['actividad'] ?? null,
        $_POST['rubro'] ?? null,
        $principal_ingreso
    ]);

    /* =========================
       6. USUARIO RNA
    ========================= */
    $stmt = $pdo->prepare(
        "INSERT INTO rna_usuario (
            id_persona, usuario_rna, contrasena_rna, fecha_registro
        ) VALUES (?,?,?,NOW())"
    );
    $stmt->execute([
        $id_persona,
        $_POST['usuario_rna'] ?? null,
        $_POST['contrasena_rna'] ?? null  // Store plain text, no hashing
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'RNA guardado correctamente'
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar RNA',
        'error' => $e->getMessage()
    ]);
}
