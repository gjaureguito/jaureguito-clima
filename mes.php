<?php
/**
 * Página "Clima en San Juan en <mes>" (URLs /enero … /diciembre, ver .htaccess).
 * Pensada para búsquedas como "clima en San Juan en julio" o "cuánto llueve en San Juan en febrero".
 */
declare(strict_types=1);
require __DIR__ . '/clima.php';
require __DIR__ . '/estadisticas.php';
require __DIR__ . '/comun.php';

$slug = strtolower((string) ($_GET['mes'] ?? ''));
$m = array_search($slug, SLUGS_MESES, true);
if ($m === false) {
    http_response_code(404);
    header('Location: /', true, 302);
    exit;
}
header('Cache-Control: public, max-age=3600');

$Mes = ucfirst($slug);
$res = resumen_mes($m);

// Ranking simple: qué lugar ocupa el mes entre los más calurosos
$orden = NORMAL_MAX;
arsort($orden);
$puesto = array_search($m, array_keys($orden), true) + 1;
$ordinales = [1 => 'el mes más caluroso', 2 => 'el segundo mes más caluroso', 3 => 'el tercer mes más caluroso',
    10 => 'el tercer mes más frío', 11 => 'el segundo mes más frío', 12 => 'el mes más frío'];
$rankTexto = $ordinales[$puesto] ?? ('el puesto ' . $puesto . ' de 12 en temperatura');

// Cómo fue este mes el año pasado (datos medidos, del análisis diario)
$medido = null;
$archivo = __DIR__ . '/datos/estadisticas.json';
if (is_file($archivo)) {
    $A = json_decode((string) file_get_contents($archivo), true);
    foreach (array_reverse($A['meses'] ?? []) as $x) {
        if ($x['mes'] === $slug) { $medido = $x; break; }
    }
}

$hoy = new DateTimeImmutable('now', new DateTimeZone(CLIMA_TZ));
$esEsteMes = (int) $hoy->format('n') - 1 === $m;

$preguntas = [
    ['¿Hace calor en San Juan en ' . $slug . '?', $res['texto']],
    ['¿Qué ropa llevar a San Juan en ' . $slug . '?', $res['ropa']],
    ['¿Cuál fue la temperatura récord en ' . $slug . ' en San Juan?',
     'Desde 1967, la máxima más alta registrada en ' . $slug . ' en la estación San Juan Aero fue de ' . dec(RECORD_MAX[$m]) . '° y la mínima más baja, de ' . dec(RECORD_MIN[$m]) . '°.'],
];

$titulo = 'Clima en San Juan en ' . $slug . ': temperatura, lluvia y récords';
$desc = 'Cómo es el clima en San Juan, Argentina, en ' . $slug . ': máxima promedio de ' . dec(NORMAL_MAX[$m]) . '°, mínima de ' . dec(NORMAL_MIN[$m])
    . '°, ' . dec(NORMAL_LLUVIA[$m]) . ' mm de lluvia, récords y qué ropa llevar. Datos del SMN.';
$jsonld = [
    ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Clima en San Juan', 'item' => SITIO . '/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $Mes, 'item' => SITIO . '/' . $slug]]],
    ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn($q) => [
        '@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]]], $preguntas)],
];
echo cabeza($titulo, $desc, '/' . $slug, $jsonld);
?>
<body>

<section class="hero hero-chico">
  <div class="top">
    <a class="brand" href="/">Clima <span>San Juan</span></a>
    <a href="<?= e(WEB_URL) ?>">Jaureguito Sistemas</a>
  </div>
  <div class="now">
    <nav class="migas" aria-label="Ruta"><a href="/">Clima en San Juan</a> › <?= e($Mes) ?></nav>
    <h1 class="titulo-mes">Clima en San Juan en <?= e($slug) ?></h1>
    <p class="bajada">Temperatura, lluvia, récords y qué ropa llevar, con datos del Servicio Meteorológico Nacional.</p>
  </div>
</section>

<main class="wrap">

<section class="card">
  <div class="lbl">Así es <?= e($slug) ?> en San Juan</div>
  <p class="destacado"><?= e($res['texto']) ?> Es <?= e($rankTexto) ?> del año.</p>
  <div class="f-grid">
    <div><small>☀️ De día</small><b><?= dec(NORMAL_MAX[$m]) ?>°</b></div>
    <div><small>🌙 De noche</small><b><?= dec(NORMAL_MIN[$m]) ?>°</b></div>
    <div><small>🌧️ Lluvia en el mes</small><b><?= dec(NORMAL_LLUVIA[$m]) ?> mm</b></div>
    <div><small>🔥 Récord de calor</small><b><?= dec(RECORD_MAX[$m]) ?>°</b></div>
    <div><small>❄️ Récord de frío</small><b><?= dec(RECORD_MIN[$m]) ?>°</b></div>
  </div>
  <p class="f-ropa-wrap">👕 <?= e($res['ropa']) ?></p>
  <?php if ($esEsteMes): ?>
  <p class="frase"><a class="btn wa" href="/">Ver el pronóstico de hoy</a></p>
  <?php endif; ?>
</section>

<?php if ($medido): $an = (float) $medido['anom']; ?>
<section class="card">
  <div class="lbl"><?= e($Mes) ?> de <?= (int) $medido['anio'] ?>, medido</div>
  <p class="frase">El último <?= e($slug) ?> la máxima promedio fue de <b><?= dec((float) $medido['prom_max']) ?>°</b>:
    <?= abs($an) < 1 ? 'normal para la época' : '<b class="' . ($an > 0 ? 't-calor' : 't-frio') . '">' . dec(abs($an)) . '° ' . ($an > 0 ? 'más caluroso' : 'más fresco') . '</b> que un ' . e($slug) . ' típico' ?>.
    <?php if ((int) $medido['calor35'] > 0): ?>Hubo <?= plural((int) $medido['calor35'], 'día', 'días') ?> con 35° o más.<?php endif; ?>
    <?php if ((int) $medido['heladas'] > 0): ?>Hubo <?= plural((int) $medido['heladas'], 'helada', 'heladas') ?>.<?php endif; ?></p>
  <p class="ayuda">Datos medidos en la estación San Juan Aero (SMN), comparados con el promedio 1991-2020.</p>
</section>
<?php endif; ?>

<?= publicidad() ?>

<section class="card">
  <h2 class="lbl">Preguntas frecuentes sobre <?= e($slug) ?></h2>
  <?php foreach ($preguntas as [$q, $r]): ?>
  <details class="faq"><summary><?= e($q) ?></summary><p><?= e($r) ?></p></details>
  <?php endforeach; ?>
</section>

<section class="card">
  <h2 class="lbl">Otros meses</h2>
  <?= enlaces_meses($m) ?>
  <p class="frase"><a href="/">← Volver al clima de hoy en San Juan</a></p>
</section>

<?= pie() ?>

</main>
</body>
</html>
