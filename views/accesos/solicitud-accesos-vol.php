<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

protegerPagina(['usuario']);

// Solo jefes
if (!esJefe()) {
    header('Location: ../dashboard-usuario.php?error=' . urlencode('Solo jefes pueden solicitar accesos VOL'));
    exit();
}

// Obtener empleados del jefe
$sqlEmpleados = "
    SELECT id, nombre, apellido 
    FROM users 
    WHERE jefe_id = ? AND activo = 1
    ORDER BY nombre, apellido
";
$empleados = obtenerTodos($sqlEmpleados, [$_SESSION['user_id']]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../img/LogoGris.png">
    <title>Solicitar Accesos VOL - Sistema de Tickets</title>
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
                <div class="page-header mb-4">
                    <h1 class="page-title"><i class="bi bi-file-earmark-check"></i> Solicitar Accesos VOL</h1>
                    <p class="page-subtitle">Formulario de solicitud de accesos y licencias Volvo</p>
                </div>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($_GET['error']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form id="formSolicitudVOL" method="POST" action="../../actions/accesos/crear-solicitud-vol.php">
                    
                    <!-- SECCIÓN 1: Tipo de Solicitud -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-1-circle"></i> Tipo de Solicitud</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="tipo_solicitud" id="tipo_crear" value="crear" checked onchange="cambiarTipoSolicitud()">
                                        <label class="form-check-label fw-bold" for="tipo_crear">
                                            Crear Usuario
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="tipo_solicitud" id="tipo_solicitar" value="solicitar" onchange="cambiarTipoSolicitud()">
                                        <label class="form-check-label fw-bold" for="tipo_solicitar">
                                            Solicitar Accesos
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="tipo_solicitud" id="tipo_licencias" value="licencias" onchange="cambiarTipoSolicitud()">
                                        <label class="form-check-label fw-bold" for="tipo_licencias">
                                            Solicitar Licencias
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="tipo_solicitud" id="tipo_eliminar" value="eliminar" onchange="cambiarTipoSolicitud()">
                                        <label class="form-check-label fw-bold" for="tipo_eliminar">
                                            Eliminar Usuario
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 2: Selección de Empleado -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-2-circle"></i> Datos del Empleado</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Empleado <span class="text-danger">*</span></label>
                                <select class="form-select" id="empleado_id" name="empleado_id" required onchange="cargarDatosEmpleado(this.value)">
                                    <option value="">-- Seleccionar Empleado --</option>
                                    <?php foreach ($empleados as $emp): ?>
                                        <option value="<?= $emp['id'] ?>"><?= e($emp['nombre'] . ' ' . $emp['apellido']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Alerta de accesos existentes -->
                            <div id="alertaAccesosExistentes" class="alert alert-info d-none">
                                <h6><i class="bi bi-info-circle"></i> Este empleado ya tiene accesos registrados:</h6>
                                <ul id="listaAccesosExistentes" class="mb-0"></ul>
                                <p class="mb-0 mt-2"><strong>Los accesos existentes aparecerán deshabilitados. Solo puedes agregar nuevos accesos.</strong></p>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Nombre</label>
                                    <input type="text" class="form-control" id="nombre" readonly>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Apellido</label>
                                    <input type="text" class="form-control" id="apellido" readonly>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">ID Usuario <span id="asteriscoIdUsuario" class="text-danger d-none">*</span></label>
                                    <input type="text" class="form-control" id="id_usuario" name="id_usuario" placeholder="Se asignará después">
                                    <small id="helpIdUsuario" class="text-muted">Opcional para "Crear Usuario"</small>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Teléfono</label>
                                    <input type="text" class="form-control" id="telefono" readonly>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Cargo</label>
                                    <input type="text" class="form-control" id="cargo" readonly>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Concesionario</label>
                                    <input type="text" class="form-control" id="concesionario" readonly>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Sucursal</label>
                                    <input type="text" class="form-control" id="sucursal" readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email Corporativo</label>
                                    <input type="text" class="form-control" id="email" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 3: Accesos -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-3-circle"></i> Selección de Accesos</h5>
                        </div>
                        <div class="card-body">
                            
                            <!-- VOLVO TRUCKS -->
                            <h6 class="fw-bold text-primary mb-3">* VOLVO TRUCKS, VOLVO BUSES & UD TRUCKS</h6>
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input acceso-checkbox" type="checkbox" name="accesos[]" value="trucks_portal_volvo" id="cb_trucks_portal">
                                        <label class="form-check-label" for="cb_trucks_portal">Trucks Portal Volvo</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input acceso-checkbox" type="checkbox" name="accesos[]" value="impact_vt" id="cb_impact_vt">
                                        <label class="form-check-label" for="cb_impact_vt">Impact</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input acceso-checkbox" type="checkbox" name="accesos[]" value="technical_service" id="cb_technical_service">
                                        <label class="form-check-label" for="cb_technical_service">Technical Service Bulletin</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input acceso-checkbox" type="checkbox" name="accesos[]" value="ud_product_history" id="cb_ud_product_history">
                                        <label class="form-check-label" for="cb_ud_product_history">UD Product History Viewer</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input acceso-checkbox" type="checkbox" name="accesos[]" value="argus_dealer" id="cb_argus_dealer">
                                        <label class="form-check-label" for="cb_argus_dealer">Argus Dealer</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input acceso-checkbox" type="checkbox" name="accesos[]" value="parts_online" id="cb_parts_online">
                                        <label class="form-check-label" for="cb_parts_online">Parts Online</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input acceso-checkbox" type="checkbox" name="accesos[]" value="truck_campaign" id="cb_truck_campaign">
                                        <label class="form-check-label" for="cb_truck_campaign">Truck Campaign Information</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input acceso-checkbox" type="checkbox" name="accesos[]" value="vosp" id="cb_vosp">
                                        <label class="form-check-label" for="cb_vosp">VOSP</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input acceso-checkbox" type="checkbox" name="accesos[]" value="dynafleet" id="cb_dynafleet">
                                        <label class="form-check-label" for="cb_dynafleet">Dynafleet</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input acceso-checkbox" type="checkbox" name="accesos[]" value="product_history" id="cb_product_history">
                                        <label class="form-check-label" for="cb_product_history">Product History Viewer</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input acceso-checkbox" type="checkbox" name="accesos[]" value="trucks_portal_ud" id="cb_trucks_portal_ud">
                                        <label class="form-check-label" for="cb_trucks_portal_ud">Trucks Portal UD</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input acceso-checkbox" type="checkbox" name="accesos[]" value="wiring_diagrams" id="cb_wiring_diagrams">
                                        <label class="form-check-label" for="cb_wiring_diagrams">Wiring Diagrams</label>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <!-- MACK TRUCKS -->
                            <h6 class="fw-bold text-primary mb-3">* MACK TRUCKS</h6>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input acceso-checkbox" type="checkbox" name="accesos[]" value="mack_trucks_dealer" id="cb_mack_trucks_dealer">
                                        <label class="form-check-label" for="cb_mack_trucks_dealer">Trucks Dealer Portal</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input acceso-checkbox" type="checkbox" name="accesos[]" value="mack_impact" id="cb_mack_impact">
                                        <label class="form-check-label" for="cb_mack_impact">Impact</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input acceso-checkbox" type="checkbox" name="accesos[]" value="mack_electronic_info" id="cb_mack_electronic_info">
                                        <label class="form-check-label" for="cb_mack_electronic_info">Electronic Info System</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input acceso-checkbox" type="checkbox" name="accesos[]" value="mack_product_history" id="cb_mack_product_history">
                                        <label class="form-check-label" for="cb_mack_product_history">Product History Viewer</label>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <!-- VOLVO CONSTRUCTION EQUIPMENT -->
                            <h6 class="fw-bold text-primary mb-3">* VOLVO CONSTRUCTION EQUIPMENT</h6>
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input acceso-checkbox" type="checkbox" name="accesos[]" value="vdn" id="cb_vdn">
                                        <label class="form-check-label" for="cb_vdn">VDN</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input acceso-checkbox" type="checkbox" name="accesos[]" value="caretrack" id="cb_caretrack">
                                        <label class="form-check-label" for="cb_caretrack">CareTrack</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input acceso-checkbox" type="checkbox" name="accesos[]" value="chain" id="cb_chain">
                                        <label class="form-check-label" for="cb_chain">CHAIN</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input acceso-checkbox" type="checkbox" name="accesos[]" value="prosis_pro" id="cb_prosis_pro">
                                        <label class="form-check-label" for="cb_prosis_pro">Prosis Pro</label>
                                    </div>
                                    <input type="text" class="form-control form-control-sm mt-2" name="prosis_precio" placeholder="Precio Por Usuario (opcional)">
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input acceso-checkbox" type="checkbox" name="accesos[]" value="vlc" id="cb_vlc">
                                        <label class="form-check-label" for="cb_vlc">VLC</label>
                                    </div>
                                    <input type="text" class="form-control form-control-sm mt-2" name="vlc_precio" placeholder="Precio Por Usuario (opcional)">
                                </div>
                            </div>
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <div class="form-check">
                                        <input class="form-check-input acceso-checkbox" type="checkbox" name="accesos[]" value="tech_tool_matris" id="cb_tech_tool_matris">
                                        <label class="form-check-label" for="cb_tech_tool_matris">Tech Tool 2 / MATRIS 2</label>
                                    </div>
                                    <div class="ms-4 mt-2">
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" name="accesos[]" value="tt_accesos" id="cb_tt_accesos">
                                            <label class="form-check-label" for="cb_tt_accesos">Accesos</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" name="accesos[]" value="tt_licencia" id="cb_tt_licencia">
                                            <label class="form-check-label" for="cb_tt_licencia">Licencia</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <!-- VOLVO PENTA -->
                            <h6 class="fw-bold text-primary mb-3">* VOLVO PENTA</h6>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input acceso-checkbox" type="checkbox" name="accesos[]" value="vppn" id="cb_vppn">
                                        <label class="form-check-label" for="cb_vppn">VPPN</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input acceso-checkbox" type="checkbox" name="accesos[]" value="epc_offline" id="cb_epc_offline">
                                        <label class="form-check-label" for="cb_epc_offline">EPC Offline</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <div class="form-check">
                                        <input class="form-check-input acceso-checkbox" type="checkbox" name="accesos[]" value="vodia5" id="cb_vodia5">
                                        <label class="form-check-label" for="cb_vodia5">VODIA 5</label>
                                    </div>
                                    <div class="ms-4 mt-2">
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" name="accesos[]" value="vodia_acceso" id="cb_vodia_acceso">
                                            <label class="form-check-label" for="cb_vodia_acceso">Acceso</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" name="accesos[]" value="vodia_licencia" id="cb_vodia_licencia">
                                            <label class="form-check-label" for="cb_vodia_licencia">Licencia</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <!-- TECH TOOL 2 -->
                            <h6 class="fw-bold text-primary mb-3">* TECH TOOL 2</h6>
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <div class="form-check">
                                        <input class="form-check-input acceso-checkbox" type="checkbox" name="accesos[]" value="vtt" id="cb_vtt">
                                        <label class="form-check-label" for="cb_vtt">VTT</label>
                                    </div>
                                    <div class="ms-4 mt-2">
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" name="accesos[]" value="vtt_nueva" id="cb_vtt_nueva">
                                            <label class="form-check-label" for="cb_vtt_nueva">Nueva</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" name="accesos[]" value="vtt_renovacion" id="cb_vtt_renovacion">
                                            <label class="form-check-label" for="cb_vtt_renovacion">Renovación</label>
                                        </div>
                                        <input type="text" class="form-control form-control-sm mt-2" name="vtt_precio" placeholder="Precio Por Hardware + Licencia (opcional)" style="max-width: 350px;">
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <label class="form-label fw-bold">Accesos:</label>
                                    <div class="ms-4">
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" name="accesos[]" value="vtt_volvo_trucks" id="cb_vtt_volvo_trucks">
                                            <label class="form-check-label" for="cb_vtt_volvo_trucks">Volvo Trucks</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" name="accesos[]" value="vtt_volvo_buses" id="cb_vtt_volvo_buses">
                                            <label class="form-check-label" for="cb_vtt_volvo_buses">Volvo Buses</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" name="accesos[]" value="vtt_mack_trucks" id="cb_vtt_mack_trucks">
                                            <label class="form-check-label" for="cb_vtt_mack_trucks">Mack Trucks</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" name="accesos[]" value="vtt_ud_trucks" id="cb_vtt_ud_trucks">
                                            <label class="form-check-label" for="cb_vtt_ud_trucks">UD Trucks</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <!-- FACTURACIÓN -->
                            <h6 class="fw-bold text-primary mb-3">* FACTURACIÓN</h6>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input acceso-checkbox" type="checkbox" name="accesos[]" value="lds" id="cb_lds">
                                        <label class="form-check-label" for="cb_lds">LDS</label>
                                    </div>
                                    <input type="text" class="form-control form-control-sm mt-2" name="lds_rol" placeholder="Rol (opcional)">
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input acceso-checkbox" type="checkbox" name="accesos[]" value="gds" id="cb_gds">
                                        <label class="form-check-label" for="cb_gds">GDS</label>
                                    </div>
                                    <input type="text" class="form-control form-control-sm mt-2" name="gds_rol" placeholder="Rol (opcional)">
                                </div>
                            </div>
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <div class="form-check">
                                        <input class="form-check-input acceso-checkbox" type="checkbox" name="accesos[]" value="time_recording" id="cb_time_recording">
                                        <label class="form-check-label" for="cb_time_recording">Time Recording</label>
                                    </div>
                                    <input type="text" class="form-control form-control-sm mt-2" name="time_recording_codigo" placeholder="Código Dealer (opcional)" style="max-width: 300px;">
                                </div>
                            </div>

                            <hr>

                            <!-- GARANTÍAS -->
                            <h6 class="fw-bold text-primary mb-3">* GARANTÍAS</h6>
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <div class="form-check">
                                        <input class="form-check-input acceso-checkbox" type="checkbox" name="accesos[]" value="uchp" id="cb_uchp">
                                        <label class="form-check-label" for="cb_uchp">UCHP</label>
                                    </div>
                                    <div class="ms-4 mt-2">
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" name="accesos[]" value="uchp_vtc" id="cb_uchp_vtc">
                                            <label class="form-check-label" for="cb_uchp_vtc">VTC</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" name="accesos[]" value="uchp_vbc" id="cb_uchp_vbc">
                                            <label class="form-check-label" for="cb_uchp_vbc">VBC</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" name="accesos[]" value="uchp_mack" id="cb_uchp_mack">
                                            <label class="form-check-label" for="cb_uchp_mack">MACK</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" name="accesos[]" value="uchp_ud" id="cb_uchp_ud">
                                            <label class="form-check-label" for="cb_uchp_ud">UD</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" name="accesos[]" value="uchp_vce" id="cb_uchp_vce">
                                            <label class="form-check-label" for="cb_uchp_vce">VCE</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" name="accesos[]" value="uchp_penta" id="cb_uchp_penta">
                                            <label class="form-check-label" for="cb_uchp_penta">PENTA</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input acceso-checkbox" type="checkbox" name="accesos[]" value="warranty_bulletin" id="cb_warranty_bulletin">
                                        <label class="form-check-label" for="cb_warranty_bulletin">Warranty Bulletin</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input acceso-checkbox" type="checkbox" name="accesos[]" value="vda_plus" id="cb_vda_plus">
                                        <label class="form-check-label" for="cb_vda_plus">VDA+</label>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <!-- CONTRATOS -->
                            <h6 class="fw-bold text-primary mb-3">* CONTRATOS</h6>
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <div class="form-check">
                                        <input class="form-check-input acceso-checkbox" type="checkbox" name="accesos[]" value="tsa" id="cb_tsa">
                                        <label class="form-check-label" for="cb_tsa">TSA</label>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <!-- OTRO ACCESO -->
                            <h6 class="fw-bold text-primary mb-3">OTRO ACCESO (JUSTIFICAR)</h6>
                            <div class="row">
                                <div class="col-md-12">
                                    <textarea class="form-control" name="otro_acceso_texto" rows="3" placeholder="Especifique otros accesos y justificación..."></textarea>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="text-end">
                        <a href="../dashboard-usuario.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-file-earmark-arrow-down"></i> Generar Solicitud
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let accesosExistentes = [];

        function cambiarTipoSolicitud() {
            const tipo = document.querySelector('input[name="tipo_solicitud"]:checked').value;
            const inputIdUsuario = document.getElementById('id_usuario');
            const asterisco = document.getElementById('asteriscoIdUsuario');
            const help = document.getElementById('helpIdUsuario');

            if (tipo === 'crear') {
                inputIdUsuario.required = false;
                inputIdUsuario.placeholder = 'Se asignará después';
                asterisco.classList.add('d-none');
                help.textContent = 'Opcional para "Crear Usuario"';
                
                // Si está precargado, limpiarlo
                if (inputIdUsuario.hasAttribute('readonly')) {
                    inputIdUsuario.removeAttribute('readonly');
                    inputIdUsuario.value = '';
                }
            } else {
                inputIdUsuario.required = true;
                inputIdUsuario.placeholder = 'Ej: USR12345';
                asterisco.classList.remove('d-none');
                help.textContent = 'Obligatorio para este tipo de solicitud';
            }
        }

        function cargarDatosEmpleado(empleadoId) {
            if (!empleadoId) {
                limpiarFormulario();
                return;
            }

            fetch(`../../actions/accesos/verificar-empleado.php?id=${empleadoId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        limpiarFormulario();
                        return;
                    }

                    // Auto-rellenar datos
                    document.getElementById('nombre').value = data.nombre;
                    document.getElementById('apellido').value = data.apellido;
                    document.getElementById('telefono').value = data.telefono || '';
                    document.getElementById('cargo').value = data.puesto || '';
                    document.getElementById('concesionario').value = 'AUTOMOTRIZ COMERCIO PACIFICO';
                    document.getElementById('sucursal').value = data.sede_nombre || '';
                    document.getElementById('email').value = data.email || '';

                    // Lógica de ID Usuario
                    const tipo = document.querySelector('input[name="tipo_solicitud"]:checked').value;
                    const inputIdUsuario = document.getElementById('id_usuario');

                    if (tipo === 'crear') {
                        // Crear usuario: siempre vacío
                        inputIdUsuario.value = '';
                        inputIdUsuario.removeAttribute('readonly');
                    } else {
                        // Otros tipos: precargar si existe
                        if (data.id_usuario_vol) {
                            inputIdUsuario.value = data.id_usuario_vol;
                            inputIdUsuario.setAttribute('readonly', 'readonly');
                        } else {
                            inputIdUsuario.value = '';
                            inputIdUsuario.removeAttribute('readonly');
                        }
                    }

                    // Manejar accesos existentes
                    accesosExistentes = data.accesos_existentes || [];
                    
                    if (data.tiene_accesos) {
                        mostrarAccesosExistentes(accesosExistentes);
                        marcarCheckboxesExistentes(accesosExistentes);
                    } else {
                        ocultarAccesosExistentes();
                        desmarcarTodosCheckboxes();
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    alert('Error al cargar datos del empleado');
                });
        }

        function mostrarAccesosExistentes(accesos) {
            const alerta = document.getElementById('alertaAccesosExistentes');
            const lista = document.getElementById('listaAccesosExistentes');
            
            lista.innerHTML = '';
            accesos.forEach(acceso => {
                const li = document.createElement('li');
                li.textContent = formatearNombreAcceso(acceso);
                lista.appendChild(li);
            });
            
            alerta.classList.remove('d-none');
        }

        function ocultarAccesosExistentes() {
            document.getElementById('alertaAccesosExistentes').classList.add('d-none');
        }

        function marcarCheckboxesExistentes(accesos) {
            // Primero desmarcar todos
            desmarcarTodosCheckboxes();
            
            accesos.forEach(acceso => {
                const checkbox = document.querySelector(`input[value="${acceso}"]`);
                if (checkbox) {
                    checkbox.checked = true;
                    checkbox.disabled = true;
                    checkbox.parentElement.classList.add('text-muted');
                }
            });
        }

        function desmarcarTodosCheckboxes() {
            const checkboxes = document.querySelectorAll('.acceso-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = false;
                cb.disabled = false;
                cb.parentElement.classList.remove('text-muted');
            });
        }

        function limpiarFormulario() {
            document.getElementById('nombre').value = '';
            document.getElementById('apellido').value = '';
            document.getElementById('id_usuario').value = '';
            document.getElementById('telefono').value = '';
            document.getElementById('cargo').value = '';
            document.getElementById('concesionario').value = '';
            document.getElementById('sucursal').value = '';
            document.getElementById('email').value = '';
            
            ocultarAccesosExistentes();
            desmarcarTodosCheckboxes();
        }

        function formatearNombreAcceso(acceso) {
            const nombres = {
                'trucks_portal_volvo': 'Trucks Portal Volvo',
                'argus_dealer': 'Argus Dealer',
                'dynafleet': 'Dynafleet',
                'impact_vt': 'Impact',
                'parts_online': 'Parts Online',
                'product_history': 'Product History Viewer',
                'technical_service': 'Technical Service Bulletin',
                'truck_campaign': 'Truck Campaign Information',
                'trucks_portal_ud': 'Trucks Portal UD',
                'ud_product_history': 'UD Product History Viewer',
                'vosp': 'VOSP',
                'wiring_diagrams': 'Wiring Diagrams',
                'mack_trucks_dealer': 'Mack Trucks Dealer Portal',
                'mack_electronic_info': 'Mack Electronic Info System',
                'mack_impact': 'Mack Impact',
                'mack_product_history': 'Mack Product History Viewer',
                'vdn': 'VDN',
                'caretrack': 'CareTrack',
                'chain': 'CHAIN',
                'prosis_pro': 'Prosis Pro',
                'vlc': 'VLC',
                'tech_tool_matris': 'Tech Tool 2 / MATRIS 2',
                'tt_accesos': 'Tech Tool 2 - Accesos',
                'tt_licencia': 'Tech Tool 2 - Licencia',
                'vppn': 'VPPN',
                'epc_offline': 'EPC Offline',
                'vodia5': 'VODIA 5',
                'vodia_acceso': 'VODIA 5 - Acceso',
                'vodia_licencia': 'VODIA 5 - Licencia',
                'vtt': 'VTT',
                'vtt_nueva': 'VTT - Nueva',
                'vtt_renovacion': 'VTT - Renovación',
                'vtt_volvo_trucks': 'VTT - Volvo Trucks',
                'vtt_volvo_buses': 'VTT - Volvo Buses',
                'vtt_mack_trucks': 'VTT - Mack Trucks',
                'vtt_ud_trucks': 'VTT - UD Trucks',
                'lds': 'LDS',
                'gds': 'GDS',
                'time_recording': 'Time Recording',
                'uchp': 'UCHP',
                'uchp_vtc': 'UCHP - VTC',
                'uchp_vbc': 'UCHP - VBC',
                'uchp_mack': 'UCHP - MACK',
                'uchp_ud': 'UCHP - UD',
                'uchp_vce': 'UCHP - VCE',
                'uchp_penta': 'UCHP - PENTA',
                'warranty_bulletin': 'Warranty Bulletin',
                'vda_plus': 'VDA+',
                'tsa': 'TSA'
            };
            
            return nombres[acceso] || acceso;
        }

        // Validación antes de enviar
        document.getElementById('formSolicitudVOL').addEventListener('submit', function(e) {
            const empleadoId = document.getElementById('empleado_id').value;
            if (!empleadoId) {
                e.preventDefault();
                alert('Debe seleccionar un empleado');
                return false;
            }

            // Validar que al menos un checkbox esté marcado y habilitado
            const checkboxes = document.querySelectorAll('.acceso-checkbox:checked:not(:disabled)');
            if (checkboxes.length === 0) {
                const otroAcceso = document.querySelector('textarea[name="otro_acceso_texto"]').value.trim();
                if (!otroAcceso) {
                    e.preventDefault();
                    alert('Debe seleccionar al menos un acceso o especificar en "Otro Acceso"');
                    return false;
                }
            }
        });
    </script>
</body>
</html>