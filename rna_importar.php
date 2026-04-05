<?php
// IMPORTACIÓN RNA DESDE EXCEL
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
    ob_end_clean();
    die(json_encode(['success'=>false,'message'=>'No autorizado']));
}

try {
    require "config/conexion.php";
} catch (Exception $e) {
    ob_end_clean();
    die(json_encode(['success'=>false,'message'=>'Error de conexión a BD: '.$e->getMessage()]));
}

function importarExcel() {
    global $pdo;
    
    // Validar que PDO existe
    if (!isset($pdo)) {
        throw new Exception('Conexión a base de datos no disponible');
    }
    
    if (!isset($_FILES['archivo'])) {
        throw new Exception('Archivo no enviado');
    }

    if ($_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        $errores = [
            UPLOAD_ERR_INI_SIZE => 'Archivo superior al límite del servidor',
            UPLOAD_ERR_FORM_SIZE => 'Archivo superior al límite del formulario',
            UPLOAD_ERR_PARTIAL => 'Archivo subido parcialmente',
            UPLOAD_ERR_NO_FILE => 'Archivo no seleccionado',
            UPLOAD_ERR_NO_TMP_DIR => 'Directorio temporal no disponible',
            UPLOAD_ERR_CANT_WRITE => 'No se puede escribir el archivo',
        ];
        throw new Exception($errores[$_FILES['archivo']['error']] ?? 'Error desconocido');
    }

    $archivo_tmp = $_FILES['archivo']['tmp_name'];
    $archivo_nombre = $_FILES['archivo']['name'];

    if (!preg_match('/\.(xlsx|xls)$/i', $archivo_nombre)) {
        throw new Exception('El archivo debe ser Excel (.xlsx o .xls)');
    }

    if (!file_exists($archivo_tmp)) {
        throw new Exception('Archivo temporal no encontrado');
    }

    // Leer Excel como ZIP
    $zip = new ZipArchive();
    if (!$zip->open($archivo_tmp)) {
        throw new Exception('No se puede leer el archivo Excel');
    }

    // Leer strings compartidos
    $strings = [];
    $xml_strings = $zip->getFromName('xl/sharedStrings.xml');
    if ($xml_strings) {
        $xml = simplexml_load_string($xml_strings);
        if ($xml) {
            foreach ($xml->si as $si) {
                $strings[] = (string)$si->t;
            }
        }
    }

    // Leer primera hoja
    $xml_sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    if (!$xml_sheet) {
        throw new Exception('No se encuentra hoja de trabajo en el Excel');
    }

    $xml = simplexml_load_string($xml_sheet);
    if (!$xml) {
        throw new Exception('Error al leer XML del Excel');
    }

    // Procesar filas
    $rows = [];
    foreach ($xml->sheetData->row as $row) {
        $fila = [];
        $tiene_datos = false;
        
        foreach ($row->c as $cell) {
            $valor = '';
            $tipo = (string)$cell['t'];
            
            if ($tipo == 's' && isset($cell->v)) {
                // String (referencia al string table)
                $idx = (int)$cell->v;
                $valor = $strings[$idx] ?? '';
                if ($valor) $tiene_datos = true;
            } elseif (isset($cell->v)) {
                // Número u otro tipo
                $valor = (string)$cell->v;
                if ($valor) $tiene_datos = true;
            }
            
            $fila[] = trim($valor);
        }
        
        if ($tiene_datos || empty($rows)) { // Incluir header aunque esté vacío
            $rows[] = $fila;
        }
    }

    $zip->close();

    if (count($rows) < 2) {
        throw new Exception('El archivo debe tener encabezados y al menos 1 fila de datos');
    }

    // Procesar datos
    $headers = array_map(function($h) { return strtolower(trim($h)); }, $rows[0]);
    
    if (!in_array('cedula', $headers)) {
        throw new Exception('La columna "cedula" es obligatoria en los encabezados');
    }

    $importados = 0;
    $pdo->beginTransaction();

    for ($i = 1; $i < count($rows); $i++) {
        $row_values = array_pad($rows[$i], count($headers), null);
        $fila = [];
        
        foreach ($headers as $idx => $header) {
            $fila[$header] = trim((string)($row_values[$idx] ?? ''));
        }

        if (empty($fila['cedula'])) {
            continue;
        }

        /* PERSONA */
        $stmt = $pdo->prepare("SELECT id_persona FROM rna_persona WHERE cedula=? LIMIT 1");
        $stmt->execute([$fila['cedula']]);
        $persona = $stmt->fetch(PDO::FETCH_ASSOC);
        $id_persona = null;

        if ($persona) {
            $id_persona = $persona['id_persona'];
            $sql = "UPDATE rna_persona SET
                nombres=?, apellidos=?, genero=?, fecha_nacimiento=?,
                celular=?, correo=?, contrasena_correo=?, se_registra_como=?,
                nacionalidad=?, autoidentificacion=?, instruccion_formal=?,
                anios_educacion=?, lugar_nacimiento=?, situacion_movilidad=?
                WHERE id_persona=?";
            
            $pdo->prepare($sql)->execute([
                $fila['nombres'] ?? null,
                $fila['apellidos'] ?? null,
                $fila['genero'] ?? null,
                $fila['fecha_nacimiento'] ?? null,
                $fila['celular'] ?? null,
                $fila['correo'] ?? null,
                $fila['contrasena_correo'] ?? null,
                $fila['se_registra_como'] ?? null,
                $fila['nacionalidad'] ?? null,
                $fila['autoidentificacion'] ?? null,
                $fila['instruccion_formal'] ?? null,
                $fila['anios_educacion'] ?? null,
                $fila['lugar_nacimiento'] ?? null,
                $fila['situacion_movilidad'] ?? null,
                $id_persona
            ]);
        } else {
            $sql = "INSERT INTO rna_persona
                (cedula,nombres,apellidos,genero,fecha_nacimiento,celular,correo,
                 contrasena_correo,se_registra_como,nacionalidad,autoidentificacion,
                 instruccion_formal,anios_educacion,lugar_nacimiento,situacion_movilidad)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            
            $pdo->prepare($sql)->execute([
                $fila['cedula'],
                $fila['nombres'] ?? null,
                $fila['apellidos'] ?? null,
                $fila['genero'] ?? null,
                $fila['fecha_nacimiento'] ?? null,
                $fila['celular'] ?? null,
                $fila['correo'] ?? null,
                $fila['contrasena_correo'] ?? null,
                $fila['se_registra_como'] ?? null,
                $fila['nacionalidad'] ?? null,
                $fila['autoidentificacion'] ?? null,
                $fila['instruccion_formal'] ?? null,
                $fila['anios_educacion'] ?? null,
                $fila['lugar_nacimiento'] ?? null,
                $fila['situacion_movilidad'] ?? null
            ]);
            $id_persona = $pdo->lastInsertId();
        }

        /* DOMICILIO */
        $pdo->prepare("DELETE FROM rna_domicilio WHERE id_persona=?")->execute([$id_persona]);
        $pdo->prepare("INSERT INTO rna_domicilio (id_persona,provincia,canton,parroquia,recinto,referencia)
            VALUES (?,?,?,?,?,?)")->execute([
            $id_persona,
            $fila['provincia'] ?? null,
            $fila['canton'] ?? null,
            $fila['parroquia'] ?? null,
            $fila['recinto'] ?? null,
            $fila['referencia'] ?? null
        ]);

        /* PREDIO */
        $pdo->prepare("DELETE FROM rna_predio WHERE id_persona=?")->execute([$id_persona]);
        $pdo->prepare("INSERT INTO rna_predio
            (id_persona,nombre_predio,provincia,canton,parroquia,recinto,vive_en_predio,forma_tenencia,area_has)
            VALUES (?,?,?,?,?,?,?,?,?)")->execute([
            $id_persona,
            $fila['nombre_predio'] ?? null,
            $fila['pred_provincia'] ?? null,
            $fila['pred_canton'] ?? null,
            $fila['pred_parroquia'] ?? null,
            $fila['pred_recinto'] ?? null,
            $fila['vive_en_predio'] ?? null,
            $fila['forma_tenencia'] ?? null,
            $fila['area_has'] ?? null
        ]);

        $id_predio = $pdo->lastInsertId();

        /* GEO */
        if ($id_predio > 0) {
            $x = !empty($fila['x']) ? $fila['x'] : null;
            $y = !empty($fila['y']) ? $fila['y'] : null;
            $z = !empty($fila['z']) ? $fila['z'] : null;
            
            if ($x !== null && $y !== null) {
                $pdo->prepare("INSERT INTO rna_georreferenciacion (id_predio,x,y,z)
                    VALUES (?,?,?,?)")->execute([
                    $id_predio, $x, $y, $z
                ]);
            }
        }

        /* ACTIVIDAD */
        if ($id_predio > 0 && !empty($fila['actividad'])) {
            $pdo->prepare("INSERT INTO rna_actividad (id_predio,actividad,rubro,principal_ingreso)
                VALUES (?,?,?,?)")->execute([
                $id_predio,
                $fila['actividad'],
                $fila['rubro'] ?? null,
                $fila['principal_ingreso'] ?? null
            ]);
        }

        /* USUARIO RNA */
        if (!empty($fila['usuario_rna'])) {
            $pdo->prepare("INSERT INTO rna_usuario (id_persona,usuario_rna,contrasena_rna,fecha_registro)
                VALUES (?,?,?,NOW())")->execute([
                $id_persona,
                $fila['usuario_rna'],
                $fila['contrasena_rna'] ?? null
            ]);
        }

        $importados++;
    }

    $pdo->commit();
    
    return [
        'success' => true,
        'message' => "✅ Importación exitosa: $importados registros importados"
    ];
}

// Ejecutar importación
try {
    $resultado = importarExcel();
    ob_end_clean();
    echo json_encode($resultado);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error importación RNA: ' . $e->getMessage());
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => '❌ Error: ' . $e->getMessage()
    ]);
}
exit;
