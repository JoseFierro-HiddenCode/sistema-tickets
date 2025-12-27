<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

protegerPagina(['admin_tecnico', 'admin_global']);
protegerPorEquipo('soporte'); 


// EJECUTAR ARCHIVADO AUTOMÁTICO
ejecutarArchivoSiNecesario();

// Estadísticas generales (SOLO ACTIVOS, NO ARCHIVADOS)
$sqlStats = "
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN estado = 'abierto' THEN 1 ELSE 0 END) as abiertos,
        SUM(CASE WHEN estado = 'en_progreso' THEN 1 ELSE 0 END) as en_progreso,
        SUM(CASE WHEN estado = 'cerrado' THEN 1 ELSE 0 END) as cerrados,
        SUM(CASE WHEN tecnico_asignado_id IS NULL THEN 1 ELSE 0 END) as sin_asignar,
        SUM(CASE WHEN prioridad = 'urgente' AND estado != 'cerrado' THEN 1 ELSE 0 END) as urgentes
    FROM tickets
    WHERE archivado = 0
      AND equipo_asignado = 'soporte'
";
$stats = obtenerUno($sqlStats);

// Todos los tickets ACTIVOS (NO ARCHIVADOS)
$sqlTodosTickets = "
    SELECT 
        t.*,
        c.nombre as categoria_nombre,
        u.nombre + ' ' + u.apellido as usuario_nombre,
        tec.nombre + ' ' + tec.apellido as tecnico_nombre,
        s.nombre as sede_nombre,
        a.nombre as area_nombre
    FROM tickets t
    LEFT JOIN categories c ON t.categoria_id = c.id
    LEFT JOIN users u ON t.usuario_id = u.id
    LEFT JOIN users tec ON t.tecnico_asignado_id = tec.id
    LEFT JOIN sedes s ON u.sede_id = s.id
    LEFT JOIN areas a ON u.area_id = a.id
    WHERE t.archivado = 0
    AND t.equipo_asignado = 'soporte'
    ORDER BY 
        CASE t.estado 
            WHEN 'abierto' THEN 1 
            WHEN 'en_progreso' THEN 2 
            WHEN 'cerrado' THEN 3 
        END,
        CASE t.prioridad
            WHEN 'urgente' THEN 1
            WHEN 'alta' THEN 2
            WHEN 'media' THEN 3
            WHEN 'baja' THEN 4
        END,
        t.created_at DESC
";
$todosTickets = obtenerTodos($sqlTodosTickets);


// Obtener técnicos para asignación
$tecnicos = obtenerTecnicosPorEquipo('soporte');


// ============================================
// QUERY PARA KANBAN: TICKETS SIN ASIGNAR O ASIGNADOS A MÍ
// ============================================
$sqlTicketsSinAsignar = "
SELECT 
    t.*,
    c.nombre as categoria_nombre,
    u.nombre + ' ' + u.apellido as usuario_nombre,
    tec.nombre + ' ' + tec.apellido as tecnico_nombre
FROM tickets t
LEFT JOIN categories c ON t.categoria_id = c.id
LEFT JOIN users u ON t.usuario_id = u.id
LEFT JOIN users tec ON t.tecnico_asignado_id = tec.id
WHERE t.archivado = 0
AND t.equipo_asignado = 'soporte'
AND (t.tecnico_asignado_id IS NULL OR t.tecnico_asignado_id = ?)
ORDER BY 
    CASE t.prioridad 
        WHEN 'urgente' THEN 1
        WHEN 'alta' THEN 2
        WHEN 'media' THEN 3
        WHEN 'baja' THEN 4
    END,
    t.created_at DESC
";
$ticketsSinAsignar = obtenerTodos($sqlTicketsSinAsignar, [$_SESSION['user_id']]);
// Contar tickets por estado para el Kanban
$sinAsignarAbiertos = count(array_filter($ticketsSinAsignar, function($t) {
    return $t['estado'] == 'abierto';
}));
$sinAsignarProgreso = count(array_filter($ticketsSinAsignar, function($t) {
    return $t['estado'] == 'en_progreso';
}));
$sinAsignarCerrados = count(array_filter($ticketsSinAsignar, function($t) {
    return $t['estado'] == 'cerrado';
}));



