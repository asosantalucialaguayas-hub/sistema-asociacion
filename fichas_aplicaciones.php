<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { header("Location: /auth/login.php"); exit; }
require __DIR__ . "/layout/bootstrap.php";

$id_usuario = (int)($_SESSION['id_usuario'] ?? 0);
if ($id_usuario && function_exists('tienePermiso') && isset($pdo)) {
    $puede_modificar = tienePermiso($pdo, $id_usuario, 'fichas_registros', 'puede_modificar');
    $puede_eliminar  = tienePermiso($pdo, $id_usuario, 'fichas_registros', 'puede_eliminar');
} else {
    $puede_modificar = $puede_eliminar = false;
}

$id_ficha = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$buscar   = trim($_GET['q'] ?? '');

$ficha = null;
if ($id_ficha) {
    $st = $pdo->prepare("SELECT * FROM fichas WHERE id_ficha=?");
    $st->execute([$id_ficha]);
    $ficha = $st->fetch(PDO::FETCH_ASSOC);
}

// Aplicaciones
$params = [];
$where  = $id_ficha ? "WHERE fa.id_ficha=?" : "WHERE 1=1";
if ($id_ficha) $params[] = $id_ficha;
if ($buscar) {
    $where .= " AND (s.nombre_completo LIKE ? OR s.identificacion LIKE ?)";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

$stmt = $pdo->prepare("
    SELECT fa.*, f.nombre AS nombre_ficha,
           s.nombre_completo, s.identificacion,
           u.usuario AS inspector
    FROM ficha_aplicaciones fa
    JOIN fichas f ON f.id_ficha = fa.id_ficha
    JOIN socios s ON s.id_socio = fa.id_socio
    LEFT JOIN usuarios u ON u.id_usuario = fa.id_usuario
    $where
    ORDER BY fa.fecha_aplicacion DESC
");
$stmt->execute($params);
$aplicaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Todas las fichas para el filtro
$todas_fichas = $pdo->query("SELECT id_ficha,nombre FROM fichas ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Registros de Fichas</title>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--azul:#1f3a5f;--azul2:#2563eb;--gris:#f8fafc;--borde:#e2e8f0;--sombra:0 2px 12px rgba(0,0,0,.08);}
body{font-family:'Segoe UI',sans-serif;background:var(--gris);}
.btn-prim{display:inline-flex;align-items:center;gap:7px;background:linear-gradient(135deg,#1f3a5f,#2563eb);color:#fff;border:none;border-radius:10px;padding:10px 20px;font-weight:700;font-size:.875rem;cursor:pointer;text-decoration:none;transition:.2s;}
.btn-prim:hover{transform:translateY(-2px);color:#fff;}
.btn-xs{display:inline-flex;align-items:center;gap:4px;padding:5px 10px;border-radius:7px;font-size:.75rem;font-weight:700;cursor:pointer;text-decoration:none;border:1.5px solid transparent;}
.bx-eye{background:#dcfce7;color:#166534;border-color:#bbf7d0;}
.bx-pdf{background:#fef3c7;color:#92400e;border-color:#fde68a;}
.card{background:#fff;border-radius:14px;border:1.5px solid var(--borde);overflow:hidden;box-shadow:var(--sombra);}
.card-head{background:linear-gradient(135deg,#1f3a5f,#2563eb);padding:14px 20px;color:#fff;display:flex;align-items:center;justify-content:space-between;}
.card-head h3{margin:0;font-size:.95rem;font-weight:700;display:flex;align-items:center;gap:8px;}
.filtros{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;align-items:center;}
.filtros input,.filtros select{border:1.5px solid var(--borde);border-radius:8px;padding:8px 12px;font-size:.85rem;background:#fff;}
.tbl{width:100%;border-collapse:collapse;}
.tbl thead{background:#1f3a5f;color:#fff;}
.tbl th{padding:11px 14px;font-size:.78rem;font-weight:700;text-align:left;}
.tbl td{padding:10px 14px;border-bottom:1px solid #f1f5f9;font-size:.84rem;vertical-align:middle;}
.tbl tr:hover td{background:#f8fafc;}
.av{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#1f3a5f,#2563eb);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.75rem;flex-shrink:0;}
.empty{text-align:center;padding:60px;color:#94a3b8;}
</style>
</head>
<body>
<div class="app">
<?php include __DIR__ . "/layout/sidebar.php"; ?>
<main class="content">
<header class="topbar">
    <span>Bienvenido, <strong><?= htmlspecialchars($_SESSION['usuario']) ?></strong></span>
</header>
<section class="page">

<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
    <div>
        <h1 style="font-size:1.4rem;font-weight:800;color:var(--azul);margin:0;display:flex;align-items:center;gap:10px;">
            <i class="fa-solid fa-file-lines" style="color:var(--azul2);"></i>
            <?= $ficha ? 'Registros: '.htmlspecialchars($ficha['nombre']) : 'Todos los Registros' ?>
        </h1>
        <p style="margin:4px 0 0;font-size:.875rem;color:#64748b;"><?= count($aplicaciones) ?> ficha(s) aplicada(s)</p>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="fichas_lista.php" style="color:#64748b;text-decoration:none;display:flex;align-items:center;gap:6px;font-size:.85rem;">
            <i class="fa-solid fa-arrow-left"></i> Fichas
        </a>
        <a href="fichas_aplicar.php<?= $id_ficha ? '?ficha='.$id_ficha : '' ?>" class="btn-prim">
            <i class="fa-solid fa-plus"></i> Nueva aplicación
        </a>
    </div>
</div>

<!-- Filtros -->
<form method="GET" class="filtros">
    <?php if ($id_ficha): ?>
    <input type="hidden" name="id" value="<?= $id_ficha ?>">
    <?php else: ?>
    <select name="id" onchange="this.form.submit()">
        <option value="">— Todas las fichas —</option>
        <?php foreach ($todas_fichas as $tf): ?>
        <option value="<?= $tf['id_ficha'] ?>" <?= $id_ficha==$tf['id_ficha']?'selected':'' ?>>
            <?= htmlspecialchars($tf['nombre']) ?>
        </option>
        <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <input type="text" name="q" value="<?= htmlspecialchars($buscar) ?>" placeholder="Buscar socio...">
    <button type="submit" class="btn-prim" style="padding:8px 16px;">
        <i class="fa-solid fa-search"></i>
    </button>
    <?php if ($buscar || $id_ficha): ?>
    <a href="fichas_aplicaciones.php" style="color:#64748b;font-size:.85rem;text-decoration:none;">
        <i class="fa-solid fa-xmark"></i> Limpiar
    </a>
    <?php endif; ?>
</form>

<div class="card">
    <div class="card-head">
        <h3><i class="fa-solid fa-table-list"></i> Aplicaciones</h3>
    </div>
    <?php if (empty($aplicaciones)): ?>
    <div class="empty">
        <i class="fa-solid fa-file-circle-xmark" style="font-size:2.5rem;display:block;margin-bottom:10px;"></i>
        <p>No hay registros<?= $buscar ? ' para "'.$buscar.'"' : '' ?>.</p>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="tbl">
        <thead>
            <tr>
                <th>#</th>
                <th>Socio</th>
                <th>Ficha</th>
                <th>Inspector</th>
                <th>Fecha</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($aplicaciones as $i => $a):
            $partes = explode(' ', $a['nombre_completo']);
            $ini = strtoupper(substr($partes[0],0,1).(isset($partes[1])?substr($partes[1],0,1):''));
        ?>
        <tr>
            <td style="color:#94a3b8;font-weight:700;"><?= $i+1 ?></td>
            <td>
                <div style="display:flex;align-items:center;gap:10px;">
                    <div class="av"><?= $ini ?></div>
                    <div>
                        <div style="font-weight:700;"><?= htmlspecialchars($a['nombre_completo']) ?></div>
                        <div style="font-size:.75rem;color:#64748b;"><?= htmlspecialchars($a['identificacion']) ?></div>
                    </div>
                </div>
            </td>
            <td>
                <span style="background:#e0f2fe;color:#0369a1;padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:700;">
                    <?= htmlspecialchars($a['nombre_ficha']) ?>
                </span>
            </td>
            <td><?= htmlspecialchars($a['inspector'] ?? '—') ?></td>
            <td><?= date('d/m/Y H:i', strtotime($a['fecha_aplicacion'])) ?></td>
            <td>
                <div style="display:flex;gap:5px;">
                    <a href="fichas_ver.php?id=<?= $a['id_aplicacion'] ?>" class="btn-xs bx-eye">
                        <i class="fa-solid fa-eye"></i> Ver
                    </a>
                    <a href="fichas_pdf.php?id=<?= $a['id_aplicacion'] ?>" target="_blank" class="btn-xs bx-pdf">
                        <i class="fa-solid fa-file-pdf"></i> PDF
                    </a>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

</section>
</main>
</div>
</body>
</html>
