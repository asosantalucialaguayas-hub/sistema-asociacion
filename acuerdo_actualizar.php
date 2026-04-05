<?php
// acuerdo_actualizar.php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

require "config/conexion.php";

try {
    $id_acuerdo         = intval($_POST['id_acuerdo']         ?? 0);
    $nombres_completos  = trim($_POST['nombres_completos']    ?? '');
    $cedula             = trim($_POST['cedula']               ?? '');
    $provincia          = trim($_POST['provincia']            ?? '');
    $canton             = trim($_POST['canton']               ?? '');
    $parroquia          = trim($_POST['parroquia']            ?? '');
    $sector             = trim($_POST['sector']               ?? '');

    if (!$id_acuerdo || !$nombres_completos || !$cedula) {
        echo json_encode(['success' => false, 'message' => 'Datos requeridos faltantes']);
        exit;
    }

    // Verificar que el acuerdo existe
    $stCheck = $pdo->prepare("SELECT id_acuerdo FROM acuerdo_productor WHERE id_acuerdo = :id");
    $stCheck->bindValue(':id', $id_acuerdo, PDO::PARAM_INT);
    $stCheck->execute();
    if (!$stCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Acuerdo no encontrado']);
        exit;
    }

    // Actualizar
    $sql = "
        UPDATE acuerdo_productor SET
            nombres_completos = :nombres_completos,
            cedula            = :cedula,
            provincia         = :provincia,
            canton            = :canton,
            parroquia         = :parroquia,
            sector            = :sector
        WHERE id_acuerdo = :id_acuerdo
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':nombres_completos', $nombres_completos, PDO::PARAM_STR);
    $stmt->bindValue(':cedula',            $cedula,            PDO::PARAM_STR);
    $stmt->bindValue(':provincia',         $provincia,         PDO::PARAM_STR);
    $stmt->bindValue(':canton',            $canton,            PDO::PARAM_STR);
    $stmt->bindValue(':parroquia',         $parroquia,         PDO::PARAM_STR);
    $stmt->bindValue(':sector',            $sector,            PDO::PARAM_STR);
    $stmt->bindValue(':id_acuerdo',        $id_acuerdo,        PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'message' => 'Acuerdo actualizado correctamente'
    ]);

} catch (PDOException $e) {
    error_log("acuerdo_actualizar.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error BD: ' . $e->getMessage()]);
}
?>