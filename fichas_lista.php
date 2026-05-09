<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { header("Location: /auth/login.php"); exit; }
require __DIR__ . "/layout/bootstrap.php";

$id_usuario = (int)($_SESSION['id_usuario'] ?? 0);
if ($id_usuario && function_exists('tienePermiso') && isset($pdo)) {
    $puede_agregar   = tienePermiso($pdo, $id_usuario, 'fichas', 'puede_agregar');
    $puede_modificar = tienePermiso($pdo, $id_usuario, 'fichas', 'puede_modificar');
    $puede_eliminar  = tienePermiso($pdo, $id_usuario, 'fichas', 'puede_eliminar');
} else {
    $puede_agregar = $puede_modificar = $puede_eliminar = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_ficha']) && $puede_eliminar) {
    $id = (int)$_POST['id_ficha'];
    $pdo->prepare("DELETE FROM fichas WHERE id_ficha=?")->execute([$id]);
    $_SESSION['flash'] = ['tipo'=>'success','msg'=>'✅ Ficha eliminada.'];
    header("Location: fichas_lista.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_ficha']) && $puede_modificar) {
    $id  = (int)$_POST['id_ficha'];
    $act = (int)$_POST['activa'];
    $pdo->prepare("UPDATE fichas SET activa=? WHERE id_ficha=?")->execute([$act ? 0 : 1, $id]);
    header("Location: fichas_lista.php"); exit;
}

$fichas = $pdo->query("
    SELECT f.*,
           u.usuario AS creador,
           (SELECT COUNT(*) FROM ficha_secciones WHERE id_ficha=f.id_ficha) AS total_secciones,
           (SELECT COUNT(*) FROM ficha_preguntas p
            JOIN ficha_secciones s ON s.id_seccion=p.id_seccion
            WHERE s.id_ficha=f.id_ficha) AS total_preguntas,
           (SELECT COUNT(*) FROM ficha_aplicaciones WHERE id_ficha=f.id_ficha) AS total_aplicaciones
    FROM fichas f
    LEFT JOIN usuarios u ON u.id_usuario=f.creado_por
    ORDER BY f.id_ficha DESC
")->fetchAll(PDO::FETCH_ASSOC);

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Fichas – Asociación</title>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--azul:#1f3a5f;--azul2:#2563eb;--gris:#f8fafc;--borde:#e2e8f0;--sombra:0 2px 12px rgba(0,0,0,.08);}
body{font-family:'Segoe UI',sans-serif;background:var(--gris);}
.btn-prim{display:inline-flex;align-items:center;gap:7px;background:linear-gradient(135deg,#1f3a5f,#2563eb);color:#fff;border:none;border-radius:10px;padding:10px 20px;font-weight:700;font-size:.875rem;cursor:pointer;text-decoration:none;transition:.2s;}
.btn-prim:hover{transform:translateY(-2px);color:#fff;}
.btn-xs{display:inline-flex;align-items:center;gap:4px;padding:5px 10px;border-radius:7px;font-size:.75rem;font-weight:700;cursor:pointer;text-decoration:none;border:1.5px solid transparent;}
.bx-edit{background:#dbeafe;color:#1d4ed8;border-color:#bfdbfe;}
.bx-eye{background:#dcfce7;color:#166534;border-color:#bbf7d0;}
.bx-del{background:#fee2e2;color:#b91c1c;border-color:#fecaca;}
.flash{padding:12px 18px;border-radius:10px;margin-bottom:20px;display:flex;align-items:center;gap:10px;font-weight:600;}
.flash.success{background:#dcfce7;color:#166534;border:1px solid #bbf7d0;}
.flash.error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca;}
.fichas-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px;}
.ficha-card{background:#fff;border-radius:14px;border:1.5px solid var(--borde);box-shadow:var(--sombra);overflow:hidden;transition:.2s;}
.ficha-card:hover{transform:translateY(-3px);box-shadow:0 8px 24px rgba(0,0,0,.1);}
.fc-head{background:linear-gradient(135deg,#1f3a5f,#2563eb);padding:16px 18px;color:#fff;}
.fc-head h3{margin:0;font-size:1rem;font-weight:700;}
.fc-head .desc{font-size:.78rem;opacity:.8;margin-top:4px;}
.fc-body{padding:14px 18px;}
.fc-stat{display:flex;gap:10px;margin-bottom:10px;flex-wrap:wrap;}
.stat-pill{background:#f1f5f9;border-radius:20px;padding:4px 12px;font-size:.75rem;font-weight:700;color:#374151;display:flex;align-items:center;gap:5px;}
.fc-foot{padding:10px 18px;border-top:1px solid var(--borde);display:flex;gap:6px;align-items:center;background:#f9fafb;flex-wrap:wrap;}
.badge-activa{background:#dcfce7;color:#166534;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;}
.badge-inactiva{background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;}
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

<?php if ($flash): ?>
<div class="flash <?= $flash['tipo'] ?>">
    <i class="fa-solid <?= $flash['tipo']==='success'?'fa-circle-check':'fa-triangle-exclamation' ?>"></i>
    <?= htmlspecialchars($flash['msg']) ?>
</div>
<?php endif; ?>

<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
    <div>
        <h1 style="font-size:1.5rem;font-weight:800;color:var(--azul);margin:0;display:flex;align-items:center;gap:10px;">
            <i class="fa-solid fa-clipboard-list" style="color:var(--azul2);"></i> Fichas
        </h1>
        <p style="margin:4px 0 0;font-size:.875rem;color:#64748b;">Gestión de fichas de inspección</p>
    </div>
    <?php if ($puede_agregar): ?>
    <a href="fichas_form.php" class="btn-prim">
        <i class="fa-solid fa-plus"></i> Nueva Ficha
    </a>
    <?php endif; ?>
</div>

<?php if (empty($fichas)): ?>
<div class="empty">
    <i class="fa-solid fa-clipboard" style="font-size:3rem;display:block;margin-bottom:12px;"></i>
    <p>No hay fichas creadas aún.</p>
    <?php if ($puede_agregar): ?>
    <a href="fichas_form.php" class="btn-prim" style="margin-top:14px;display:inline-flex;">
        <i class="fa-solid fa-plus"></i> Crear primera ficha
    </a>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="fichas-grid">
<?php foreach ($fichas as $f): ?>
<div class="ficha-card">
    <div class="fc-head">
        <h3><?= htmlspecialchars($f['nombre']) ?></h3>
        <?php if ($f['descripcion']): ?>
        <div class="desc"><?= htmlspecialchars($f['descripcion']) ?></div>
        <?php endif; ?>
    </div>
    <div class="fc-body">
        <div class="fc-stat">
            <span class="stat-pill"><i class="fa-solid fa-layer-group"></i> <?= $f['total_secciones'] ?> secciones</span>
            <span class="stat-pill"><i class="fa-solid fa-list-check"></i> <?= $f['total_preguntas'] ?> preguntas</span>
            <span class="stat-pill"><i class="fa-solid fa-file-pen"></i> <?= $f['total_aplicaciones'] ?> aplicaciones</span>
        </div>
        <div style="font-size:.78rem;color:#94a3b8;">
            Creada: <b><?= date('d/m/Y', strtotime($f['creado_en'])) ?></b>
            · Por: <?= htmlspecialchars($f['creador'] ?? 'Sistema') ?>
        </div>
    </div>
    <div class="fc-foot">
        <?= $f['activa']
            ? '<span class="badge-activa"><i class="fa-solid fa-circle-check"></i> Activa</span>'
            : '<span class="badge-inactiva"><i class="fa-solid fa-circle-xmark"></i> Inactiva</span>' ?>
        <div style="margin-left:auto;display:flex;gap:5px;flex-wrap:wrap;">
            <a href="fichas_aplicaciones.php?id=<?= $f['id_ficha'] ?>" class="btn-xs bx-eye">
                <i class="fa-solid fa-eye"></i> Registros
            </a>
            <?php if ($puede_modificar): ?>
            <a href="fichas_form.php?id=<?= $f['id_ficha'] ?>" class="btn-xs bx-edit">
                <i class="fa-solid fa-pen"></i> Editar
            </a>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="id_ficha" value="<?= $f['id_ficha'] ?>">
                <input type="hidden" name="activa" value="<?= $f['activa'] ?>">
                <button type="submit" name="toggle_ficha" class="btn-xs"
                    style="background:#fef3c7;color:#92400e;border-color:#fde68a;">
                    <i class="fa-solid fa-power-off"></i> <?= $f['activa'] ? 'Desactivar' : 'Activar' ?>
                </button>
            </form>
            <?php endif; ?>
            <?php if ($puede_eliminar && $f['total_aplicaciones'] == 0): ?>
            <form method="POST" style="display:inline;"
                  onsubmit="return confirm('¿Eliminar esta ficha?')">
                <input type="hidden" name="id_ficha" value="<?= $f['id_ficha'] ?>">
                <button type="submit" name="eliminar_ficha" class="btn-xs bx-del">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</section>
</main>
</div>
</body>
</html>