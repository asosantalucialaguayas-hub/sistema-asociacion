<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario'])) {
    die('Acceso denegado');
}

require "config/conexion.php";

/* ===============================
   CONSULTA RNA COMPLETA
================================ */
$sql = "
SELECT
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

    d.provincia,
    d.canton,
    d.parroquia,
    d.recinto,
    d.referencia,

    pr.nombre_predio,
    pr.provincia AS pred_provincia,
    pr.canton AS pred_canton,
    pr.parroquia AS pred_parroquia,
    pr.recinto AS pred_recinto,
    pr.vive_en_predio,
    pr.forma_tenencia,
    pr.area_has,

    g.x,
    g.y,
    g.z,

    a.principal_ingreso,
    a.actividad,
    a.rubro,

    u.usuario_rna,
    u.contrasena_rna,
    u.fecha_registro

FROM rna_persona p
LEFT JOIN rna_domicilio d ON d.id_persona = p.id_persona
LEFT JOIN rna_predio pr ON pr.id_persona = p.id_persona
LEFT JOIN rna_georreferenciacion g ON g.id_predio = pr.id_predio
LEFT JOIN rna_actividad a ON a.id_predio = pr.id_predio
LEFT JOIN rna_usuario u ON u.id_persona = p.id_persona
ORDER BY p.id_persona DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ===============================
   CABECERAS EXCEL CON HTML Y ESTILOS
================================ */
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=RNA_" . date('Y-m-d_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

