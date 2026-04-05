<?php
/**
 * SCRIPT DE MIGRACIÓN: LPA → acuerdo_productor
 * 
 * Crea registros en acuerdo_productor para todos los socios que:
 * 1. Tienen registro en tabla_lpa
 * 2. NO tienen acuerdo_productor todavía
 * 
 * MAPEO:
 * - zona (LPA) → parroquia (acuerdo)
 * - comunidad_grupo (LPA) → sector (acuerdo)
 * - Provincia y Cantón por defecto según zona
 */

require "config/conexion.php";

echo "<h2>🔄 Migración LPA → acuerdo_productor</h2>";
echo "<pre>";

// ── Mapeo de zonas a provincia/cantón ──────────────────────────────────────
$mapeoZonas = [
    'Guayas'      => ['provincia' => 'Guayas', 'canton' => 'Pedro Carbo'],
    'Balzar'      => ['provincia' => 'Guayas', 'canton' => 'Balzar'],
    'Mocache'     => ['provincia' => 'Los Ríos', 'canton' => 'Mocache'],
    'El Empalme'  => ['provincia' => 'Guayas', 'canton' => 'El Empalme'],
    'Buena Fe'    => ['provincia' => 'Los Ríos', 'canton' => 'Buena Fe'],
    'Valencia'    => ['provincia' => 'Los Ríos', 'canton' => 'Valencia'],
    'Quevedo'     => ['provincia' => 'Los Ríos', 'canton' => 'Quevedo'],
    'Ventanas'    => ['provincia' => 'Los Ríos', 'canton' => 'Ventanas'],
];

function getProvincia($zona, $mapeo) {
    $zona = trim($zona);
    return $mapeo[$zona]['provincia'] ?? 'Guayas';
}

function getCanton($zona, $mapeo) {
    $zona = trim($zona);
    return $mapeo[$zona]['canton'] ?? 'Pedro Carbo';
}

