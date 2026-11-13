<?php
declare(strict_types=1);
require __DIR__ . '/clima.php';
require __DIR__ . '/estadisticas.php';
require __DIR__ . '/comun.php';

header('Cache-Control: public, max-age=600');

$met = pronostico_met();
$smn = actual_smn();

$a = $met['actual'] ?? null;
[$iconoActual, $textoActual] = simbolo($a['simbolo'] ?? null);
$hoy = $met['dias'][0] ?? null;

$fechaHoy = new DateTimeImmutable('now', new DateTimeZone(CLIMA_TZ));
$fechaTexto = ucfirst(dia_semana($fechaHoy)) . ' ' . $fechaHoy->format('j') . ' de ' . SLUGS_MESES[(int) $fechaHoy->format('n') - 1];

// Preguntas frecuentes: respuestas calculadas con las normales y récords del SMN
$mesMasCalido = array_search(max(NORMAL_MAX), NORMAL_MAX, true);
$mesMasFrio = array_search(min(NORMAL_MIN), NORMAL_MIN, true);
$mesMasLluvia = array_search(max(NORMAL_LLUVIA), NORMAL_LLUVIA, true);
$mesRecord = array_search(max(RECORD_MAX), RECORD_MAX, true);
$mesRecordFrio = array_search(min(RECORD_MIN), RECORD_MIN, true);
$faq = [
    ['¿Cuál es el mes más caluroso en San Juan?',
     ucfirst(SLUGS_MESES[$mesMasCalido]) . ' es el mes más caluroso: la temperatura máxima promedio es de ' . dec(NORMAL_MAX[$mesMasCalido]) . '° y de noche ronda los ' . dec(NORMAL_MIN[$mesMasCalido]) . '°.'],
    ['¿Cuál es el mes más frío en San Juan?',
     ucfirst(SLUGS_MESES[$mesMasFrio]) . ' es el mes más frío: de día llega a unos ' . dec(NORMAL_MAX[$mesMasFrio]) . '° y de noche la mínima promedio es de ' . dec(NORMAL_MIN[$mesMasFrio]) . '°, con heladas frecuentes.'],
    ['¿Cuánto llueve en San Juan?',
     'Muy poco: en todo el año caen en promedio ' . dec(array_sum(NORMAL_LLUVIA), 0) . ' mm. El mes más lluvioso es ' . SLUGS_MESES[$mesMasLluvia] . ', con ' . dec(NORMAL_LLUVIA[$mesMasLluvia]) . ' mm, y en invierno casi no llueve.'],
    ['¿Cuál es la temperatura más alta registrada en San Juan?',
     'El récord de la estación San Juan Aero desde 1967 es de ' . dec(RECORD_MAX[$mesRecord]) . '°, en ' . SLUGS_MESES[$mesRecord] . '. El récord de frío es de ' . dec(RECORD_MIN[$mesRecordFrio]) . '°, en ' . SLUGS_MESES[$mesRecordFrio] . '.'],
    ['¿De dónde salen los datos de esta página?',
     'La medición actual, los promedios y los récords son del Servicio Meteorológico Nacional (estación San Juan Aero). El pronóstico es de MET Norway. Las estadísticas del último año se calculan todos los días con los datos abiertos del SMN.'],
];

