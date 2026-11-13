<?php
// Verifica que el hosting llegue a las dos fuentes. Borralo después de probar.
declare(strict_types=1);
require __DIR__ . '/clima.php';
header('Content-Type: text/plain; charset=utf-8');

echo "PHP " . PHP_VERSION . "\n";
echo "curl: " . (function_exists('curl_init') ? 'OK' : 'FALTA') . "\n";
echo "zip: " . (class_exists('ZipArchive') ? 'OK' : 'FALTA') . "\n";
echo "cache escribible: " . (is_writable(CLIMA_CACHE) ? 'OK' : 'NO (dar permisos 755)') . "\n\n";

$met = http_get(sprintf('https://api.met.no/weatherapi/locationforecast/2.0/compact?lat=%.3f&lon=%.3f', CLIMA_LAT, CLIMA_LON));
echo "MET Norway: " . ($met ? 'OK (' . strlen($met) . ' bytes)' : 'ERROR') . "\n";

$zip = http_get(SMN_URL, 15);
echo "SMN zip: " . ($zip ? 'OK (' . strlen($zip) . ' bytes)' : 'ERROR') . "\n";
if ($zip && class_exists('ZipArchive')) {
    $txt = leer_zip($zip);
    if ($txt !== null) {
        if (!mb_check_encoding($txt, 'UTF-8')) {
            $txt = mb_convert_encoding($txt, 'UTF-8', 'ISO-8859-1');
        }
        echo "\nPrimeras líneas del SMN:\n";
        echo implode("\n", array_slice(preg_split('/\r?\n/', $txt), 0, 4)) . "\n";
        echo "\nLínea de San Juan:\n";
        foreach (preg_split('/\r?\n/', $txt) as $l) {
            if (stripos($l, 'San Juan') !== false) echo $l . "\n";
        }
        echo "\nResultado parseado:\n";
        var_export(parsear_smn($txt));
    }
}
