<?php
require_once '../../includes/accesos-vol-functions.php';

session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

logVOL("\n\n===== INICIO DESCARGA =====");
logVOL("Usuario intentando descargar: " . ($_SESSION['user_id'] ?? 'NO AUTENTICADO'));

// Proteger
protegerPagina(['usuario']);

if (!esJefe()) {
    logVOL("ERROR: Usuario no es jefe");
    die('No autorizado');
}

$folio = $_GET['folio'] ?? '';
logVOL("Folio solicitado: " . $folio);

if (empty($folio)) {
    logVOL("ERROR: Folio vacío");
    die('Folio no válido');
}

// Verificar que el folio pertenece al usuario
$sql = "SELECT id FROM solicitudes_accesos_vol_historial 
        WHERE folio = ? AND generado_por = ?";
        
$solicitud = obtenerUno($sql, [$folio, $_SESSION['user_id']]);

if (!$solicitud) {
    logVOL("ERROR: Solicitud no encontrada o sin permiso");
    die('Solicitud no encontrada o no tienes permiso');
}

logVOL("✓ Solicitud validada, ID: " . $solicitud['id']);

// Ruta del archivo
$nombreArchivo = $folio . '.docx';
$rutaArchivo = __DIR__ . '/../../documents/solicitudes_vol/' . $nombreArchivo;

logVOL("Ruta del archivo: " . $rutaArchivo);
logVOL("Archivo existe: " . (file_exists($rutaArchivo) ? 'SI' : 'NO'));

if (!file_exists($rutaArchivo)) {
    logVOL("ERROR: Archivo no encontrado en disco");
    die('Archivo no encontrado');
}

$tamano = filesize($rutaArchivo);
logVOL("Tamaño del archivo: " . $tamano . " bytes");

// Verificar integridad ANTES de descargar
$zipTest = new ZipArchive();
if ($zipTest->open($rutaArchivo) === TRUE) {
    logVOL("✓ ZIP válido ANTES de enviar descarga");
    logVOL("  Archivos internos: " . $zipTest->numFiles);
    $zipTest->close();
} else {
    logVOL("ERROR: ZIP CORRUPTO antes de enviar descarga");
}

// === LIMPIAR CUALQUIER OUTPUT ANTERIOR ===
if (ob_get_level()) {
    ob_end_clean();
    logVOL("✓ Buffer limpiado");
}

logVOL("Enviando headers de descarga...");

// === HEADERS PARA DESCARGA FORZADA ===
header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . $tamano);

logVOL("Headers enviados");
logVOL("Enviando archivo binario...");

// === LIMPIAR BUFFER Y ENVIAR ARCHIVO ===
flush();
readfile($rutaArchivo);

logVOL("✓ readfile() completado");
logVOL("===== FIN DESCARGA =====\n");

exit;
?>