// Tickets asignados AL ADMIN como técnico
$sqlMisTickets = "SELECT t.*, 
    c.nombre as categoria_nombre,
    u.nombre as usuario_nombre,
    ut.nombre as tecnico_nombre,
    s.nombre as sede_nombre,
    a.nombre as area_nombre
    FROM dbo.tickets t
    LEFT JOIN dbo.categories c ON t.categoria_id = c.id
    LEFT JOIN dbo.users u ON t.usuario_id = u.id
    LEFT JOIN dbo.users ut ON t.tecnico_asignado_id = ut.id
    LEFT JOIN dbo.sedes s ON u.sede_id = s.id
    LEFT JOIN dbo.areas a ON u.area_id = a.id
    WHERE t.tecnico_asignado_id = ?
    AND t.archivado = 0
    AND t.equipo_asignado = 'soporte'
    ORDER BY
        CASE t.prioridad 
            WHEN 'urgente' THEN 1 
            WHEN 'alta' THEN 2 
            WHEN 'media' THEN 3 
            WHEN 'baja' THEN 4 
        END,
        t.created_at DESC";

$misTicketsComoTecnico = obtenerTodos($sqlMisTickets, [$_SESSION['user_id']]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../img/LogoGris.png">
    <title>Dashboard Soporte - Sistema de Tickets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="../public/css/style.css?v=<?php echo time(); ?>">
   
</head>
<body>
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <img src="../img/LogoGris.png" alt="Logo ACP" style="height: 40px;" class="me-2">
                 <span class="fw-semibold">Sistema de Tickets</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
        
                    <!-- Theme Toggle -->
                    <li class="nav-item">
                        <button class="theme-toggle" id="theme-toggle" title="Cambiar tema">
                            <i class="bi bi-moon-stars-fill"></i>
                        </button>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i> <?php echo e($_SESSION['nombre'] . ' ' . $_SESSION['apellido']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar Sesión</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <div class="sidebar">
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="dashboard-admin-soporte.php" class="sidebar-nav-link active">
                    <i class="bi bi-grid-fill"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
            <a href="crear-ticket.php" class="sidebar-nav-link">
            <i class="bi bi-plus-circle-fill"></i>
             <span>Nuevo Ticket</span>
             </a>
            </li>
             <?php if (esUsuarioAutorizadoEspecial()): ?>
        <li class="sidebar-nav-item">
            <a href="inventario/solicitar-equipos.php" class="sidebar-nav-link">
                <i class="bi bi-pc-display-horizontal"></i>
                <span>Solicitar Equipos</span>
            </a>
        </li>
        <?php endif; ?>
        </ul>

        
        
        <div class="sidebar-section-title">Gestión</div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="#" class="sidebar-nav-link" onclick="filtrarTickets('todos'); return false;">
                    <i class="bi bi-list-ul"></i>
                    <span>Todos</span>
                    <span class="ms-auto badge bg-secondary"><?php echo $stats['total']; ?></span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="#" class="sidebar-nav-link" onclick="filtrarTickets('sin_asignar'); return false;">
                    <i class="bi bi-person-x"></i>
                    <span>Sin Asignar</span>
                    <span class="ms-auto badge bg-danger"><?php echo $stats['sin_asignar']; ?></span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="#" class="sidebar-nav-link" onclick="filtrarTickets('urgente'); return false;">
                    <i class="bi bi-exclamation-triangle"></i>
                    <span>Urgentes</span>
                    <span class="ms-auto badge bg-warning"><?php echo $stats['urgentes']; ?></span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="#" class="sidebar-nav-link" onclick="filtrarTickets('abierto'); return false;">
                    <i class="bi bi-circle"></i>
                    <span>Abiertos</span>
                    <span class="ms-auto badge bg-primary"><?php echo $stats['abiertos']; ?></span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="#" class="sidebar-nav-link" onclick="filtrarTickets('en_progreso'); return false;">
                    <i class="bi bi-arrow-repeat"></i>
                    <span>En Progreso</span>
                    <span class="ms-auto badge bg-info"><?php echo $stats['en_progreso']; ?></span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="#" class="sidebar-nav-link" onclick="filtrarTickets('cerrado'); return false;">
                    <i class="bi bi-check-circle"></i>
                    <span>Cerrados</span>
                    <span class="ms-auto badge bg-success"><?php echo $stats['cerrados']; ?></span>
                </a>
            </li>
        </ul>

        <!-- NEW SECTION INVENTARIO -->
<div class="sidebar-section-title">Inventario</div>
<ul class="sidebar-nav">
    <li class="sidebar-nav-item">
        <a href="inventario/dashboard-inventario.php" class="sidebar-nav-link">
            <i class="bi bi-box-seam"></i>
            <span>Dashboard</span>
        </a>
    </li>
    <li class="sidebar-nav-item">
        <a href="inventario/gestionar-equipos.php" class="sidebar-nav-link">
            <i class="bi bi-laptop"></i>
            <span>Gestionar Equipos</span>
        </a>
    </li>
    <li class="sidebar-nav-item">
        <a href="inventario/asignar-equipos-fisicos.php" class="sidebar-nav-link">
            <i class="bi bi-clipboard-check"></i>
            <span>Solicitudes Aprobadas</span>
            <?php 
            $solicitudesAprobadas = contarSolicitudesAprobadas();
            if ($solicitudesAprobadas > 0): 
            ?>
                <span class="ms-auto badge bg-warning"><?php echo $solicitudesAprobadas; ?></span>
            <?php endif; ?>
        </a>
    </li>
</ul>


<div class="sidebar-section-title">Préstamos de Equipos</div>
<ul class="sidebar-nav">
    <li class="sidebar-nav-item">
        <a href="inventario/prestamos-pendientes.php" class="sidebar-nav-link">
            <i class="bi bi-hourglass-split"></i>
            <span>Pendientes</span>
            <?php
            $sqlPrestamosPendientes = "SELECT COUNT(*) as total FROM prestamos WHERE estado = 'pendiente'";
            $prestamosPendientes = obtenerUno($sqlPrestamosPendientes);
            if ($prestamosPendientes && $prestamosPendientes['total'] > 0):
            ?>
                <span class="ms-auto badge bg-warning"><?php echo $prestamosPendientes['total']; ?></span>
            <?php endif; ?>
        </a>
    </li>
    <li class="sidebar-nav-item">
        <a href="inventario/prestamos-aprobados.php" class="sidebar-nav-link">
            <i class="bi bi-check-circle"></i>
            <span>Aprobados (Asignar)</span>
            <?php
            $sqlPrestamosAprobados = "SELECT COUNT(*) as total FROM prestamos WHERE estado = 'aprobado'";
            $prestamosAprobados = obtenerUno($sqlPrestamosAprobados);
            if ($prestamosAprobados && $prestamosAprobados['total'] > 0):
            ?>
                <span class="ms-auto badge bg-info"><?php echo $prestamosAprobados['total']; ?></span>
            <?php endif; ?>
        </a>
    </li>
    <li class="sidebar-nav-item">
        <a href="inventario/prestamos-activos.php" class="sidebar-nav-link">
            <i class="bi bi-box-seam"></i>
            <span>Activos</span>
        </a>
    </li>
</ul>
</ul>

<div class="sidebar-section-title">Toners / Tintas</div>
<ul class="sidebar-nav">
    <li class="sidebar-nav-item">
        <a href="toners/gestionar-impresoras.php" class="sidebar-nav-link">
            <i class="bi bi-printer"></i>
            <span>Gestionar Impresoras</span>
        </a>
    </li>
    <li class="sidebar-nav-item">
        <a href="toners/gestionar-stock.php" class="sidebar-nav-link">
            <i class="bi bi-boxes"></i>
            <span>Gestionar Stock</span>
        </a>
    </li>
    <li class="sidebar-nav-item">
        <a href="toners/revisar-solicitudes.php" class="sidebar-nav-link">
            <i class="bi bi-clipboard-check"></i>
            <span>Revisar Solicitudes</span>
            <?php
            $sqlTonersRevision = "SELECT COUNT(*) as total FROM solicitudes_toners WHERE estado = 'pendiente_revision'";
            $tonersRevision = obtenerUno($sqlTonersRevision);
            if ($tonersRevision && $tonersRevision['total'] > 0):
            ?>
                <span class="ms-auto badge bg-info"><?php echo $tonersRevision['total']; ?></span>
            <?php endif; ?>
        </a>
    </li>
    <li class="sidebar-nav-item">
        <a href="toners/entregar-solicitudes.php" class="sidebar-nav-link">
            <i class="bi bi-box-seam"></i>
            <span>Comprar y Entregar</span>
            <?php
            $sqlTonersEntrega = "SELECT COUNT(*) as total FROM solicitudes_toners WHERE estado IN ('pendiente_entrega', 'aprobada')";
            $tonersEntrega = obtenerUno($sqlTonersEntrega);
            if ($tonersEntrega && $tonersEntrega['total'] > 0):
            ?>
                <span class="ms-auto badge bg-success"><?php echo $tonersEntrega['total']; ?></span>
            <?php endif; ?>
        </a>
    </li>
</ul>

<div class="sidebar-section-title">Administración</div>
<ul class="sidebar-nav">
    <li class="sidebar-nav-item">
        <a href="usuarios/gestionar-usuarios.php" class="sidebar-nav-link">
            <i class="bi bi-people-fill"></i>
            <span>Gestionar Usuarios</span>
        </a>
    </li>
</ul>

<!-- SI EL USUARIO ES JEFE, AGREGAR ESTA SECCIÓN -->
<?php if (esJefe()): ?>
<div class="sidebar-section-title">Gestión de Equipo</div>
<ul class="sidebar-nav">
    <li class="sidebar-nav-item">
        <a href="inventario/solicitar-equipos.php" class="sidebar-nav-link">
            <i class="bi bi-plus-square"></i>
            <span>Solicitar Equipos</span>
        </a>
    </li>
    <li class="sidebar-nav-item">
        <a href="inventario/mis-solicitudes.php" class="sidebar-nav-link">
            <i class="bi bi-list-check"></i>
            <span>Mis Solicitudes</span>
        </a>
    </li>
</ul>
<?php endif; ?>

        <div class="sidebar-section-title">Archivo</div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="tickets-archivados.php" class="sidebar-nav-link">
                    <i class="bi bi-archive"></i>
                    <span>Tickets Archivados</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">🛠️ Dashboard Soporte Técnico</h1>
            <p class="page-subtitle">Gestiona tickets de soporte técnico y asigna técnicos del equipo</p>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-lg-2 col-md-4 mb-3">
                <div class="stat-card">
                    <div class="text-center">
                        <div class="stat-icon mx-auto mb-2" style="background-color: #DEEBFF;">
                            <i class="bi bi-ticket-perforated" style="color: #0052CC;"></i>
                        </div>
                        <h6>Total</h6>
                        <h3><?php echo $stats['total']; ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-lg-2 col-md-4 mb-3">
                <div class="stat-card">
                    <div class="text-center">
                        <div class="stat-icon mx-auto mb-2" style="background-color: #FFEBE6;">
                            <i class="bi bi-person-x" style="color: #DE350B;"></i>
                        </div>
                        <h6>Sin Asignar</h6>
                        <h3><?php echo $stats['sin_asignar']; ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-lg-2 col-md-4 mb-3">
                <div class="stat-card">
                    <div class="text-center">
                        <div class="stat-icon mx-auto mb-2" style="background-color: #E3FCEF;">
                            <i class="bi bi-circle" style="color: #006644;"></i>
                        </div>
                        <h6>Abiertos</h6>
                        <h3><?php echo $stats['abiertos']; ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-lg-2 col-md-4 mb-3">
                <div class="stat-card">
                    <div class="text-center">
                        <div class="stat-icon mx-auto mb-2" style="background-color: #FFF0B3;">
                            <i class="bi bi-arrow-repeat" style="color: #974F0C;"></i>
                        </div>
                        <h6>En Progreso</h6>
                        <h3><?php echo $stats['en_progreso']; ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-lg-2 col-md-4 mb-3">
                <div class="stat-card">
                    <div class="text-center">
                        <div class="stat-icon mx-auto mb-2" style="background-color: #E8F5E9;">
                            <i class="bi bi-check-circle" style="color: #36B37E;"></i>
                        </div>
                        <h6>Cerrados</h6>
                        <h3><?php echo $stats['cerrados']; ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-lg-2 col-md-4 mb-3">
                <div class="stat-card">
                    <div class="text-center">
                        <div class="stat-icon mx-auto mb-2" style="background-color: #FFF0B3;">
                            <i class="bi bi-exclamation-triangle" style="color: #974F0C;"></i>
                        </div>
                        <h6>Urgentes</h6>
                        <h3><?php echo $stats['urgentes']; ?></h3>
                    </div>
                </div>
            </div>
        </div>


<!-- Kanban Board -->
<div class="kanban-board mb-4">
    <!-- COLUMNA ABIERTO -->
    <div class="kanban-column" data-status="abierto">
        <div class="kanban-header" style="background-color: #DEEBFF;">
            <h5 style="color: #0052CC;">
                <i class="bi bi-circle"></i> Abiertos
                <span class="kanban-count badge bg-primary ms-2"><?php echo $sinAsignarAbiertos; ?></span>
            </h5>
        </div>
        
        <?php foreach ($ticketsSinAsignar as $ticket): ?>
            <?php if ($ticket['estado'] == 'abierto'): ?>
                <div class="kanban-card" data-ticket-id="<?php echo $ticket['id']; ?>" draggable="true">
                    
                    <div class="kanban-card-header">
                        <strong style="color: var(--jira-blue);">#<?php echo $ticket['id']; ?></strong>
                        <small style="color: var(--text-muted);">
                            <?php echo formatearFechaCorta($ticket['created_at']); ?>
                        </small>
                    </div>
                    
                    <div class="kanban-card-title">
                        <?php echo e($ticket['titulo']); ?>
                    </div>
                    
                    <div class="kanban-card-footer">
                        <?php echo badgePrioridad($ticket['prioridad']); ?>
                        <div class="avatar-initials">
                            <?php echo obtenerIniciales($ticket['usuario_nombre']); ?>
                        </div>
                    </div>
                    
                    <?php if ($ticket['tecnico_asignado_id']): ?>
                        <div class="mt-2">
                            <span class="badge bg-success w-100">
                                <i class="bi bi-person-check"></i> Asignado a mí
                            </span>
                        </div>
                    <?php else: ?>
                        <div class="mt-2">
                            <button class="btn btn-sm btn-primary w-100" 
                                    onclick="event.stopPropagation(); abrirModalAsignarTecnico(<?php echo $ticket['id']; ?>)">
                                <i class="bi bi-person-plus"></i> Asignar Técnico
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- COLUMNA EN PROGRESO -->
    <div class="kanban-column" data-status="en_progreso">
        <div class="kanban-header" style="background-color: #FFF0B3;">
            <h5 style="color: #974F0C;">
                <i class="bi bi-arrow-repeat"></i> En Progreso
                <span class="kanban-count badge bg-warning ms-2"><?php echo $sinAsignarProgreso; ?></span>
            </h5>
        </div>
        
        <?php foreach ($ticketsSinAsignar as $ticket): ?>
            <?php if ($ticket['estado'] == 'en_progreso'): ?>
                <div class="kanban-card" data-ticket-id="<?php echo $ticket['id']; ?>" draggable="true">
                    
                    <div class="kanban-card-header">
                        <strong style="color: var(--jira-blue);">#<?php echo $ticket['id']; ?></strong>
                        <small style="color: var(--text-muted);">
                            <?php echo formatearFechaCorta($ticket['created_at']); ?>
                        </small>
                    </div>
                    
                    <div class="kanban-card-title">
                        <?php echo e($ticket['titulo']); ?>
                    </div>
                    
                    <div class="kanban-card-footer">
                        <?php echo badgePrioridad($ticket['prioridad']); ?>
                        <div class="avatar-initials">
                            <?php echo obtenerIniciales($ticket['usuario_nombre']); ?>
                        </div>
                    </div>
                    
                    <?php if ($ticket['tecnico_asignado_id']): ?>
                        <div class="mt-2">
                            <span class="badge bg-success w-100">
                                <i class="bi bi-person-check"></i> Asignado a mí
                            </span>
                        </div>
                    <?php else: ?>
                        <div class="mt-2">
                            <button class="btn btn-sm btn-primary w-100" 
                                    onclick="event.stopPropagation(); abrirModalAsignarTecnico(<?php echo $ticket['id']; ?>)">
                                <i class="bi bi-person-plus"></i> Asignar Técnico
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- COLUMNA CERRADO -->
    <div class="kanban-column" data-status="cerrado">
        <div class="kanban-header" style="background-color: #E3FCEF;">
            <h5 style="color: #006644;">
                <i class="bi bi-check-circle"></i> Cerrados
                <span class="kanban-count badge bg-success ms-2"><?php echo $sinAsignarCerrados; ?></span>
            </h5>
        </div>
        
        <?php foreach ($ticketsSinAsignar as $ticket): ?>
            <?php if ($ticket['estado'] == 'cerrado'): ?>
                <div class="kanban-card" data-ticket-id="<?php echo $ticket['id']; ?>" draggable="true">
                    
                    <div class="kanban-card-header">
                        <strong style="color: var(--jira-blue);">#<?php echo $ticket['id']; ?></strong>
                        <small style="color: var(--text-muted);">
                            <?php echo formatearFechaCorta($ticket['created_at']); ?>
                        </small>
                    </div>
                    
                    <div class="kanban-card-title">
                        <?php echo e($ticket['titulo']); ?>
                    </div>
                    
                    <div class="kanban-card-footer">
                        <?php echo badgePrioridad($ticket['prioridad']); ?>
                        <div class="avatar-initials">
                            <?php echo obtenerIniciales($ticket['usuario_nombre']); ?>
                        </div>
                    </div>
                    
                    <?php if ($ticket['tecnico_asignado_id']): ?>
                        <div class="mt-2">
                            <span class="badge bg-success w-100">
                                <i class="bi bi-person-check"></i> Asignado a mí
                            </span>
                        </div>
                    <?php else: ?>
                        <div class="mt-2">
                            <button class="btn btn-sm btn-primary w-100" 
                                    onclick="event.stopPropagation(); abrirModalAsignarTecnico(<?php echo $ticket['id']; ?>)">
                                <i class="bi bi-person-plus"></i> Asignar Técnico
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>
        
       <!-- Tickets Table -->
<div class="card">
    <div class="card-header">
        <h5><i class="bi bi-table"></i> Todos los Tickets</h5>
    </div>
    <div class="card-body" style="padding: 20px; overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <thead>
                <tr>
                    <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 80px;">ID</th>
                    <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: left;">TÍTULO</th>
                    <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: left; width: 150px;">USUARIO</th>
                    <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: left; width: 150px;">SEDE / ÁREA</th>
                    <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: left; width: 150px;">CATEGORÍA</th>
                    <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 120px;">PRIORIDAD</th>
                    <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 120px;">ESTADO</th>
                    <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: left; width: 220px;">TÉCNICO</th>
                    <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: left; width: 140px;">FECHA</th>
                    <th style="background-color: var(--bg-tertiary); color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border: none; border-bottom: 2px solid var(--border-color); text-align: center; width: 140px;">ACCIONES</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($todosTickets as $ticket): ?>
                    <tr class="priority-<?php echo $ticket['prioridad']; ?> ticket-row" 
                        data-estado="<?php echo $ticket['estado']; ?>"
                        data-asignado="<?php echo $ticket['tecnico_asignado_id'] ? 'si' : 'no'; ?>"
                        data-prioridad="<?php echo $ticket['prioridad']; ?>"
                        style="transition: background-color 0.2s ease;"
                        onmouseover="this.style.backgroundColor='var(--bg-tertiary)'"
                        onmouseout="this.style.backgroundColor='transparent'">
                        
                        <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                            <strong style="color: var(--jira-blue); font-weight: 700;">#<?php echo $ticket['id']; ?></strong>
                        </td>
                        
                        <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); vertical-align: middle;">
                            <a href="ver-ticket.php?id=<?php echo $ticket['id']; ?>" style="color: var(--text-primary); font-weight: 600; text-decoration: none;">
                                <?php echo e(substr($ticket['titulo'], 0, 40)) . (strlen($ticket['titulo']) > 40 ? '...' : ''); ?>
                            </a>
                        </td>
                        
                        <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); color: var(--text-secondary); vertical-align: middle;">
                            <?php echo e($ticket['usuario_nombre']); ?>
                        </td>
                        
                        <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); vertical-align: middle;">
                            <small style="color: var(--text-secondary);">
                                <?php echo e($ticket['sede_nombre']); ?><br>
                                <em style="color: var(--text-muted);"><?php echo e($ticket['area_nombre']); ?></em>
                            </small>
                        </td>
                        
                        <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); color: var(--text-secondary); vertical-align: middle;">
                            <?php echo $ticket['categoria_otro'] ? e($ticket['categoria_otro']) : e($ticket['categoria_nombre']); ?>
                        </td>
                        
                        <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                            <?php echo badgePrioridad($ticket['prioridad']); ?>
                        </td>
                        
                        <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                            <?php echo badgeEstado($ticket['estado']); ?>
                        </td>
                        
                        <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); vertical-align: middle;">
                            <?php if ($ticket['tecnico_asignado_id']): ?>
                                <div style="display: flex; align-items: center; gap: 8px; justify-content: space-between;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <div class="avatar-initials" style="width: 26px; height: 26px; font-size: 11px;">
                                            <?php echo obtenerIniciales($ticket['tecnico_nombre'], ''); ?>
                                        </div>
                                        <small style="font-weight: 500; color: var(--text-primary);">
                                            <?php echo e(substr($ticket['tecnico_nombre'], 0, 15)); ?>
                                        </small>
                                    </div>
                                    <button class="btn btn-sm btn-link p-0 text-muted" 
                                            onclick="abrirModalAsignar(<?php echo $ticket['id']; ?>, '<?php echo e($ticket['tecnico_nombre']); ?>', <?php echo $ticket['tecnico_asignado_id']; ?>)"
                                            title="Cambiar técnico">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                </div>
                            <?php else: ?>
                                <button class="btn btn-sm btn-outline-danger w-100" 
                                        onclick="abrirModalAsignar(<?php echo $ticket['id']; ?>, '', null)">
                                    <i class="bi bi-person-plus"></i> Asignar
                                </button>
                            <?php endif; ?>
                        </td>
                        
                        <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); vertical-align: middle;">
                            <small style="color: var(--text-muted);"><?php echo formatearFecha($ticket['created_at']); ?></small>
                        </td>
                        
                        <td style="padding: 16px; border: none; border-bottom: 1px solid var(--border-color); text-align: center; vertical-align: middle;">
                            <div style="display: flex; gap: 4px; justify-content: center;">
                                <a href="ver-ticket.php?id=<?php echo $ticket['id']; ?>" 
                                   class="btn btn-sm btn-primary"
                                   title="Ver detalles del ticket">
                                    <i class="bi bi-eye"></i>
                                </a>

                                <?php 
    $equipoActual = $ticket['equipo_asignado']; 
    $equipoDestino = ($equipoActual === 'soporte') ? 'desarrollo' : 'soporte';
    $icono = ($equipoDestino === 'soporte') ? 'headset' : 'code-square';
