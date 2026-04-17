<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}
require __DIR__ . "/layout/bootstrap.php";
header('Content-Type: application/json; charset=utf-8');

// ── Apagar warnings de PHP para que no contaminen el JSON ──
error_reporting(0);
ini_set('display_errors', 0);

$conv_id         = intval($_GET['conv_id'] ?? 0);
$tipo_asistentes = trim($_GET['tipo_asistentes'] ?? 'general');
$q_raw           = trim($_GET['q'] ?? '');

if ($q_raw === '') {
    echo json_encode([]);
    exit;
}

// ── Normalizar búsqueda: quitar tildes para buscar sin importar acentos ──
function quitarTildes(string $s): string {
    $s = mb_strtolower($s, 'UTF-8');
    $find    = ['á','é','í','ó','ú','ü','ñ','à','è','ì','ò','ù','â','ê','î','ô','û','ã','õ'];
    $replace = ['a','e','i','o','u','u','n','a','e','i','o','u','a','e','i','o','u','a','o'];
    return str_replace($find, $replace, $s);
}

$q_norm = quitarTildes($q_raw);
$q_like = '%' . $q_norm . '%';
$q_orig = '%' . $q_raw . '%';

try {
    // ── Forzar collation utf8mb4 en la conexión ──
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci");

    if ($tipo_asistentes === 'solo_directivos') {

        $stP  = $pdo->query("SELECT id FROM directiva_periodos WHERE estado='activo' ORDER BY id DESC LIMIT 1");
        $pRow = $stP->fetch(PDO::FETCH_ASSOC);
        if (!$pRow) {
            echo json_encode([['_error' => 'No hay período de directiva activo.']]);
            exit;
        }
        $pid = (int)$pRow['id'];

        $st = $pdo->prepare("
            SELECT
                COALESCE(s.id_socio, dm.socio_id)                    AS id,
                COALESCE(s.identificacion, dm.cedula_manual)          AS cedula,
                COALESCE(s.nombre_completo, dm.nombre_manual)         AS nombre_completo,
                IF(a.id IS NOT NULL, 1, 0)                            AS ya_registro,
                IF(s.id_socio IS NOT NULL, 0, 1)                      AS sin_socio,
                MIN(dm.orden_cargo)                                    AS orden_cargo
            FROM directiva_miembros dm
            LEFT JOIN socios s
                   ON CONVERT(s.identificacion USING utf8mb4) COLLATE utf8mb4_general_ci
                    = CONVERT(dm.cedula_manual  USING utf8mb4) COLLATE utf8mb4_general_ci
                  AND s.estado = 'activo'
            LEFT JOIN conv_asistencia a
                   ON a.id_socio        = COALESCE(s.id_socio, dm.socio_id)
                  AND a.convocatoria_id = ?
            WHERE dm.periodo_id = ?
              AND (
                    -- Buscar por nombre original (con tildes)
                    CONVERT(COALESCE(s.nombre_completo, dm.nombre_manual) USING utf8mb4)
                        COLLATE utf8mb4_general_ci LIKE CONVERT(? USING utf8mb4) COLLATE utf8mb4_general_ci
                    -- Buscar por cédula
                 OR CONVERT(COALESCE(s.identificacion, dm.cedula_manual) USING utf8mb4)
                        COLLATE utf8mb4_general_ci LIKE CONVERT(? USING utf8mb4) COLLATE utf8mb4_general_ci
              )
            GROUP BY CONVERT(COALESCE(s.identificacion, dm.cedula_manual) USING utf8mb4) COLLATE utf8mb4_general_ci
            ORDER BY sin_socio ASC, orden_cargo ASC, nombre_completo ASC
            LIMIT 15
        ");
        $st->execute([$conv_id, $pid, $q_orig, $q_orig]);

    } else {

        // ── Búsqueda GENERAL con soporte de tildes ──
        // Estrategia: buscar tanto con el término original como con versión sin tilde
        // usando REPLACE en SQL para normalizar el campo nombre_completo
        $st = $pdo->prepare("
            SELECT
                s.id_socio                         AS id,
                s.identificacion                   AS cedula,
                s.nombre_completo,
                IF(a.id IS NOT NULL, 1, 0)         AS ya_registro
            FROM socios s
            LEFT JOIN conv_asistencia a
                   ON a.id_socio        = s.id_socio
                  AND a.convocatoria_id = ?
            WHERE s.estado = 'activo'
              AND (
                    -- Búsqueda normal (con tildes, collation insensible)
                    CONVERT(s.nombre_completo USING utf8mb4) COLLATE utf8mb4_general_ci
                        LIKE CONVERT(? USING utf8mb4) COLLATE utf8mb4_general_ci
                    -- Búsqueda por cédula exacta
                 OR CONVERT(s.identificacion USING utf8mb4) COLLATE utf8mb4_general_ci
                        LIKE CONVERT(? USING utf8mb4) COLLATE utf8mb4_general_ci
                    -- Búsqueda sin tildes: normalizar nombre en MySQL
                 OR LOWER(
                        REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                        REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                        CONVERT(s.nombre_completo USING utf8mb4),
                        'á','a'),'é','e'),'í','i'),'ó','o'),'ú','u'),
                        'Á','a'),'É','e'),'Í','i'),'Ó','o'),'Ú','u')
                    ) LIKE LOWER(?)
                    -- Búsqueda ñ → n
                 OR LOWER(
                        REPLACE(
                        REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                        REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                        CONVERT(s.nombre_completo USING utf8mb4),
                        'á','a'),'é','e'),'í','i'),'ó','o'),'ú','u'),
                        'Á','a'),'É','e'),'Í','i'),'Ó','o'),'Ú','u'),
                        'ñ','n')
                    ) LIKE LOWER(?)
              )
            ORDER BY s.nombre_completo ASC
            LIMIT 15
        ");
        // Parámetros: conv_id, q_orig, q_orig (cédula), q_like_norm, q_like_norm (con ñ)
        $st->execute([$conv_id, $q_orig, $q_orig, $q_like, $q_like]);
    }

    $socios = $st->fetchAll(PDO::FETCH_ASSOC);

    foreach ($socios as &$s) {
        $nombre = trim($s['nombre_completo'] ?? '');
        $partes = array_filter(explode(' ', $nombre));
        $partes = array_values($partes);
        $ini    = strtoupper(
            mb_substr($partes[0] ?? '', 0, 1, 'UTF-8') .
            mb_substr($partes[1] ?? '', 0, 1, 'UTF-8')
        );
        $s['iniciales'] = $ini ?: '?';
        unset($s['sin_socio'], $s['orden_cargo']);
    }
    unset($s);

    echo json_encode($socios, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([['_error' => $e->getMessage()]]);
}