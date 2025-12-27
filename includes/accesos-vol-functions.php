<?php
use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Log personalizado para debug VOL
 */
function logVOL($mensaje) {
    $rutaLog = __DIR__ . '/../debug-vol.txt';
    $timestamp = date('Y-m-d H:i:s');
    $linea = "[$timestamp] $mensaje\n";
    file_put_contents($rutaLog, $linea, FILE_APPEND);
}

/**
 * Limpiar log anterior
 */
function limpiarLogVOL() {
    $rutaLog = __DIR__ . '/../debug-vol.txt';
    file_put_contents($rutaLog, "=== NUEVO LOG DE DEBUG VOL ===\n");
}

function sanitizarParaWord($texto) {
    if (empty($texto)) {
        return '';
    }
    
    $texto = (string) $texto;
    
    if (!mb_check_encoding($texto, 'UTF-8')) {
        $texto = mb_convert_encoding($texto, 'UTF-8', 'auto');
    }
    
    $texto = str_replace('á', 'a', $texto);
    $texto = str_replace('é', 'e', $texto);
    $texto = str_replace('í', 'i', $texto);
    $texto = str_replace('ó', 'o', $texto);
    $texto = str_replace('ú', 'u', $texto);
    $texto = str_replace('Á', 'A', $texto);
    $texto = str_replace('É', 'E', $texto);
    $texto = str_replace('Í', 'I', $texto);
    $texto = str_replace('Ó', 'O', $texto);
    $texto = str_replace('Ú', 'U', $texto);
    $texto = str_replace('ñ', 'n', $texto);
    $texto = str_replace('Ñ', 'N', $texto);
    $texto = str_replace('ü', 'u', $texto);
    $texto = str_replace('Ü', 'U', $texto);
    
    $texto = preg_replace('/[^\x20-\x7E]/', '', $texto);
    $texto = preg_replace('/\s+/', ' ', $texto);
    
    return trim($texto);
}
/**
 * Generar folio único para solicitudes VOL
 */
function generarFolioVOL() {
    $anio = date('Y');
    
    $sql = "SELECT TOP 1 folio FROM solicitudes_accesos_vol_historial 
            WHERE folio LIKE ? 
            ORDER BY id DESC";
    
    $ultimoFolio = obtenerUno($sql, ['VOL-' . $anio . '-%']);
    
    if ($ultimoFolio) {
        $partes = explode('-', $ultimoFolio['folio']);
        $ultimoNumero = intval($partes[2]);
        $nuevoNumero = $ultimoNumero + 1;
    } else {
        $nuevoNumero = 1;
    }
    
    return 'VOL-' . $anio . '-' . str_pad($nuevoNumero, 3, '0', STR_PAD_LEFT);
}

/**
 * Mapear nombres de accesos de BD a variables de plantilla Word
 */
function obtenerNombreVariable($acceso) {
    $mapeo = [
        'trucks_portal_volvo' => 'CB_TRUCKS_PORTAL',
        'argus_dealer' => 'CB_ARGUS_DEALER',
        'dynafleet' => 'CB_DYNAFLEET',
        'impact_vt' => 'CB_IMPACT_VT',
        'parts_online' => 'CB_PARTS_ONLINE',
        'product_history' => 'CB_PRODUCT_HISTORY',
        'technical_service' => 'CB_TECHNICAL_SERVICE',
        'truck_campaign' => 'CB_TRUCK_CAMPAIGN',
        'trucks_portal_ud' => 'CB_TRUCKS_PORTAL_UD',
        'ud_product_history' => 'CB_UD_PRODUCT_HISTORY',
        'vosp' => 'CB_VOSP',
        'wiring_diagrams' => 'CB_WIRING_DIAGRAMS',
        'mack_trucks_dealer' => 'CB_MACK_TRUCKS_DEALER',
        'mack_electronic_info' => 'CB_MACK_ELECTRONIC_INFO',
        'mack_impact' => 'CB_MACK_IMPACT',
        'mack_product_history' => 'CB_MACK_PRODUCT_HISTORY',
        'vdn' => 'CB_VDN',
        'caretrack' => 'CB_CARETRACK',
        'chain' => 'CB_CHAIN',
        'prosis_pro' => 'CB_PROSIS_PRO',
        'vlc' => 'CB_VLC',
        'tech_tool_matris' => 'CB_TECH_TOOL_MATRIS',
        'tt_accesos' => 'CB_TT_ACCESOS',
        'tt_licencia' => 'CB_TT_LICENCIA',
        'vppn' => 'CB_VPPN',
        'epc_offline' => 'CB_EPC_OFFLINE',
        'vodia5' => 'CB_VODIA5',
        'vodia_acceso' => 'CB_VODIA_ACCESO',
        'vodia_licencia' => 'CB_VODIA_LICENCIA',
        'vtt' => 'CB_VTT',
        'vtt_nueva' => 'CB_VTT_NUEVA',
        'vtt_renovacion' => 'CB_VTT_RENOVACION',
        'vtt_volvo_trucks' => 'CB_VTT_VOLVO_TRUCKS',
        'vtt_volvo_buses' => 'CB_VTT_VOLVO_BUSES',
        'vtt_mack_trucks' => 'CB_VTT_MACK_TRUCKS',
        'vtt_ud_trucks' => 'CB_VTT_UD_TRUCKS',
        'lds' => 'CB_LDS',
        'gds' => 'CB_GDS',
        'time_recording' => 'CB_TIME_RECORDING',
        'uchp' => 'CB_UCHP',
        'uchp_vtc' => 'CB_UCHP_VTC',
        'uchp_vbc' => 'CB_UCHP_VBC',
        'uchp_mack' => 'CB_UCHP_MACK',
        'uchp_ud' => 'CB_UCHP_UD',
        'uchp_vce' => 'CB_UCHP_VCE',
        'uchp_penta' => 'CB_UCHP_PENTA',
        'warranty_bulletin' => 'CB_WARRANTY_BULLETIN',
        'vda_plus' => 'CB_VDA_PLUS',
        'tsa' => 'CB_TSA'
    ];

    return $mapeo[$acceso] ?? null;
}