$jsonld = [
    ['@context' => 'https://schema.org', '@type' => 'WebPage', 'name' => 'Clima en San Juan hoy y pronóstico extendido',
     'url' => SITIO . '/', 'inLanguage' => 'es-AR',
     'about' => ['@type' => 'City', 'name' => 'San Juan', 'containedInPlace' => ['@type' => 'AdministrativeArea', 'name' => 'San Juan, Argentina']],
     'publisher' => ['@type' => 'Organization', 'name' => 'Jaureguito Sistemas', 'url' => 'https://www.jaureguitosistemas.com/']],
    ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn($q) => [
        '@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]]], $faq)],
];
echo cabeza('Clima en San Juan hoy y pronóstico extendido a 7 días',
    'El clima en San Juan, Argentina: temperatura actual medida por el SMN, pronóstico por hora y a 7 días, y estadísticas simples del último año. Se actualiza todo el día.',
    '/', $jsonld);
?>
<body>

<section class="hero">
  <div class="top">
    <div class="brand">Clima <span>San Juan</span></div>
    <a href="<?= e(WEB_URL) ?>">Jaureguito Sistemas</a>
  </div>

  <div class="now">
    <h1 class="lugar">Clima en San Juan, Argentina</h1>
    <div class="fecha"><?= e($fechaTexto) ?></div>
    <?php if ($a): ?>
      <div class="temp"><?= t($a['temp']) ?>°</div>
      <div class="desc"><?= $iconoActual ?> <?= e($textoActual) ?></div>
      <div class="mm">Máx <?= t($hoy['max'] ?? null) ?>° · Mín <?= t($a['min_noche'] ?? $hoy['min'] ?? null) ?>°</div>
      <div class="chips">
        <span class="chip">💧 Humedad <b><?= t($a['humedad']) ?>%</b></span>
        <span class="chip">🌬️ Viento <b><?= t($a['viento']) ?> km/h <?= e($a['rumbo']) ?></b></span>
        <span class="chip">☔ Próxima hora <b><?= e($a['lluvia_1h']) ?> mm</b></span>
      </div>
    <?php else: ?>
      <div class="temp">–°</div>
      <div class="desc">No pudimos obtener el pronóstico. Probá en unos minutos.</div>
    <?php endif; ?>
  </div>
</section>

<main class="wrap">

<?php if (!empty($met['horas'])): ?>
<section class="card">
  <div class="lbl">Próximas 24 horas</div>
  <div class="horas">
    <?php foreach ($met['horas'] as $i => $h): [$ic] = simbolo($h['simbolo']); ?>
      <div class="hora<?= $i === 0 ? ' ya' : '' ?>">
        <div><?= $i === 0 ? 'Ahora' : e($h['hora']) . ' h' ?></div>
        <div class="i"><?= $ic ?></div>
        <div><b><?= t($h['temp']) ?>°</b></div>
        <div class="r"><?= $h['lluvia'] > 0 ? e($h['lluvia']) . ' mm' : '' ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($smn): ?>
<section class="card">
  <div class="lbl">Medición oficial SMN · estación <?= e($smn['estacion']) ?> · <?= e($smn['hora']) ?> h</div>
  <div class="smn">
    <div><small>Temperatura</small><span><?= t($smn['temp']) ?>°</span></div>
    <?php if ($smn['termica'] !== null): ?><div><small>Sensación térmica</small><span><?= t($smn['termica']) ?>°</span></div><?php endif; ?>
    <div><small>Humedad</small><span><?= t($smn['humedad']) ?>%</span></div>
    <div><small>Viento</small><span><?= e($smn['viento']) ?></span></div>
    <div><small>Estado</small><span><?= e($smn['estado']) ?></span></div>
    <?php if ($smn['presion'] !== ''): ?><div><small>Presión</small><span><?= e($smn['presion']) ?> hPa</span></div><?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?= publicidad() ?>

<?php if (!empty($met['dias'])):
    $tmin = min(array_column($met['dias'], 'min'));
    $tmax = max(array_column($met['dias'], 'max'));
    $span = max(1, $tmax - $tmin); ?>
<section class="card">
  <div class="lbl">Pronóstico 7 días</div>
  <?php foreach ($met['dias'] as $i => $d):
      $f = new DateTimeImmutable($d['fecha'], new DateTimeZone(CLIMA_TZ));
      [$ic, $tx] = simbolo($d['simbolo']);
      $izq = ($d['min'] - $tmin) / $span * 100;
      $ancho = max(4, ($d['max'] - $d['min']) / $span * 100); ?>
    <div class="dia">
      <div class="n"><?= $i === 0 ? 'Hoy' : e(dia_semana($f)) ?></div>
      <div class="i"><?= $ic ?></div>
      <div class="tx"><?= e($tx) ?><?php if ($d['lluvia'] > 0): ?> · <span class="r"><?= e($d['lluvia']) ?> mm</span><?php endif; ?></div>
      <div class="rango">
        <small><?= t($d['min']) ?>°</small>
        <span class="barra"><i style="left:<?= round($izq) ?>%;width:<?= round($ancho) ?>%"></i></span>
        <b><?= t($d['max']) ?>°</b>
      </div>
    </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<?= seccion_estadisticas($met) ?>

<?= seccion_ultimo_anio($met) ?>

<section class="card" id="preguntas">
  <h2 class="lbl">Preguntas frecuentes sobre el clima de San Juan</h2>
  <?php foreach ($faq as [$q, $r]): ?>
  <details class="faq"><summary><?= e($q) ?></summary><p><?= e($r) ?></p></details>
  <?php endforeach; ?>
</section>

<section class="card">
  <h2 class="lbl">El clima de San Juan mes por mes</h2>
  <p class="frase">¿Viajás o planificás algo? Mirá cómo es el clima en cada mes del año: temperatura, lluvia, récords y qué ropa llevar.</p>
  <?= enlaces_meses() ?>
</section>

<?= pie() ?>

</main>
</body>
</html>