?>
<html xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<style>
    body { font-family: Arial, sans-serif; font-size: 11pt; margin: 10px; }
    .header { text-align: center; font-size: 16pt; font-weight: bold; color: #1f3a5f; margin: 15px 0 5px 0; }
    .subheader { text-align: center; font-size: 11pt; color: #666; margin: 2px 0; }
    table { 
        width: 100%; 
        border-collapse: collapse; 
        margin: 15px 0;
    }
    th { 
        background-color: #2563eb; 
        color: white; 
        padding: 8px 5px; 
        text-align: left; 
        font-weight: bold;
        border: 1px solid #1f3a5f;
        font-size: 9pt;
    }
    td { 
        padding: 5px 5px; 
        border: 1px solid #ddd; 
        font-size: 9pt;
    }
    tr:nth-child(even) { 
        background-color: #f5f5f5; 
    }
    tr:hover { 
        background-color: #e8f0f8; 
    }
</style>
</head>
<body>

<!-- ENCABEZADO PROFESIONAL -->
<div class="header">REGISTRO NACIONAL AGROPECUARIO (RNA)</div>
<div class="subheader">Listado Completo de Registros</div>
<div class="subheader">Generado: <?= date('d/m/Y H:i:s') ?></div>

<br>

<?php
// TABLA ÚNICA CON TODOS LOS CAMPOS
echo '<table>';
echo '<tr>
    <th>ID</th>
    <th>Cédula</th>
    <th>Nombres</th>
    <th>Apellidos</th>
    <th>Género</th>
    <th>Fecha Nacimiento</th>
    <th>Celular</th>
    <th>Correo</th>
    <th>Se Registra Como</th>
    <th>Nacionalidad</th>
    <th>Autoidentificación</th>
    <th>Instrucción Formal</th>
    <th>Años Educación</th>
    <th>Lugar Nacimiento</th>
    <th>Situación Movilidad</th>
    <th>Estado Completitud</th>
    <th>Provincia (Domicilio)</th>
    <th>Cantón (Domicilio)</th>
    <th>Parroquia (Domicilio)</th>
    <th>Recinto (Domicilio)</th>
    <th>Referencia</th>
    <th>Nombre Predio</th>
    <th>Provincia (Predio)</th>
    <th>Cantón (Predio)</th>
    <th>Parroquia (Predio)</th>
    <th>Recinto (Predio)</th>
    <th>Vive en Predio</th>
    <th>Forma de Tenencia</th>
    <th>Área (Has)</th>
    <th>Coordenada X</th>
    <th>Coordenada Y</th>
    <th>Coordenada Z</th>
    <th>Principal Ingreso</th>
    <th>Actividad</th>
    <th>Rubro</th>
    <th>Usuario RNA</th>
    <th>Fecha Registro</th>
</tr>';

foreach ($datos as $row) {
    echo '<tr>
        <td>' . htmlspecialchars($row['id_persona'] ?? '') . '</td>
        <td>' . htmlspecialchars($row['cedula'] ?? '') . '</td>
        <td>' . htmlspecialchars($row['nombres'] ?? '') . '</td>
        <td>' . htmlspecialchars($row['apellidos'] ?? '') . '</td>
        <td>' . htmlspecialchars($row['genero'] ?? '') . '</td>
        <td>' . htmlspecialchars($row['fecha_nacimiento'] ?? '') . '</td>
        <td>' . htmlspecialchars($row['celular'] ?? '') . '</td>
        <td>' . htmlspecialchars($row['correo'] ?? '') . '</td>
        <td>' . htmlspecialchars($row['se_registra_como'] ?? '') . '</td>
        <td>' . htmlspecialchars($row['nacionalidad'] ?? '') . '</td>
        <td>' . htmlspecialchars($row['autoidentificacion'] ?? '') . '</td>
        <td>' . htmlspecialchars($row['instruccion_formal'] ?? '') . '</td>
        <td>' . htmlspecialchars($row['anios_educacion'] ?? '') . '</td>
        <td>' . htmlspecialchars($row['lugar_nacimiento'] ?? '') . '</td>
        <td>' . htmlspecialchars($row['situacion_movilidad'] ?? '') . '</td>
        <td>' . htmlspecialchars($row['estado_completitud'] ?? '') . '</td>
        <td>' . htmlspecialchars($row['provincia'] ?? '') . '</td>
        <td>' . htmlspecialchars($row['canton'] ?? '') . '</td>
        <td>' . htmlspecialchars($row['parroquia'] ?? '') . '</td>
        <td>' . htmlspecialchars($row['recinto'] ?? '') . '</td>
        <td>' . htmlspecialchars($row['referencia'] ?? '') . '</td>
        <td>' . htmlspecialchars($row['nombre_predio'] ?? '') . '</td>
        <td>' . htmlspecialchars($row['pred_provincia'] ?? '') . '</td>
        <td>' . htmlspecialchars($row['pred_canton'] ?? '') . '</td>
        <td>' . htmlspecialchars($row['pred_parroquia'] ?? '') . '</td>
        <td>' . htmlspecialchars($row['pred_recinto'] ?? '') . '</td>
        <td>' . htmlspecialchars($row['vive_en_predio'] ?? '') . '</td>
        <td>' . htmlspecialchars($row['forma_tenencia'] ?? '') . '</td>
        <td>' . htmlspecialchars($row['area_has'] ?? '') . '</td>
        <td>' . htmlspecialchars($row['x'] ?? '') . '</td>
        <td>' . htmlspecialchars($row['y'] ?? '') . '</td>
        <td>' . htmlspecialchars($row['z'] ?? '') . '</td>
        <td>' . htmlspecialchars($row['principal_ingreso'] ?? '') . '</td>
        <td>' . htmlspecialchars($row['actividad'] ?? '') . '</td>
        <td>' . htmlspecialchars($row['rubro'] ?? '') . '</td>
        <td>' . htmlspecialchars($row['usuario_rna'] ?? '') . '</td>
        <td>' . htmlspecialchars($row['fecha_registro'] ?? '') . '</td>
    </tr>';
}
echo '</table>';

// FOOTER
echo '<div class="subheader" style="margin-top: 20px;">Total de registros: ' . count($datos) . '</div>';
echo '<div class="subheader">Documento generado por el sistema RNA</div>';

?>

</body>
</html>
            <td>{$row['actividad']}</td>
            <td>{$row['rubro']}</td>

            <td>{$row['usuario_rna']}</td>
            <td>{$row['fecha_registro']}</td>
          </tr>";
}

echo "</table>";