/**
 * Generar documento Word desde plantilla
 */
function generarWordDesdePlantillaVOL($solicitud, $accesosNuevos, $camposAdicionales) {
    logVOL("========================================");
    logVOL("INICIO generarWordDesdePlantillaVOL()");
    logVOL("Folio: " . $solicitud['folio']);
    
    $rutaPlantillaOriginal = __DIR__ . '/../documents/templates/plantilla_solicitud_accesos_vol.docx';

    if (!file_exists($rutaPlantillaOriginal)) {
        logVOL("ERROR: Plantilla no encontrada");
        throw new Exception("Plantilla no encontrada");
    }
    
    logVOL("Plantilla encontrada: " . filesize($rutaPlantillaOriginal) . " bytes");

    $dirTemporal = __DIR__ . '/../documents/temp/';
    if (!file_exists($dirTemporal)) {
        mkdir($dirTemporal, 0755, true);
    }
    
    $rutaPlantillaTemporal = $dirTemporal . 'plantilla_temp_' . uniqid() . '.docx';
    
    if (!copy($rutaPlantillaOriginal, $rutaPlantillaTemporal)) {
        throw new Exception("No se pudo crear copia temporal");
    }
    
    logVOL("Copia temporal creada");

    try {
        $templateProcessor = new TemplateProcessor($rutaPlantillaTemporal);
        
        $checkboxesMarcados = [];
        foreach ($accesosNuevos as $acceso) {
            $variable = obtenerNombreVariable($acceso);
            if ($variable) {
                $checkboxesMarcados[$variable] = true;
            }
        }
        
        $todosCheckboxes = [
            'CB_TRUCKS_PORTAL', 'CB_ARGUS_DEALER', 'CB_DYNAFLEET', 'CB_IMPACT_VT',
            'CB_PARTS_ONLINE', 'CB_PRODUCT_HISTORY', 'CB_TECHNICAL_SERVICE', 'CB_TRUCK_CAMPAIGN',
            'CB_TRUCKS_PORTAL_UD', 'CB_UD_PRODUCT_HISTORY', 'CB_VOSP', 'CB_WIRING_DIAGRAMS',
            'CB_MACK_TRUCKS_DEALER', 'CB_MACK_ELECTRONIC_INFO', 'CB_MACK_IMPACT', 'CB_MACK_PRODUCT_HISTORY',
            'CB_VDN', 'CB_CARETRACK', 'CB_CHAIN', 'CB_PROSIS_PRO', 'CB_VLC',
            'CB_TECH_TOOL_MATRIS', 'CB_TT_ACCESOS', 'CB_TT_LICENCIA',
            'CB_VPPN', 'CB_EPC_OFFLINE', 'CB_VODIA5', 'CB_VODIA_ACCESO', 'CB_VODIA_LICENCIA',
            'CB_VTT', 'CB_VTT_NUEVA', 'CB_VTT_RENOVACION',
            'CB_VTT_VOLVO_TRUCKS', 'CB_VTT_VOLVO_BUSES', 'CB_VTT_MACK_TRUCKS', 'CB_VTT_UD_TRUCKS',
            'CB_LDS', 'CB_GDS', 'CB_TIME_RECORDING',
            'CB_UCHP', 'CB_UCHP_VTC', 'CB_UCHP_VBC', 'CB_UCHP_MACK',
            'CB_UCHP_UD', 'CB_UCHP_VCE', 'CB_UCHP_PENTA', 'CB_WARRANTY_BULLETIN', 'CB_VDA_PLUS',
            'CB_TSA'
        ];
        

        // === LOG DE DATOS ANTES DE SANITIZAR ===
logVOL("=== DATOS ANTES DE SANITIZAR ===");
logVOL("NOMBRE RAW: [" . bin2hex($solicitud['nombre']) . "]");
logVOL("APELLIDO RAW: [" . bin2hex($solicitud['apellido']) . "]");
logVOL("CARGO RAW: [" . bin2hex($solicitud['cargo'] ?? '') . "]");
// Preparar array completo de valores (SANITIZADOS)
$valores = [
    // ... tu código actual
];
// === LOG DE DATOS DESPUÉS DE SANITIZAR ===
logVOL("=== DATOS DESPUÉS DE SANITIZAR ===");
logVOL("NOMBRE CLEAN: [" . $valores['NOMBRE'] . "]");
logVOL("APELLIDO CLEAN: [" . $valores['APELLIDO'] . "]");
logVOL("CARGO CLEAN: [" . $valores['CARGO'] . "]");

        $valores = [
            'TIPO_CREAR' => $solicitud['tipo_solicitud'] == 'crear' ? 'X' : ' ',
            'TIPO_SOLICITAR' => $solicitud['tipo_solicitud'] == 'solicitar' ? 'X' : ' ',
            'TIPO_LICENCIAS' => $solicitud['tipo_solicitud'] == 'licencias' ? 'X' : ' ',
            'TIPO_ELIMINAR' => $solicitud['tipo_solicitud'] == 'eliminar' ? 'X' : ' ',
            'NOMBRE' => sanitizarParaWord($solicitud['nombre']),
            'APELLIDO' => sanitizarParaWord($solicitud['apellido']),
            'ID_USUARIO' => sanitizarParaWord($solicitud['id_usuario'] ?? ''),
            'TELEFONO' => sanitizarParaWord($solicitud['telefono'] ?? ''),
            'CARGO' => sanitizarParaWord($solicitud['cargo'] ?? ''),
            'CONCESIONARIO' => sanitizarParaWord($solicitud['concesionario'] ?? ''),
            'SUCURSAL' => sanitizarParaWord($solicitud['sucursal'] ?? ''),
            'EMAIL' => sanitizarParaWord($solicitud['correo_corporativo'] ?? ''),
            'PROSIS_PRECIO' => sanitizarParaWord($camposAdicionales['prosis_precio'] ?? ''),
            'VLC_PRECIO' => sanitizarParaWord($camposAdicionales['vlc_precio'] ?? ''),
            'VTT_PRECIO' => sanitizarParaWord($camposAdicionales['vtt_precio'] ?? ''),
            'LDS_ROL' => sanitizarParaWord($camposAdicionales['lds_rol'] ?? ''),
            'GDS_ROL' => sanitizarParaWord($camposAdicionales['gds_rol'] ?? ''),
            'TIME_RECORDING_CODIGO' => sanitizarParaWord($camposAdicionales['time_recording_codigo'] ?? ''),
            'OTRO_ACCESO_TEXTO' => sanitizarParaWord($camposAdicionales['otro_acceso_texto'] ?? ''),
            'FECHA' => date('d/m/Y')
        ];
        
        foreach ($todosCheckboxes as $cb) {
            $valores[$cb] = isset($checkboxesMarcados[$cb]) ? 'X' : ' ';
        }
        
        logVOL("Ejecutando setValues con " . count($valores) . " valores");
        $templateProcessor->setValues($valores);
        logVOL("setValues completado");

        $nombreArchivo = $solicitud['folio'] . '.docx';
        $dirDestino = __DIR__ . '/../documents/solicitudes_vol/';

        if (!file_exists($dirDestino)) {
            mkdir($dirDestino, 0755, true);
        }

        $rutaDestino = $dirDestino . $nombreArchivo;
        
        logVOL("Guardando en: " . $rutaDestino);
        $templateProcessor->saveAs($rutaDestino);
        logVOL("saveAs completado");
        
        if (!file_exists($rutaDestino)) {
            throw new Exception("El archivo no se creo");
        }
        
        $tamano = filesize($rutaDestino);
        logVOL("Archivo guardado: " . $tamano . " bytes");
        
        $zip = new ZipArchive();
        if ($zip->open($rutaDestino) === TRUE) {
            logVOL("ZIP valido con " . $zip->numFiles . " archivos");
            $zip->close();
        } else {
            logVOL("ERROR: ZIP CORRUPTO");
        }
        
        unlink($rutaPlantillaTemporal);
        logVOL("FIN generarWordDesdePlantillaVOL - EXITO");
        logVOL("========================================\n");
        
        return $rutaDestino;
        
    } catch (Exception $e) {
        logVOL("ERROR: " . $e->getMessage());
        
        if (file_exists($rutaPlantillaTemporal)) {
            unlink($rutaPlantillaTemporal);
        }
        throw $e;
    }
}