<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/accesos-vol-functions.php';
require_once '../../vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../views/accesos/solicitud-accesos-vol.php');
    exit();
}

// Solo jefes
protegerPagina(['usuario']);

if (!esJefe()) {
    header('Location: ../../views/dashboard-usuario.php?error=' . urlencode('No autorizado'));
    exit();
}

// === OBTENER DATOS DEL FORMULARIO ===
$empleado_id = $_POST['empleado_id'] ?? 0;
$tipo_solicitud = $_POST['tipo_solicitud'] ?? 'crear';
$id_usuario = trim($_POST['id_usuario'] ?? '');
$accesos = $_POST['accesos'] ?? [];

// Campos adicionales
$prosis_precio = trim($_POST['prosis_precio'] ?? '');
$vlc_precio = trim($_POST['vlc_precio'] ?? '');
$vtt_precio = trim($_POST['vtt_precio'] ?? '');
$lds_rol = trim($_POST['lds_rol'] ?? '');
$gds_rol = trim($_POST['gds_rol'] ?? '');
$time_recording_codigo = trim($_POST['time_recording_codigo'] ?? '');
$otro_acceso_texto = trim($_POST['otro_acceso_texto'] ?? '');

// === VALIDACIONES ===
if (empty($empleado_id)) {
    header('Location: ../../views/accesos/solicitud-accesos-vol.php?error=' . urlencode('Debe seleccionar un empleado'));
    exit();
}

// Validar ID Usuario según tipo de solicitud
if ($tipo_solicitud !== 'crear' && empty($id_usuario)) {
    header('Location: ../../views/accesos/solicitud-accesos-vol.php?error=' . urlencode('El ID de usuario es obligatorio para este tipo de solicitud'));
    exit();
}

// Validar que al menos haya un acceso seleccionado o texto en "Otro Acceso"
if (empty($accesos) && empty($otro_acceso_texto)) {
    header('Location: ../../views/accesos/solicitud-accesos-vol.php?error=' . urlencode('Debe seleccionar al menos un acceso o especificar en "Otro Acceso"'));
    exit();
}

// Verificar que el empleado pertenece al jefe
$sqlVerificar = "SELECT id FROM users WHERE id = ? AND jefe_id = ? AND activo = 1";
$empleadoValido = obtenerUno($sqlVerificar, [$empleado_id, $_SESSION['user_id']]);

if (!$empleadoValido) {
    header('Location: ../../views/accesos/solicitud-accesos-vol.php?error=' . urlencode('El empleado no pertenece a tu equipo'));
    exit();
}

