<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

protegerPagina(['usuario']);

if (!esJefe()) {
    header('Location: ../dashboard-usuario.php?error=' . urlencode('No autorizado'));
    exit();
}

$folio = $_GET['folio'] ?? '';

if (empty($folio)) {
    header('Location: historial-solicitudes-vol.php?error=' . urlencode('Folio no válido'));
    exit();
}

// Obtener datos de la solicitud
$sql = "
    SELECT 
        h.*,
        s.nombre as empleado_nombre,
        s.apellido as empleado_apellido,
        s.id_usuario,
        u.nombre + ' ' + u.apellido as generado_por_nombre
    FROM solicitudes_accesos_vol_historial h
    INNER JOIN solicitudes_accesos_vol s ON h.solicitud_id = s.id
    INNER JOIN users u ON h.generado_por = u.id
    WHERE h.folio = ? AND h.generado_por = ?
";

$solicitud = obtenerUno($sql, [$folio, $_SESSION['user_id']]);

if (!$solicitud) {
    header('Location: historial-solicitudes-vol.php?error=' . urlencode('Solicitud no encontrada'));
    exit();
}

// Obtener accesos incluidos
$idsAccesos = json_decode($solicitud['accesos_incluidos'], true);
$accesosDetalle = [];

if (!empty($idsAccesos)) {
    $placeholders = implode(',', array_fill(0, count($idsAccesos), '?'));
    $sqlAccesos = "SELECT acceso FROM solicitudes_accesos_vol_detalle WHERE id IN ($placeholders)";
    $accesosDetalle = obtenerTodos($sqlAccesos, $idsAccesos);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../img/LogoGris.png">
    <title>Solicitud VOL Generada - Sistema de Tickets</title>
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
        </div>
    </nav>

    <div class="container mt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <strong>¡Solicitud generada exitosamente!</strong>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card shadow-lg">
                    <div class="card-body text-center p-5">
                        
                        <!-- Icono -->
                        <div class="mb-4">
                            <i class="bi bi-file-earmark-word" style="font-size: 120px; color: #2B579A;"></i>
                        </div>

                        <!-- Título -->
                        <h2 class="mb-3">Solicitud VOL Generada</h2>
                        
                        <!-- Folio -->
                        <div class="alert alert-info d-inline-block">
                            <h4 class="mb-0">
                                <i class="bi bi-tag"></i> Folio: <strong><?= htmlspecialchars($folio) ?></strong>
                            </h4>
                        </div>

                        <!-- Detalles -->
                        <div class="mt-4 text-start">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="fw-bold" style="width: 40%;">Empleado:</td>
                                    <td><?= e($solicitud['empleado_nombre'] . ' ' . $solicitud['empleado_apellido']) ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">ID Usuario:</td>
                                    <td><?= e($solicitud['id_usuario'] ?: 'Se asignará después') ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Tipo de Solicitud:</td>
                                    <td>
                                        <?php
                                        $tipos = [
                                            'crear' => 'Crear Usuario',
                                            'solicitar' => 'Solicitar Accesos',
                                            'licencias' => 'Solicitar Licencias',
                                            'eliminar' => 'Eliminar Usuario'
                                        ];
                                        echo $tipos[$solicitud['tipo_solicitud']] ?? $solicitud['tipo_solicitud'];
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Fecha de Generación:</td>
                                    <td>
                                    <?php 
                                    if ($solicitud['fecha_generacion'] instanceof DateTime) {
                                        echo $solicitud['fecha_generacion']->format('d/m/Y H:i');
                                    } else {
                                        echo date('d/m/Y H:i', strtotime($solicitud['fecha_generacion']));
                                    }
                                    ?>
                                </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Generado por:</td>
                                    <td><?= e($solicitud['generado_por_nombre']) ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Estado:</td>
                                    <td>
                                        <?php if ($solicitud['estado'] == 'pendiente'): ?>
                                            <span class="badge bg-warning text-dark">
                                                <i class="bi bi-clock"></i> Pendiente de Entrega
                                            </span>
                                        <?php elseif ($solicitud['estado'] == 'entregado'): ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-circle"></i> Entregado a TI
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><?= e($solicitud['estado']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Accesos Incluidos -->
                        <?php if (!empty($accesosDetalle)): ?>
                            <div class="mt-4 text-start">
                                <h6 class="fw-bold">Accesos Incluidos en esta Solicitud:</h6>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($accesosDetalle as $acceso): ?>
                                        <li class="list-group-item">
                                            <i class="bi bi-check-circle text-success"></i> 
                                            <?= formatearNombreAcceso($acceso['acceso']) ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <hr class="my-4">
<!-- Botones de Acción -->
<div class="d-grid gap-3">
    <!-- Descargar con script PHP dedicado -->
    <?php 
    $nombreArchivo = $solicitud['folio'] . ".docx";
    $rutaCompleta = __DIR__ . "/../../documents/solicitudes_vol/" . $nombreArchivo;
    
    if (file_exists($rutaCompleta)) {
        // Usar script de descarga dedicado
        $rutaDescarga = "../../actions/accesos/descargar-solicitud-vol.php?folio=" . urlencode($solicitud['folio']);
        $textoBoton = "Descargar Documento Word";
        $iconoBoton = "bi-file-word";
        $colorBtn = "btn-primary";
    } else {
        $rutaDescarga = "#";
        $textoBoton = "Archivo no disponible";
        $iconoBoton = "bi-x-circle";
        $colorBtn = "btn-secondary disabled";
    }
    ?>
    
    <a href="<?= $rutaDescarga ?>" 
       class="btn <?= $colorBtn ?> btn-lg">
        <i class="bi bi-download <?= $iconoBoton ?>"></i> <?= $textoBoton ?>
    </a>
 
                            <!-- Marcar como Entregado -->
                            <?php if ($solicitud['estado'] == 'pendiente'): ?>
                                <button onclick="marcarComoEntregado('<?= htmlspecialchars($folio) ?>')" 
                                        class="btn btn-success btn-lg">
                                    <i class="bi bi-check-circle"></i> Marcar como Entregado a TI
                                </button>
                            <?php endif; ?>

                            <!-- Volver -->
                            <a href="historial-solicitudes-vol.php" class="btn btn-outline-secondary btn-lg">
                                <i class="bi bi-list"></i> Ver Historial de Solicitudes
                            </a>

                            <a href="solicitud-accesos-vol.php" class="btn btn-outline-primary">
                                <i class="bi bi-plus-circle"></i> Crear Nueva Solicitud
                            </a>
                        </div>

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

<?php
function formatearNombreAcceso($acceso) {
    $nombres = [
        'trucks_portal_volvo' => 'Trucks Portal Volvo',
        'argus_dealer' => 'Argus Dealer',
        'dynafleet' => 'Dynafleet',
        'impact_vt' => 'Impact',
        'parts_online' => 'Parts Online',
        'product_history' => 'Product History Viewer',
        'technical_service' => 'Technical Service Bulletin',
        'truck_campaign' => 'Truck Campaign Information',
        'trucks_portal_ud' => 'Trucks Portal UD',
        'ud_product_history' => 'UD Product History Viewer',
        'vosp' => 'VOSP',
        'wiring_diagrams' => 'Wiring Diagrams',
        'mack_trucks_dealer' => 'Mack Trucks Dealer Portal',
        'mack_electronic_info' => 'Mack Electronic Info System',
        'mack_impact' => 'Mack Impact',
        'mack_product_history' => 'Mack Product History Viewer',
        'vdn' => 'VDN',
        'caretrack' => 'CareTrack',
        'chain' => 'CHAIN',
        'prosis_pro' => 'Prosis Pro',
        'vlc' => 'VLC',
        'tech_tool_matris' => 'Tech Tool 2 / MATRIS 2',
        'tt_accesos' => 'Tech Tool 2 - Accesos',
        'tt_licencia' => 'Tech Tool 2 - Licencia',
        'vppn' => 'VPPN',
        'epc_offline' => 'EPC Offline',
        'vodia5' => 'VODIA 5',
        'vodia_acceso' => 'VODIA 5 - Acceso',
        'vodia_licencia' => 'VODIA 5 - Licencia',
        'vtt' => 'VTT',
        'vtt_nueva' => 'VTT - Nueva',
        'vtt_renovacion' => 'VTT - Renovación',
        'vtt_volvo_trucks' => 'VTT - Volvo Trucks',
        'vtt_volvo_buses' => 'VTT - Volvo Buses',
        'vtt_mack_trucks' => 'VTT - Mack Trucks',
        'vtt_ud_trucks' => 'VTT - UD Trucks',
        'lds' => 'LDS',
        'gds' => 'GDS',
        'time_recording' => 'Time Recording',
        'uchp' => 'UCHP',
        'uchp_vtc' => 'UCHP - VTC',
        'uchp_vbc' => 'UCHP - VBC',
        'uchp_mack' => 'UCHP - MACK',
        'uchp_ud' => 'UCHP - UD',
        'uchp_vce' => 'UCHP - VCE',
        'uchp_penta' => 'UCHP - PENTA',
        'warranty_bulletin' => 'Warranty Bulletin',
        'vda_plus' => 'VDA+',
        'tsa' => 'TSA'
    ];
    
    return $nombres[$acceso] ?? $acceso;
}
?>