try {
    // ── 1. Buscar socios con LPA sin acuerdo ───────────────────────────────────
    $sql = "
        SELECT
            l.id_lpa,
            l.id_socio,
            l.anio,
            l.zona,
            l.comunidad_grupo,
            l.area_cacao_ha,
            l.volumen_produccion_estimado,
            l.id_periodo,
            s.identificacion,
            COALESCE(s.nombre_completo, CONCAT(s.nombres, ' ', s.apellidos)) AS nombre_completo,
            s.fecha_nacimiento
        FROM tabla_lpa l
        INNER JOIN socios s ON s.id_socio = l.id_socio
        WHERE NOT EXISTS (
            SELECT 1 FROM acuerdo_productor ap
            WHERE ap.id_socio = l.id_socio
        )
        AND l.estado_lpa = 'activo'
        ORDER BY s.nombre_completo ASC
    ";

    $stmt = $pdo->query($sql);
    $socios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "📊 Socios con LPA sin acuerdo_productor: " . count($socios) . "\n\n";

    if (empty($socios)) {
        echo "✅ Todos los socios ya tienen acuerdo_productor\n";
        exit;
    }

    // ── 2. Generar número de acuerdo secuencial ────────────────────────────────
    $stMaxAcuerdo = $pdo->query("SELECT MAX(CAST(SUBSTRING(numero_acuerdo FROM 5) AS UNSIGNED)) AS max_num FROM acuerdo_productor WHERE numero_acuerdo LIKE 'ACU-%'");
    $maxNum = $stMaxAcuerdo->fetchColumn() ?: 0;
    $contadorAcuerdo = $maxNum + 1;

    // ── 3. Insertar acuerdos ───────────────────────────────────────────────────
    $sqlInsert = "
        INSERT INTO acuerdo_productor (
            id_socio,
            numero_acuerdo,
            cedula,
            fecha_nacimiento,
            nombres_completos,
            provincia,
            canton,
            parroquia,
            sector,
            posee_riego,
            periodo_de_fertilizacion,
            cacao_nacional_has,
            estimado_produccion_nacional,
            cacao_ccn51_has,
            estimado_produccion_ccn51,
            fecha_firma,
            id_periodo
        ) VALUES (
            :id_socio,
            :numero_acuerdo,
            :cedula,
            :fecha_nacimiento,
            :nombres_completos,
            :provincia,
            :canton,
            :parroquia,
            :sector,
            :posee_riego,
            :periodo_de_fertilizacion,
            :cacao_nacional_has,
            :estimado_produccion_nacional,
            :cacao_ccn51_has,
            :estimado_produccion_ccn51,
            :fecha_firma,
            :id_periodo
        )
    ";

    $stInsert = $pdo->prepare($sqlInsert);
    $insertados = 0;
    $errores = [];

    foreach ($socios as $socio) {
        // Limpiar y validar datos
        $zona             = trim($socio['zona'] ?? '');
        $comunidad_grupo  = trim($socio['comunidad_grupo'] ?? '');
        
        // Si zona está vacía, usar comunidad_grupo
        if (empty($zona) && !empty($comunidad_grupo)) {
            $zona = $comunidad_grupo;
        }

        // Determinar provincia y cantón
        $provincia = getProvincia($zona, $mapeoZonas);
        $canton    = getCanton($zona, $mapeoZonas);

        // parroquia = zona del LPA
        // sector = comunidad_grupo del LPA
        $parroquia = !empty($zona) ? $zona : 'No especificada';
        $sector    = !empty($comunidad_grupo) ? $comunidad_grupo : 'No especificado';

        // Generar número de acuerdo
        $numeroAcuerdo = 'ACU-' . str_pad($contadorAcuerdo, 4, '0', STR_PAD_LEFT);
        $contadorAcuerdo++;

        // Fecha de nacimiento
        $fechaNac = $socio['fecha_nacimiento'] ?? null;
        if (empty($fechaNac) || $fechaNac === '0000-00-00') {
            $fechaNac = '2000-01-01'; // Valor por defecto
        }

        try {
            $stInsert->execute([
                ':id_socio'                        => $socio['id_socio'],
                ':numero_acuerdo'                  => $numeroAcuerdo,
                ':cedula'                          => $socio['identificacion'],
                ':fecha_nacimiento'                => $fechaNac,
                ':nombres_completos'               => $socio['nombre_completo'],
                ':provincia'                       => $provincia,
                ':canton'                          => $canton,
                ':parroquia'                       => $parroquia,
                ':sector'                          => $sector,
                ':posee_riego'                     => 'NO',
                ':periodo_de_fertilizacion'        => '2',
                ':cacao_nacional_has'              => $socio['area_cacao_ha'] ?? 0,
                ':estimado_produccion_nacional'    => $socio['volumen_produccion_estimado'] ?? 0,
                ':cacao_ccn51_has'                 => 0,
                ':estimado_produccion_ccn51'       => 0,
                ':fecha_firma'                     => date('Y-m-d'),
                ':id_periodo'                      => $socio['id_periodo']
            ]);

            $insertados++;
            echo "✅ {$socio['identificacion']} - {$socio['nombre_completo']} → {$numeroAcuerdo}\n";
            echo "   📍 {$provincia} / {$canton} / {$parroquia} / {$sector}\n\n";

        } catch (PDOException $e) {
            $errores[] = [
                'socio' => $socio['nombre_completo'],
                'error' => $e->getMessage()
            ];
        }
    }

    echo "\n" . str_repeat('═', 70) . "\n";
    echo "✅ MIGRACIÓN COMPLETADA\n";
    echo "📊 Total procesados: " . count($socios) . "\n";
    echo "✔️  Insertados: {$insertados}\n";
    echo "❌ Errores: " . count($errores) . "\n";

    if (!empty($errores)) {
        echo "\n⚠️  ERRORES:\n";
        foreach ($errores as $err) {
            echo "   - {$err['socio']}: {$err['error']}\n";
        }
    }

    echo "\n🎉 Ahora todos los socios tienen acuerdo_productor con los datos correctos.\n";
    echo "Los PDFs se generarán correctamente con provincia, cantón, parroquia y sector.\n";

} catch (PDOException $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>