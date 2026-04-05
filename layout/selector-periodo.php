<?php
// layout/selector-periodo.php
// Requiere que ya se haya ejecutado layout/bootstrap.php
// Variables esperadas: $pdo, $periodoSeleccionado

require_once __DIR__ . "/../helpers/periodo.php";

$todos = get_all_periodos($pdo);
$actualId = $periodoSeleccionado ? (int)$periodoSeleccionado['id_periodo'] : 0;
?>

<style>
.periodo-mini {
  display:flex;
  align-items:center;
  gap:10px;
  padding:6px 10px;
  border:1px solid #e5e7eb;
  border-radius:10px;
  background:#fff;
}
.periodo-mini i { color:#1f3a5f; }
.periodo-mini label{
  font-weight:700;
  color:#1f3a5f;
  font-size:13px;
  white-space:nowrap;
}
.periodo-mini select{
  border:none;
  outline:none;
  background:transparent;
  font-size:13px;
  color:#111827;
  padding:4px 6px;
  min-width:260px;
}
.periodo-badge{
  font-size:11px;
  font-weight:800;
  padding:3px 8px;
  border-radius:999px;
  color:#fff;
}
.periodo-badge.abierto{ background:#10b981; }
.periodo-badge.cerrado{ background:#6b7280; }
</style>

<div class="periodo-mini" title="Cambiar período">
  <i class="fa-solid fa-calendar-days"></i>
  <label>Período</label>

  <select id="periodoSelect">
    <option value="0" disabled <?= $actualId===0 ? 'selected' : '' ?>>Seleccione...</option>

    <?php foreach ($todos as $p): ?>
      <?php $pid = (int)$p['id_periodo']; ?>
      <option value="<?= $pid ?>" <?= $pid===$actualId ? 'selected' : '' ?>>
        <?= htmlspecialchars($p['nombre']) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <?php if ($periodoSeleccionado): ?>
    <span class="periodo-badge <?= strtolower($periodoSeleccionado['estado']) ?>">
      <?= htmlspecialchars($periodoSeleccionado['estado']) ?>
    </span>
  <?php endif; ?>
</div>

<script>
document.getElementById('periodoSelect')?.addEventListener('change', async (e) => {
  const idPeriodo = e.target.value;

  try {
    const fd = new FormData();
    fd.append('id_periodo', idPeriodo);

    const res = await fetch('periodo_set.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (!data.success) {
      window.mostrarMensaje?.(data.message || 'No se pudo cambiar el período', 'error');
      return;
    }

    window.mostrarMensaje?.(`Período: ${data.periodo.nombre}`, 'success');
    setTimeout(() => window.location.reload(), 150);
  } catch (err) {
    window.mostrarMensaje?.('Error al cambiar período', 'error');
  }
});
</script>