try {
    // === OBTENER DATOS COMPLETOS DEL EMPLEADO ===
    $sqlEmpleado = "
        SELECT 
            u.id,
            u.nombre,
            u.apellido,
            u.email,
            u.telefono,
            u.puesto,
            s.nombre as sede_nombre,
            a.nombre as area_nombre
        FROM users u
        LEFT JOIN sedes s ON u.sede_id = s.id
        LEFT JOIN areas a ON u.area_id = a.id
        WHERE u.id = ?
    ";
    
    $empleado = obtenerUno($sqlEmpleado, [$empleado_id]);
    
    if (!$empleado) {
        throw new Exception("No se pudo obtener la información del empleado");
    }
    
    // === VERIFICAR SI YA EXISTE REGISTRO MAESTRO ===
    $sqlExistente = "SELECT id FROM solicitudes_accesos_vol WHERE empleado_id = ?";
    $registroExistente = obtenerUno($sqlExistente, [$empleado_id]);
    
    if ($registroExistente) {
        // ACTUALIZAR registro existente
        $sqlUpdate = "
            UPDATE solicitudes_accesos_vol 
            SET 
                id_usuario = ?,
                telefono = ?,
                cargo = ?,
                sucursal = ?,
                area = ?,
                correo_corporativo = ?,
                ultima_actualizacion = GETDATE(),
                ultima_solicitud_por = ?
            WHERE empleado_id = ?
        ";
        
        ejecutarQuery($sqlUpdate, [
            $id_usuario,
            $empleado['telefono'],
            $empleado['puesto'],
            $empleado['sede_nombre'],
            $empleado['area_nombre'],
            $empleado['email'],
            $_SESSION['user_id'],
            $empleado_id
        ]);
        
        $solicitud_id = $registroExistente['id'];
        
    } else {
        // INSERTAR nuevo registro maestro
        $sqlInsert = "
            INSERT INTO solicitudes_accesos_vol (
                empleado_id,
                nombre,
                apellido,
                id_usuario,
                telefono,
                cargo,
                concesionario,
                sucursal,
                area,
                correo_corporativo,
                ultima_solicitud_por
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        ejecutarQuery($sqlInsert, [
            $empleado_id,
            $empleado['nombre'],
            $empleado['apellido'],
            $id_usuario,
            $empleado['telefono'],
            $empleado['puesto'],
            'AUTOMOTRIZ COMERCIO PACIFICO',
            $empleado['sede_nombre'],
            $empleado['area_nombre'],
            $empleado['email'],
            $_SESSION['user_id']
        ]);
        
        // Obtener ID del registro insertado
        $sqlUltimoId = "SELECT TOP 1 id FROM solicitudes_accesos_vol WHERE empleado_id = ? ORDER BY id DESC";
        $ultimoRegistro = obtenerUno($sqlUltimoId, [$empleado_id]);
        $solicitud_id = $ultimoRegistro['id'];
    }
    
        // === OBTENER ACCESOS EXISTENTES ===
    $sqlAccesosExistentes = "SELECT acceso FROM solicitudes_accesos_vol_detalle WHERE solicitud_id = ?";
    $accesosExistentes = obtenerTodos($sqlAccesosExistentes, [$solicitud_id]);
    $accesosPrevios = array_column($accesosExistentes, 'acceso');
    
    // === INSERTAR SOLO ACCESOS NUEVOS EN DETALLE ===
    $accesosNuevos = [];
    $idsAccesosNuevos = [];
    
    foreach ($accesos as $acceso) {
        // Verificar que no exista ya
        if (!in_array($acceso, $accesosPrevios)) {
            $sqlDetalle = "
                INSERT INTO solicitudes_accesos_vol_detalle (
                    solicitud_id,
                    tipo_movimiento,
                    categoria,
                    acceso,
                    solicitado_por
                ) VALUES (?, ?, ?, ?, ?)
            ";
            
            // Determinar categoría
            $categoria = determinarCategoria($acceso);
            
            ejecutarQuery($sqlDetalle, [
                $solicitud_id,
                $tipo_solicitud,
                $categoria,
                $acceso,
                $_SESSION['user_id']
            ]);
            
            // Obtener ID del detalle insertado
            $sqlUltimoDetalle = "SELECT TOP 1 id FROM solicitudes_accesos_vol_detalle ORDER BY id DESC";
            $ultimoDetalle = obtenerUno($sqlUltimoDetalle, []);
            $idsAccesosNuevos[] = $ultimoDetalle['id'];
            $accesosNuevos[] = $acceso;
        }
    }
    
    // === GENERAR FOLIO ÚNICO ===
    $folio = generarFolioVOL();
    
    // === PREPARAR DATOS PARA WORD ===
    $datosSolicitud = [
        'folio' => $folio,
        'tipo_solicitud' => $tipo_solicitud,
        'nombre' => $empleado['nombre'],
        'apellido' => $empleado['apellido'],
        'id_usuario' => $id_usuario,
        'telefono' => $empleado['telefono'],
        'cargo' => $empleado['puesto'],
        'concesionario' => 'AUTOMOTRIZ CENTRAL DEL PERÚ SAC',
        'sucursal' => $empleado['sede_nombre'],
        'correo_corporativo' => $empleado['email']
    ];
    
    // Capturar campos adicionales del formulario
$prosis_precio = trim($_POST['prosis_precio'] ?? '');
$vlc_precio = trim($_POST['vlc_precio'] ?? '');
$vtt_precio = trim($_POST['vtt_precio'] ?? '');
$lds_rol = trim($_POST['lds_rol'] ?? '');
$gds_rol = trim($_POST['gds_rol'] ?? '');
$time_recording_codigo = trim($_POST['time_recording_codigo'] ?? '');
$otro_acceso_texto = trim($_POST['otro_acceso_texto'] ?? '');

    $camposAdicionales = [
        'prosis_precio' => $prosis_precio,
        'vlc_precio' => $vlc_precio,
        'vtt_precio' => $vtt_precio,
        'lds_rol' => $lds_rol,
        'gds_rol' => $gds_rol,
        'time_recording_codigo' => $time_recording_codigo,
        'otro_acceso_texto' => $otro_acceso_texto
    ];
    
    
    // === GENERAR DOCUMENTO WORD ===
   $rutaDocumento = generarWordDesdePlantillaVOL($datosSolicitud, $accesosNuevos, $camposAdicionales);
    
    // === INSERTAR EN HISTORIAL ===
    $sqlHistorial = "
    INSERT INTO solicitudes_accesos_vol_historial (
        solicitud_id,
        folio,
        tipo_solicitud,
        accesos_incluidos,
        ruta_documento,
        generado_por,
        estado,
        campos_adicionales
    ) VALUES (?, ?, ?, ?, ?, ?, 'pendiente', ?)
";
    
  ejecutarQuery($sqlHistorial, [
    $solicitud_id,
    $folio,
    $tipo_solicitud,
    json_encode($idsAccesosNuevos),
    $rutaDocumento,
    $_SESSION['user_id'],
    json_encode($camposAdicionales)  // ← AGREGAR ESTO
]);
    
    // === ÉXITO ===
    header('Location: ../../views/accesos/ver-solicitud-vol.php?folio=' . urlencode($folio) . '&success=1');
    exit();
    
} catch (Exception $e) {
    error_log("Error al crear solicitud VOL: " . $e->getMessage());
    header('Location: ../../views/accesos/solicitud-accesos-vol.php?error=' . urlencode('Error al procesar la solicitud: ' . $e->getMessage()));
    exit();
}

/**
 * Determinar categoría según acceso
 */
function determinarCategoria($acceso) {
    $mapeo = [
        'trucks_portal_volvo' => 'volvo_trucks',
        'argus_dealer' => 'volvo_trucks',
        'dynafleet' => 'volvo_trucks',
        'impact_vt' => 'volvo_trucks',
        'parts_online' => 'volvo_trucks',
        'product_history' => 'volvo_trucks',
        'technical_service' => 'volvo_trucks',
        'truck_campaign' => 'volvo_trucks',
        'trucks_portal_ud' => 'volvo_trucks',
        'ud_product_history' => 'volvo_trucks',
        'vosp' => 'volvo_trucks',
        'wiring_diagrams' => 'volvo_trucks',
        
        'mack_trucks_dealer' => 'mack',
        'mack_electronic_info' => 'mack',
        'mack_impact' => 'mack',
        'mack_product_history' => 'mack',
        
        'vdn' => 'vce',
        'caretrack' => 'vce',
        'chain' => 'vce',
        'prosis_pro' => 'vce',
        'vlc' => 'vce',
        'tech_tool_matris' => 'vce',
        'tt_accesos' => 'vce',
        'tt_licencia' => 'vce',
        
        'vppn' => 'penta',
        'epc_offline' => 'penta',
        'vodia5' => 'penta',
        'vodia_acceso' => 'penta',
        'vodia_licencia' => 'penta',
        
        'vtt' => 'tech_tool',
        'vtt_nueva' => 'tech_tool',
        'vtt_renovacion' => 'tech_tool',
        'vtt_volvo_trucks' => 'tech_tool',
        'vtt_volvo_buses' => 'tech_tool',
        'vtt_mack_trucks' => 'tech_tool',
        'vtt_ud_trucks' => 'tech_tool',
        
        'lds' => 'facturacion',
        'gds' => 'facturacion',
        'time_recording' => 'facturacion',
        
        'uchp' => 'garantias',
        'uchp_vtc' => 'garantias',
        'uchp_vbc' => 'garantias',
        'uchp_mack' => 'garantias',
        'uchp_ud' => 'garantias',
        'uchp_vce' => 'garantias',
        'uchp_penta' => 'garantias',
        'warranty_bulletin' => 'garantias',
        'vda_plus' => 'garantias',
        
        'tsa' => 'contratos'
    ];
    
    return $mapeo[$acceso] ?? 'otro';
}