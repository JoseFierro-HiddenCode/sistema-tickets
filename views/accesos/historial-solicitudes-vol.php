<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

protegerPagina(['usuario']);

if (!esJefe()) {
    header('Location: ../dashboard-usuario.php?error=' . urlencode('No autorizado'));
    exit();
}

// Filtros
$filtroEmpleado = $_GET['empleado'] ?? '';
$filtroEstado = $_GET['estado'] ?? '';
$filtroFechaDesde = $_GET['fecha_desde'] ?? '';
$filtroFechaHasta = $_GET['fecha_hasta'] ?? '';

// Construir consulta con filtros
$sql = "
    SELECT 
        h.id,
        h.folio,
        h.tipo_solicitud,
        h.fecha_generacion,
        h.estado,
        h.fecha_entrega,
        h.ruta_documento,
        s.nombre + ' ' + s.apellido as empleado_nombre,
        s.id_usuario,
        u.nombre + ' ' + u.apellido as generado_por_nombre
    FROM solicitudes_accesos_vol_historial h
    INNER JOIN solicitudes_accesos_vol s ON h.solicitud_id = s.id
    INNER JOIN users u ON h.generado_por = u.id
    WHERE h.generado_por = ?
";

$params = [$_SESSION['user_id']];

if (!empty($filtroEmpleado)) {
    $sql .= " AND (s.nombre LIKE ? OR s.apellido LIKE ?)";
    $params[] = "%$filtroEmpleado%";
    $params[] = "%$filtroEmpleado%";
}

if (!empty($filtroEstado)) {
    $sql .= " AND h.estado = ?";
    $params[] = $filtroEstado;
}

if (!empty($filtroFechaDesde)) {
    $sql .= " AND CAST(h.fecha_generacion AS DATE) >= ?";
    $params[] = $filtroFechaDesde;
}

if (!empty($filtroFechaHasta)) {
    $sql .= " AND CAST(h.fecha_generacion AS DATE) <= ?";
    $params[] = $filtroFechaHasta;
}

$sql .= " ORDER BY h.fecha_generacion DESC";

$solicitudes = obtenerTodos($sql, $params);

// Obtener empleados únicos para el filtro
$sqlEmpleados = "
    SELECT DISTINCT 
        u.id,
        u.nombre + ' ' + u.apellido as nombre_completo
    FROM users u
    INNER JOIN solicitudes_accesos_vol s ON u.id = s.empleado_id
    INNER JOIN solicitudes_accesos_vol_historial h ON s.id = h.solicitud_id
    WHERE h.generado_por = ?
    ORDER BY nombre_completo