?>
<button class="btn btn-sm btn-warning" 
        title="Redirigir a <?php echo ucfirst($equipoDestino); ?>"
        onclick="redirigirTicket(<?php echo $ticket['id']; ?>, '<?php echo $equipoDestino; ?>')">
    <i class="bi bi-<?php echo $icono; ?>"></i>
</button>
                                
                                <?php if ($ticket['estado'] == 'cerrado'): ?>
                                    <button class="btn btn-sm btn-warning" 
                                            onclick="archivarTicketDesdeTabla(<?php echo $ticket['id']; ?>)"
                                            title="Archivar ticket">
                                        <i class="bi bi-archive"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
   <!-- Modal Asignar Técnico -->
<div class="modal fade" id="modalAsignarTecnico" tabindex="-1" aria-labelledby="modalAsignarTecnicoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formAsignarTecnico" method="POST">
                <input type="hidden" name="ticket_id" id="ticketIdAsignar">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAsignarTecnicoLabel">
                        <i class="bi bi-person-plus"></i> Asignar Técnico
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="tecnicoSelect" class="form-label">Seleccionar Técnico</label>
                        <select class="form-select" id="tecnicoSelect" name="tecnico_id" required>
                            <option value="">-- Seleccionar técnico --</option>
                            <?php foreach ($tecnicos as $tec): ?>
                                <option value="<?php echo $tec['id']; ?>">
                                    <?php echo e($tec['nombre_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Deja en blanco para desasignar</small>
                    </div>
                    
                    <div id="tecnicoActualInfo" class="alert alert-info" style="display: none;">
                        <i class="bi bi-info-circle"></i>
                        Técnico actual: <strong id="tecnicoActualNombre"></strong>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" onclick="asignarmeAMi()">
                        <i class="bi bi-person-check"></i> Asignarme a Mí
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Asignar Técnico
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
  
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    <script src="../public/js/app.js"></script>
    
<script>
// ====================================================
// ABRIR MODAL DE ASIGNACIÓN
// ====================================================
function abrirModalAsignar(ticketId, tecnicoNombre, tecnicoId) {
    console.log('Abriendo modal para ticket:', ticketId);
    
    // Guardar ID del ticket
    document.getElementById('ticketIdAsignar').value = ticketId;
    
    // Limpiar selección anterior
    document.getElementById('tecnicoSelect').value = '';
    
    // Mostrar técnico actual si existe
    const infoActual = document.getElementById('tecnicoActualInfo');
    const nombreActual = document.getElementById('tecnicoActualNombre');
    
    if (tecnicoId && tecnicoNombre) {
        nombreActual.textContent = tecnicoNombre;
        infoActual.style.display = 'block';
        document.getElementById('tecnicoSelect').value = tecnicoId;
    } else {
        infoActual.style.display = 'none';
    }
    
    // Abrir modal
    const modal = new bootstrap.Modal(document.getElementById('modalAsignarTecnico'));
    modal.show();
}

// ====================================================
// MANEJAR SUBMIT DEL FORMULARIO DE ASIGNACIÓN
// ====================================================
document.getElementById('formAsignarTecnico').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const ticketId = document.getElementById('ticketIdAsignar').value;
    const tecnicoId = document.getElementById('tecnicoSelect').value;
    
    console.log('Enviando asignación:', { ticketId, tecnicoId });
    
    if (!ticketId) {
        alert('Error: No se pudo obtener el ID del ticket');
        return;
    }
    
    // Deshabilitar botón para evitar doble click
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Asignando...';
    
    const formData = new FormData();
    formData.append('ticket_id', ticketId);
    formData.append('tecnico_id', tecnicoId);
    
    fetch('../actions/asignar-tecnico.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Respuesta recibida:', response);
        return response.text(); // Primero como texto para ver qué llega
    })
    .then(text => {
        console.log('Texto de respuesta:', text);
        
        try {
            const data = JSON.parse(text);
            console.log('JSON parseado:', data);
            
            if (data.success) {
                // Cerrar modal INMEDIATAMENTE
                const modal = bootstrap.Modal.getInstance(document.getElementById('modalAsignarTecnico'));
                if (modal) modal.hide();
                
                // Mostrar notificación
                if (typeof mostrarNotificacion === 'function') {
                    mostrarNotificacion(data.message, 'success');
                } else {
                    alert(data.message);
                }
                
                // Recargar después de 1 segundo
                setTimeout(() => location.reload(), 1000);
            } else {
                // Re-habilitar botón
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-check-circle"></i> Asignar Técnico';
                
                if (typeof mostrarNotificacion === 'function') {
                    mostrarNotificacion(data.message || 'Error al asignar técnico', 'error');
                } else {
                    alert(data.message || 'Error al asignar técnico');
                }
            }
        } catch (e) {
            console.error('Error al parsear JSON:', e);
            console.error('Respuesta del servidor:', text);
            
            // Re-habilitar botón
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-check-circle"></i> Asignar Técnico';
            
            alert('Error: La respuesta del servidor no es válida. Revisa la consola.');
        }
    })
    .catch(error => {
        console.error('Error en fetch:', error);
        
        // Re-habilitar botón
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-check-circle"></i> Asignar Técnico';
        
        if (typeof mostrarNotificacion === 'function') {
            mostrarNotificacion('Error de conexión', 'error');
        } else {
            alert('Error de conexión');
        }
    });
});

