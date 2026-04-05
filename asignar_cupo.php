
<?php
require "layout/bootstrap.php";
include "layout/selector-periodo.php";
require "vendor/autoload.php";
require __DIR__ . "/config/periodo_guard.php";
header('Content-Type: application/json; charset=utf-8');
$periodo = require_periodo_abierto_json($pdo); // 🔒 candado de período

use Dompdf\Dompdf;
use Dompdf\Options;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    die(json_encode(['success' => false, 'message' => 'Método no permitido']));
}

try {
    $id_acuerdo = $_POST['id_acuerdo'] ?? null;
    $cupo_nacional = trim($_POST['cupo_nacional'] ?? '0');
    $cupo_ccn51 = trim($_POST['cupo_ccn51'] ?? '0');
    
    if (!$id_acuerdo) {
        throw new Exception('ID de acuerdo no proporcionado');
    }

    // Obtener datos del acuerdo
    $stmt = $pdo->prepare("SELECT * FROM acuerdo_productor WHERE id_acuerdo = ?");
    $stmt->execute([$id_acuerdo]);
    $acuerdo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$acuerdo) {
        throw new Exception('Acuerdo no encontrado');
    }

    // Si ya tiene PDF, no generar de nuevo
    if (!empty($acuerdo['archivo_pdf'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Este acuerdo ya tiene asignado un cupo'
        ]);
        exit;
    }

    // Preparar logo en base64
    $logoPath = __DIR__ . "/img/logo.png";
    $logoSrc = '';
    if (file_exists($logoPath)) {
        $logoData = base64_encode(file_get_contents($logoPath));
        $logoSrc = 'data:image/png;base64,' . $logoData;
    }

    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);

    $fecha = !empty($acuerdo['fecha_firma']) ? date('d/m/Y', strtotime($acuerdo['fecha_firma'])) : '';

    // Si se proporcionan cupos, mostrarlos en lugar del estimado de producción
    $estN = ((float)$cupo_nacional != 0) ? $cupo_nacional : ($acuerdo['estimado_produccion_nacional'] ?? '0');
    $estC = ((float)$cupo_ccn51 != 0) ? $cupo_ccn51 : ($acuerdo['estimado_produccion_ccn51'] ?? '0');

    $html = '
    <!DOCTYPE html>
    <html lang="es">
    <head>
    <meta charset="UTF-8">
    <style>
    @page {
        margin: 2cm 2.5cm;
    }

    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 11px;
        line-height: 1.5;
        position: relative;
        margin: 0;
        padding: 0;
    }

    body::before {
        content: "";
        position: fixed;
        top: 50%;
        left: 50%;
        width: 400px;
        height: 400px;
        margin-left: -200px;
        margin-top: -200px;
        background-image: url("' . $logoSrc . '");
        background-size: contain;
        background-position: center;
        background-repeat: no-repeat;
        opacity: 0.08;
        z-index: -1;
    }

    .header-title {
        text-align: center;
        font-weight: bold;
        font-size: 12px;
        text-decoration: underline;
        margin-bottom: 20px;
        margin-top: 0;
    }

    p {
        text-align: justify;
        margin: 0 0 10px 0;
        line-height: 1.6;
    }

    .info-line {
        margin: 8px 0;
    }

    .section-title {
        font-weight: bold;
        font-size: 11px;
        margin-top: 15px;
        margin-bottom: 10px;
        text-align: left;
    }

    ol {
        margin: 8px 0;
        padding-left: 25px;
    }

    ol li {
        margin-bottom: 8px;
        text-align: justify;
        line-height: 1.6;
    }

    .table-data {
        width: 100%;
        margin: 15px 0;
    }

    .table-data td {
        padding: 5px 0;
        vertical-align: top;
    }

    .page-break {
        page-break-before: always;
    }

    .firma-section {
        margin-top: 150px;
        text-align: center;
    }

    .firma-line-main {
        width: 300px;
        border-top: 1.5px solid #000;
        margin: 0 auto 15px auto;
    }

    .firma-text {
        font-weight: bold;
        font-size: 11px;
        margin-bottom: 80px;
    }

    .firma-container {
        display: table;
        width: 100%;
        margin-top: 60px;
    }

    .firma-col {
        display: table-cell;
        width: 50%;
        text-align: center;
        vertical-align: top;
    }

    .firma-line {
        width: 70%;
        border-top: 1px solid #000;
        margin: 0 auto 10px auto;
    }

    .firma-name {
        font-weight: bold;
        font-size: 10.5px;
    }

    .firma-cargo {
        font-size: 10px;
        line-height: 1.3;
    }
    </style>
    </head>
    <body>

    <div class="header-title">ACUERDO DE PRODUCTOR</div>

    <p>Por parte del Sr. Rosendo Muñoz Benavides presidente de la, Aso Santa Lucia Corotú, dentro de lo cual el presente documento es prueba que el productor ha sido informado de los objetivos y metas de la Aso. Santa Lucia Corotú en forma que pueda decir y comunicar su interés en participar en el programa de comercio justo que la Aso. Santa Lucia Corotú está en capacidad de cumplir, y hacer cumplir los deberes y beneficios correspondientes.</p>

    <div class="info-line">
        De otra parte, el (a): <strong>' . htmlspecialchars($acuerdo['nombres_completos']) . '</strong>
    </div>

    <div class="info-line">
        Con Cédula #: <strong>' . htmlspecialchars($acuerdo['cedula']) . '</strong>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        fecha de nacimiento: <strong>' . htmlspecialchars($acuerdo['fecha_nacimiento']) . '</strong>
    </div>

    <p>Quien confirma que ha sido informado, sin compromiso previo establecido entre las partes, quien es el propietario de la finca ubicada en el <strong>' . htmlspecialchars($acuerdo['sector']) . '</strong>, de <strong>' . htmlspecialchars($acuerdo['parroquia']) . '</strong> cantón <strong>' . htmlspecialchars($acuerdo['canton']) . '</strong></p>

    <p>En la provincia: <strong>' . htmlspecialchars($acuerdo['provincia']) . '</strong>, con un área cultivada de cacao y un estimado de producción detallado a continuación:</p>

    <table class="table-data">
        <tr>
            <td style="width:30%">Cacao Nacional</td>
            <td style="width:15%"><strong>' . htmlspecialchars($acuerdo['cacao_nacional_has']) . ' Ha</strong></td>
            <td style="width:35%">Estimado de producción</td>
            <td style="width:20%"><strong>' . htmlspecialchars($estN) . ' QQ</strong></td>
        </tr>
        <tr>
            <td>Cacao CCN51</td>
            <td><strong>' . htmlspecialchars($acuerdo['cacao_ccn51_has']) . ' Ha</strong></td>
            <td>Estimado de producción</td>
            <td><strong>' . htmlspecialchars($estC) . ' QQ</strong></td>
        </tr>
        <tr>
            <td>Posee riego si ( ' . (($acuerdo['posee_riego'] === 'SI' || $acuerdo['posee_riego'] === 'Si') ? 'X' : '&nbsp;&nbsp;') . ' ) o no ( ' . (($acuerdo['posee_riego'] === 'NO' || $acuerdo['posee_riego'] === 'No') ? 'X' : '&nbsp;&nbsp;') . ' )</td>
            <td colspan="2">Periodo de Fertilización por año</td>
            <td><strong>' . htmlspecialchars($acuerdo['periodo_de_fertilizacion']) . '</strong></td>
        </tr>
    </table>

    <div class="section-title">OBLIGACIONES DE LAS PARTES</div>

    <p><strong>La Asociación se compromete a lo siguiente:</strong></p>
    <ol>
        <li>Establecer un sistema interno de control para el manejo agronómico, cosecha y transporte del cacao certificado, entregándole al productor los materiales para el registro de todas las actividades según formato establecido.</li>
        <li>Dar Asistencia Técnica necesaria para uso racional de recursos naturales y crear condiciones para mejorar las fincas de sus socios.</li>
        <li>La Asociación se compromete en desarrollar proyectos productivos de manera equitativa con los ingresos por prima Fairtrade.</li>
        <li>La organización se compromete en dar a conocer y hacer cumplir los criterios Fairtrade para pequeños productores.</li>
    </ol>

    <p><strong>El productor se compromete a lo siguiente:</strong></p>
    <ol>
        <li>De forma voluntaria aceptar que la asociación me incluya en la lista de productores a trabajar bajo la certificación Fairtrade.</li>
        <li>Cumplir integra y responsable con el reglamento interno de la asociación.</li>
        <li>Notificar si el caso lo requiere la adquisición de nuevas unidades de producción (Incremento del área de producción) y acatar o apelar sanciones correspondientes o violaciones que puedan cumplir.</li>
        <li>En caso de contratar personal, garantizar el cumplimiento de las normas, leyes, reglamentos y convenios internacionales vigentes.</li>
        <li>Vender exclusivamente a nuestro socio comercial, el cacao certificado que ha sido producida bajo su administración e inscritas para la certificación de comercio justo.</li>
        <li>Permitir el acceso a su finca y todas las áreas bajo su administración al personal debidamente identificado.</li>
        <li>Cuidar áreas protegidas, mantener y proteger las especies (flora y fauna) si hubiese, en la finca y su entorno.</li>
        <li>Mantener libres de cualquier tipo de contaminación o basura, su finca y la comunidad donde residen.</li>
        <li>Tiene la potestad de apelar y expresar sus desacuerdos a través de los mecanismos establecidos para tales fines.</li>
        <li>El productor se compromete a cumplir con las normativas internacionales como el estándar Fairtrade, debida diligencia de los derechos humanos y ambientales (deforestación cero) y otras exigencias nacionales e internacionales que se estipulen.</li>
        <li>El productor se compromete en dar permiso a la organización para recopilar, almacenar y compartir datos.</li>
        <li>El productor se compromete a que su finca sea físicamente rastreable.</li>
        <li>El productor se compromete con la asociación a desarrollar sus cultivos sin la utilización de la deforestación, y por otro, lado implementa acciones que permiten la conservación del medio ambiente.</li>
    </ol>

    <div class="section-title">VIGENCIA DEL ACUERDO</div>

    <p>El presente acuerdo tiene una vigencia de (1) año con una renovación en mutuo acuerdo entre ambas partes.</p>

    <p style="margin-top: 20px;">Fecha de firma del Acuerdo: <strong>' . htmlspecialchars($fecha) . '</strong></p>

    <div class="firma-section">
        <div class="firma-line-main"></div>
        <div class="firma-text">FIRMA DEL PRODUCTOR</div>

        <div class="firma-container">
            <div class="firma-col">
                <div class="firma-line"></div>
                <div class="firma-name">Ing. Rosendo Muñoz</div>
                <div class="firma-cargo">Presidente Aso. SANTA LUCIA COROTU</div>
            </div>
            
            <div class="firma-col">
                <div class="firma-line"></div>
                <div class="firma-name">Sra. Germania Zambrano</div>
                <div class="firma-cargo">Secretaria Aso. SANTA LUCIA COROTU</div>
            </div>
        </div>
    </div>

    </body>
    </html>
    ';

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Guardar PDF
    $safeCupoN = preg_replace('/[^0-9A-Za-z\-_.]/', '', $cupo_nacional);
    $safeCupoC = preg_replace('/[^0-9A-Za-z\-_.]/', '', $cupo_ccn51);
    $safeCupoPart = '_N' . ($safeCupoN !== '' ? $safeCupoN : '0') . '_C' . ($safeCupoC !== '' ? $safeCupoC : '0');
    $pdfFileName = 'Acuerdo_' . $acuerdo['numero_acuerdo'] . $safeCupoPart . '_' . time() . '.pdf';
    $pdfDir = __DIR__ . '/documentos/pdf';

    if (!is_dir($pdfDir)) {
        mkdir($pdfDir, 0755, true);
    }

    $pdfPath = $pdfDir . '/' . $pdfFileName;
    file_put_contents($pdfPath, $dompdf->output());

    // Actualizar archivo_pdf en acuerdo_productor
    $updateStmt = $pdo->prepare("UPDATE acuerdo_productor SET archivo_pdf = ? WHERE id_acuerdo = ?");
    $updateStmt->execute(['/documentos/pdf/' . $pdfFileName, $id_acuerdo]);

    // Buscar id_solicitud y registrar en documentos_socios
    $searchStmt = $pdo->prepare("SELECT id_solicitud FROM solicitud_ingreso WHERE identificacion = ? LIMIT 1");
    $searchStmt->execute([$acuerdo['cedula']]);
    $solicitudRow = $searchStmt->fetch(PDO::FETCH_ASSOC);
    $id_solicitud = $solicitudRow ? $solicitudRow['id_solicitud'] : NULL;

    if ($id_solicitud) {
        $docStmt = $pdo->prepare("INSERT INTO documentos_socios (id_solicitud, tipo_documento, nombre, ruta_archivo) VALUES (?, ?, ?, ?)");
        $docName = 'Acuerdo de productor - ' . $acuerdo['numero_acuerdo'] . ' (N:' . $cupo_nacional . ' QQ, C:' . $cupo_ccn51 . ' QQ)';
        $docStmt->execute([
            $id_solicitud,
            'acuerdo',
            $docName,
            '/documentos/pdf/' . $pdfFileName
        ]);
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Cupo asignado y PDF generado correctamente'
    ]);

} catch (Exception $e) {
    error_log('Error en asignar_cupo.php: ' . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
exit;