";
$empleadosLista = obtenerTodos($sqlEmpleados, [$_SESSION['user_id']]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../img/LogoGris.png">
    <title>Historial Solicitudes VOL - Sistema de Tickets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../public/css/style.css">
</head>
<body>
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="../dashboard-usuario.php">
                <img src="../../img/LogoGris.png" alt="Logo ACP" style="height: 40px;" class="me-2">
                <span class="fw-semibold">Sistema de Tickets</span>
            </a>
            <div class="ms-auto">
                <a href="../dashboard-usuario.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-arrow-left"></i> Volver al Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4 mb-5">
        <div class="row">
            <div class="col-12">
                
                <!-- Header -->
                <div class="page-header mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="page-title"><i class="bi bi-clock-history"></i> Historial de Solicitudes VOL</h1>
                            <p class="page-subtitle">Gestiona y consulta tus solicitudes de accesos Volvo</p>
                        </div>
                        <a href="solicitud-accesos-vol.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Nueva Solicitud
                        </a>
                    </div>
                </div>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($_GET['error']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Filtros -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-funnel"></i> Filtros de Búsqueda</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Empleado</label>
                                    <input type="text" class="form-control" name="empleado" 
                                           value="<?= htmlspecialchars($filtroEmpleado) ?>" 
                                           placeholder="Nombre del empleado">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Estado</label>
                                    <select class="form-select" name="estado">
                                        <option value="">Todos</option>
                                        <option value="pendiente" <?= $filtroEstado == 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                        <option value="entregado" <?= $filtroEstado == 'entregado' ? 'selected' : '' ?>>Entregado</option>
                                        <option value="procesado" <?= $filtroEstado == 'procesado' ? 'selected' : '' ?>>Procesado</option>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">Desde</label>
                                    <input type="date" class="form-control" name="fecha_desde" 
                                           value="<?= htmlspecialchars($filtroFechaDesde) ?>">
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">Hasta</label>
                                    <input type="date" class="form-control" name="fecha_hasta" 
                                           value="<?= htmlspecialchars($filtroFechaHasta) ?>">
                                </div>
                                <div class="col-md-2 mb-3 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-search"></i> Buscar
                                    </button>
                                </div>
                            </div>
                            <?php if (!empty($filtroEmpleado) || !empty($filtroEstado) || !empty($filtroFechaDesde) || !empty($filtroFechaHasta)): ?>
                                <div class="text-end">
                                    <a href="historial-solicitudes-vol.php" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-x-circle"></i> Limpiar Filtros
                                    </a>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Tabla de Solicitudes -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-table"></i> Mis Solicitudes 
                            <span class="badge bg-secondary"><?= count($solicitudes) ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($solicitudes) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Folio</th>
                                            <th>Empleado</th>
                                            <th>ID Usuario</th>
                                            <th>Tipo</th>
                                            <th>Fecha</th>
                                            <th>Estado</th>
                                            <th class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($solicitudes as $sol): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($sol['folio']) ?></strong>
                                                </td>
                                                <td><?= e($sol['empleado_nombre']) ?></td>
                                                <td>
                                                    <?php if ($sol['id_usuario']): ?>
                                                        <code><?= htmlspecialchars($sol['id_usuario']) ?></code>
                                                    <?php else: ?>
                                                        <span class="text-muted">Sin asignar</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $badges = [
                                                        'crear' => '<span class="badge bg-primary">Crear</span>',
                                                        'solicitar' => '<span class="badge bg-info">Solicitar</span>',
                                                        'licencias' => '<span class="badge bg-warning text-dark">Licencias</span>',
                                                        'eliminar' => '<span class="badge bg-danger">Eliminar</span>'
                                                    ];
                                                    echo $badges[$sol['tipo_solicitud']] ?? htmlspecialchars($sol['tipo_solicitud']);
                                                    ?>
                                                </td>
                                                <td>
                                                    <small>
                                                    <?php 
                                                    if ($sol['fecha_generacion'] instanceof DateTime) {
                                                        echo $sol['fecha_generacion']->format('d/m/Y H:i');
                                                    } else {
                                                        echo date('d/m/Y H:i', strtotime($sol['fecha_generacion']));
                                                    }
                                                    ?>
                                                </small>
                                                </td>
                                                <td>
                                                    <?php if ($sol['estado'] == 'pendiente'): ?>
                                                        <span class="badge bg-warning text-dark">
                                                            <i class="bi bi-clock"></i> Pendiente
                                                        </span>
                                                    <?php elseif ($sol['estado'] == 'entregado'): ?>
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-check-circle"></i> Entregado
                                                        </span>
                                                        <br><small class="text-muted">
                                                        <?php 
                                                        if ($sol['fecha_entrega'] instanceof DateTime) {
                                                            echo $sol['fecha_entrega']->format('d/m/Y');
                                                        } else {
                                                            echo date('d/m/Y', strtotime($sol['fecha_entrega']));
                                                        }
                                                        ?>
                                                    </small>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary"><?= e($sol['estado']) ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <!-- Ver Detalle -->
                                                        <a href="ver-solicitud-vol.php?folio=<?= urlencode($sol['folio']) ?>" 
                                                           class="btn btn-outline-primary" 
                                                           title="Ver detalle">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        
                                                       <!-- Descargar -->
                                                    <?php 
                                                    $rutaPDF = "../../documents/solicitudes_vol/" . $sol['folio'] . ".pdf";
                                                    $rutaDOCX = "../../documents/solicitudes_vol/" . $sol['folio'] . ".docx";

                                                    if (file_exists($rutaPDF)) {
                                                        echo '<a href="' . $rutaPDF . '" 
                                                                class="btn btn-outline-success" 
                                                                download
                                                                title="Descargar PDF">
                                                                <i class="bi bi-file-pdf"></i>
                                                            </a>';
                                                    } else if (file_exists($rutaDOCX)) {
                                                        echo '<a href="' . $rutaDOCX . '" 
                                                                class="btn btn-outline-primary" 
                                                                download
                                                                title="Descargar Word">
                                                                <i class="bi bi-file-word"></i>
                                                            </a>';
                                                    } else {
                                                        echo '<button class="btn btn-outline-secondary" disabled title="No disponible">
                                                                <i class="bi bi-x-circle"></i>
                                                            </button>';
                                                    }
                                                    ?>
                                                        
                                                        <!-- Marcar como Entregado -->
                                                        <?php if ($sol['estado'] == 'pendiente'): ?>
                                                            <button onclick="marcarComoEntregado('<?= htmlspecialchars($sol['folio']) ?>')" 
                                                                    class="btn btn-outline-warning" 
                                                                    title="Marcar como entregado">
                                                                <i class="bi bi-check-circle"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-inbox" style="font-size: 80px; color: #ccc;"></i>
                                <h5 class="mt-3 text-muted">No se encontraron solicitudes</h5>
                                <p class="text-muted">
                                    <?php if (!empty($filtroEmpleado) || !empty($filtroEstado) || !empty($filtroFechaDesde) || !empty($filtroFechaHasta)): ?>
                                        Intenta ajustar los filtros de búsqueda
                                    <?php else: ?>
                                        Crea tu primera solicitud VOL haciendo clic en "Nueva Solicitud"
                                    <?php endif; ?>
                                </p>
                                <a href="solicitud-accesos-vol.php" class="btn btn-primary mt-2">
                                    <i class="bi bi-plus-circle"></i> Crear Nueva Solicitud
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function marcarComoEntregado(folio) {
            if (!confirm('¿Confirmas que entregaste este documento a TI?')) {
                return;
            }

            fetch('../../actions/accesos/actualizar-estado-solicitud.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'folio=' + encodeURIComponent(folio) + '&estado=entregado'
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Estado actualizado correctamente');
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(err => {
                console.error('Error:', err);
                alert('Error al actualizar el estado');
            });
        }
    </script>
</body>
</html>