// ====================================================
// AUTO-ASIGNACIÓN
// ====================================================
function asignarmeAMi() {
    const ticketId = document.getElementById('ticketIdAsignar').value;
    
    console.log('Auto-asignando ticket:', ticketId);
    
    if (!ticketId) {
        alert('Error: No se pudo obtener el ID del ticket');
        return;
    }

    if (!confirm('¿Deseas asignarte este ticket a ti mismo?')) {
        return;
    }
    
    // Deshabilitar botón
    const btn = event.target;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Asignando...';

    const formData = new FormData();
    formData.append('ticket_id', ticketId);
    formData.append('auto_asignar', 'true');

    fetch('../actions/asignar-tecnico.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        console.log('Respuesta auto-asignación:', text);
        
        try {
            const data = JSON.parse(text);
            
            if (data.success) {
                // Cerrar modal INMEDIATAMENTE
                const modal = bootstrap.Modal.getInstance(document.getElementById('modalAsignarTecnico'));
                if (modal) modal.hide();
                
                if (typeof mostrarNotificacion === 'function') {
                    mostrarNotificacion(data.message, 'success');
                } else {
                    alert(data.message);
                }
                
                setTimeout(() => location.reload(), 1000);
            } else {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-person-check"></i> Asignarme a Mí';
                
                if (typeof mostrarNotificacion === 'function') {
                    mostrarNotificacion(data.message || 'Error al asignar ticket', 'error');
                } else {
                    alert(data.message || 'Error al asignar ticket');
                }
            }
        } catch (e) {
            console.error('Error al parsear JSON:', e);
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-person-check"></i> Asignarme a Mí';
            alert('Error: Respuesta del servidor inválida');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-person-check"></i> Asignarme a Mí';
        
        if (typeof mostrarNotificacion === 'function') {
            mostrarNotificacion('Error de conexión', 'error');
        } else {
            alert('Error de conexión');
        }
    });
}

function redirigirTicket(ticketId, equipoDestino) {
    if (!confirm(`¿Estás seguro de redirigir el ticket #${ticketId} a ${equipoDestino}?`)) {
        return;
    }
    const formData = new FormData();
    formData.append('ticket_id', ticketId);
    formData.append('equipo_destino', equipoDestino);
    fetch('redirigir_ticket.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error en la solicitud');
    });

   
}
</script>
</body>
</html>