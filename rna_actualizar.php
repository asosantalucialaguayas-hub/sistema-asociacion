<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success'=>false,'message'=>'No autorizado']);
    exit;
}

require "config/conexion.php";

try {
    $pdo->beginTransaction();

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

    $id_persona = $_POST['id_persona'] ?? null;
    if (!$id_persona) throw new Exception('id_persona no enviado');

    // Normalizar genero
    $genero = isset($_POST['genero']) && $_POST['genero'] === 'M' ? 'M' : (isset($_POST['genero']) && $_POST['genero'] === 'F' ? 'F' : null);

    // Actualizar persona
    $sqlPersona = "UPDATE rna_persona SET
        cedula = ?, nombres = ?, apellidos = ?, genero = ?, fecha_nacimiento = ?,
        celular = ?, correo = ?, contrasena_correo = ?, se_registra_como = ?, nacionalidad = ?, autoidentificacion = ?,
        instruccion_formal = ?, anios_educacion = ?, lugar_nacimiento = ?, situacion_movilidad = ?
        WHERE id_persona = ?";

    $stmt = $pdo->prepare($sqlPersona);
    $stmt->execute([
        $_POST['cedula'] ?? null,
        $_POST['nombres'] ?? null,
        $_POST['apellidos'] ?? null,
        $genero,
        $_POST['fecha_nacimiento'] ?? null,
        $_POST['celular'] ?? null,
        $_POST['correo'] ?? null,
        $_POST['correo_password'] ?? null,
        $_POST['registro_como'] ?? null,
        $_POST['nacionalidad'] ?? null,
        $_POST['autoidentificacion'] ?? null,
        $_POST['instruccion_formal'] ?? null,
        $_POST['anios_educacion'] ?? null,
        $_POST['lugar_nacimiento'] ?? null,
        $_POST['situacion_movilidad'] ?? null,
        $id_persona
    ]);

    // DOMICILIO
    $sqlDom = "SELECT id_domicilio FROM rna_domicilio WHERE id_persona = ? LIMIT 1";
    $stmt = $pdo->prepare($sqlDom);
    $stmt->execute([$id_persona]);
    $dom = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($dom) {
        $sqlUpdateDom = "UPDATE rna_domicilio SET provincia = ?, canton = ?, parroquia = ?, recinto = ?, referencia = ? WHERE id_domicilio = ?";
        $stmt = $pdo->prepare($sqlUpdateDom);
        $stmt->execute([
            $_POST['provincia'] ?? null,
            $_POST['canton'] ?? null,
            $_POST['parroquia'] ?? null,
            $_POST['recinto'] ?? null,
            $_POST['referencia'] ?? null,
            $dom['id_domicilio']
        ]);
    } else {
        $sqlInsertDom = "INSERT INTO rna_domicilio (id_persona, provincia, canton, parroquia, recinto, referencia) VALUES (?,?,?,?,?,?)";
        $stmt = $pdo->prepare($sqlInsertDom);
        $stmt->execute([
            $id_persona,
            $_POST['provincia'] ?? null,
            $_POST['canton'] ?? null,
            $_POST['parroquia'] ?? null,
            $_POST['recinto'] ?? null,
            $_POST['referencia'] ?? null
        ]);
    }

    // PREDIO
    $sqlPred = "SELECT id_predio FROM rna_predio WHERE id_persona = ? LIMIT 1";
    $stmt = $pdo->prepare($sqlPred);
    $stmt->execute([$id_persona]);
    $pred = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($pred) {
        $sqlUpdPred = "UPDATE rna_predio SET nombre_predio = ?, provincia = ?, canton = ?, parroquia = ?, recinto = ?, vive_en_predio = ?, forma_tenencia = ?, area_has = ? WHERE id_predio = ?";
        $stmt = $pdo->prepare($sqlUpdPred);
        $stmt->execute([
            $_POST['nombre_predio'] ?? null,
            $_POST['provincia_predio'] ?? null,
            $_POST['canton_predio'] ?? null,
            $_POST['parroquia_predio'] ?? null,
            $_POST['recinto_predio'] ?? null,
            $_POST['vive_predio'] ?? null,
            $_POST['forma_tenencia'] ?? null,
            $_POST['has'] ?? null,
            $pred['id_predio']
        ]);
        $id_predio = $pred['id_predio'];
    } else {
        $sqlInsPred = "INSERT INTO rna_predio (id_persona, nombre_predio, provincia, canton, parroquia, recinto, vive_en_predio, forma_tenencia, area_has) VALUES (?,?,?,?,?,?,?,?,?)";
        $stmt = $pdo->prepare($sqlInsPred);
        $stmt->execute([
            $id_persona,
            $_POST['nombre_predio'] ?? null,
            $_POST['provincia_predio'] ?? null,
            $_POST['canton_predio'] ?? null,
            $_POST['parroquia_predio'] ?? null,
            $_POST['recinto_predio'] ?? null,
            $_POST['vive_predio'] ?? null,
            $_POST['forma_tenencia'] ?? null,
            $_POST['has'] ?? null
        ]);
        $id_predio = $pdo->lastInsertId();
    }

    // GEO
    $sqlGeo = "SELECT id_geo, id_predio FROM rna_georreferenciacion WHERE id_predio = ? LIMIT 1";
    $stmt = $pdo->prepare($sqlGeo);
    $stmt->execute([$id_predio]);
    $geo = $stmt->fetch(PDO::FETCH_ASSOC);

    $x_val = sanitize_coord($_POST['x'] ?? null);
    $y_val = sanitize_coord($_POST['y'] ?? null);
    $z_val = sanitize_coord($_POST['z'] ?? null);

    if ($geo) {
        $sqlUpdGeo = "UPDATE rna_georreferenciacion SET x = ?, y = ?, z = ? WHERE id_geo = ?";
        $stmt = $pdo->prepare($sqlUpdGeo);
        $stmt->execute([
            $x_val,
            $y_val,
            $z_val,
            $geo['id_geo']
        ]);
    } else {
        $sqlInsGeo = "INSERT INTO rna_georreferenciacion (id_predio, x, y, z) VALUES (?,?,?,?)";
        $stmt = $pdo->prepare($sqlInsGeo);
        $stmt->execute([
            $id_predio,
            $x_val,
            $y_val,
            $z_val
        ]);
    }

    // ACTIVIDAD
    $sqlAct = "SELECT id_actividad FROM rna_actividad WHERE id_predio = ? LIMIT 1";
    $stmt = $pdo->prepare($sqlAct);
    $stmt->execute([$id_predio]);
    $act = $stmt->fetch(PDO::FETCH_ASSOC);

    $principal_ingreso_raw = strtolower(trim($_POST['principal_ingreso'] ?? ''));
    $mapIngreso = ['agricola'=>'Agricola','agrícola'=>'Agricola','pecuario'=>'Pecuario','otro'=>'Otro'];
    $principal_ingreso = $mapIngreso[$principal_ingreso_raw] ?? ($_POST['principal_ingreso'] ?? null);

    if ($act) {
        $sqlUpdAct = "UPDATE rna_actividad SET actividad = ?, rubro = ?, principal_ingreso = ? WHERE id_actividad = ?";
        $stmt = $pdo->prepare($sqlUpdAct);
        $stmt->execute([
            $_POST['actividad'] ?? null,
            $_POST['rubro'] ?? null,
            $principal_ingreso,
            $act['id_actividad']
        ]);
    } else {
        $sqlInsAct = "INSERT INTO rna_actividad (id_predio, actividad, rubro, principal_ingreso) VALUES (?,?,?,?)";
        $stmt = $pdo->prepare($sqlInsAct);
        $stmt->execute([
            $id_predio,
            $_POST['actividad'] ?? null,
            $_POST['rubro'] ?? null,
            $principal_ingreso
        ]);
    }

    // USUARIO RNA
    $sqlUsr = "SELECT id_usuario_rna FROM rna_usuario WHERE id_persona = ? LIMIT 1";
    $stmt = $pdo->prepare($sqlUsr);
    $stmt->execute([$id_persona]);
    $usr = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usr) {
        // actualizar usuario (usuario y/o contrasena)
        if (!empty($_POST['contrasena_rna'])) {
            $sqlUpdUsr = "UPDATE rna_usuario SET usuario_rna = ?, contrasena_rna = ? WHERE id_usuario_rna = ?";
            $stmt = $pdo->prepare($sqlUpdUsr);
            $stmt->execute([
                $_POST['usuario_rna'] ?? null,
                $_POST['contrasena_rna'],  // Store plain text
                $usr['id_usuario_rna']
            ]);
        } else {
            $sqlUpdUsr2 = "UPDATE rna_usuario SET usuario_rna = ? WHERE id_usuario_rna = ?";
            $stmt = $pdo->prepare($sqlUpdUsr2);
            $stmt->execute([
                $_POST['usuario_rna'] ?? null,
                $usr['id_usuario_rna']
            ]);
        }
    } else {
        if (!empty($_POST['usuario_rna'])) {
            $sqlInsUsr = "INSERT INTO rna_usuario (id_persona, usuario_rna, contrasena_rna, fecha_registro) VALUES (?,?,?,NOW())";
            $stmt = $pdo->prepare($sqlInsUsr);
            $stmt->execute([
                $id_persona,
                $_POST['usuario_rna'] ?? null,
                $_POST['contrasena_rna'] ?? null  // Store plain text
            ]);
        }
    }

    $pdo->commit();

    echo json_encode(['success'=>true,'message'=>'RNA actualizado correctamente']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success'=>false,'message'=>'Error al actualizar RNA','error'=>$e->getMessage()]);
}
