/* Gráficos interactivos de clima.jaureguitosistemas.com
 * Lee los datos de <script id="clima-datos" type="application/json"> (los genera estadisticas.php).
 * Sin librerías: SVG + JS. Si JS falla, quedan las tarjetas y el gráfico anual del servidor. */
(function () {
  'use strict';
  var nodo = document.getElementById('clima-datos');
  if (!nodo) return;
  var D;
  try { D = JSON.parse(nodo.textContent); } catch (e) { return; }

  var MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
  var NS = 'http://www.w3.org/2000/svg';
  var movRed = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function r(v) { return Math.round(v); }
  function dec(v) { return (Math.round(v * 10) / 10).toString().replace('.', ','); }
  function el(tag, attrs, padre) {
    var e = document.createElementNS(NS, tag);
    for (var k in attrs) e.setAttribute(k, attrs[k]);
    if (padre) padre.appendChild(e);
    return e;
  }
  function difTexto(d) {
    var a = Math.abs(r(d));
    if (a === 0) return 'igual que lo normal';
    return a + '° ' + (d > 0 ? 'más' : 'menos') + ' que lo normal';
  }
  /* Cuenta animada de un número */
  function contar(nodo, hasta, sufijo) {
    if (movRed) { nodo.textContent = r(hasta) + sufijo; return; }
    var desde = parseFloat(nodo.dataset.v || '0'), t0 = null;
    nodo.dataset.v = hasta;
    function paso(t) {
      if (!t0) t0 = t;
      var p = Math.min(1, (t - t0) / 600), e = 1 - Math.pow(1 - p, 3);
      nodo.textContent = r(desde + (hasta - desde) * e) + sufijo;
      if (p < 1) requestAnimationFrame(paso);
    }
    requestAnimationFrame(paso);
  }
  /* Anima solo cuando el bloque entra en pantalla */
  function alVer(nodo, fn) {
    if (!('IntersectionObserver' in window) || movRed) { fn(); return; }
    var io = new IntersectionObserver(function (ents) {
      if (ents[0].isIntersecting) { io.disconnect(); fn(); }
    }, { threshold: 0.35 });
    io.observe(nodo);
  }

  /* ---------- 1. Termómetro: ¿qué tan caluroso está hoy? ---------- */
  (function termometro() {
    var caja = document.getElementById('w-hoy');
    if (!caja || D.hoy.max === null) return;
    var m = D.mes, nmax = D.normalMax[m], rec = D.recordMax[m];
    var lo = Math.floor(nmax - 12), hi = Math.ceil(rec + 1);
    var pct = function (t) { return Math.max(0, Math.min(100, (t - lo) / (hi - lo) * 100)); };
    var zonas = [
      { de: lo, a: nmax - 3, txt: 'Fresco', cls: 'z-fresco' },
      { de: nmax - 3, a: nmax + 3, txt: 'Normal', cls: 'z-normal' },
      { de: nmax + 3, a: rec - 3, txt: 'Caluroso', cls: 'z-calor' },
      { de: rec - 3, a: hi, txt: 'Récord', cls: 'z-record' }
    ];
    var barra = caja.querySelector('.termo-barra'), marcas = caja.querySelector('.termo-marcas');
    zonas.forEach(function (z) {
      var s = document.createElement('span');
      s.className = 'termo-zona ' + z.cls;
      s.style.left = pct(z.de) + '%';
      s.style.width = (pct(z.a) - pct(z.de)) + '%';
      s.innerHTML = '<em>' + z.txt + '</em>';
      barra.appendChild(s);
    });
    [[nmax, 'Normal ' + r(nmax) + '°'], [rec, 'Récord ' + dec(rec) + '°']].forEach(function (x) {
      var s = document.createElement('span');
      var p = pct(x[0]);
      s.style.left = p + '%';
      if (p > 88) s.className = 'a-der';
      s.textContent = x[1];
      marcas.appendChild(s);
    });
    var hoy = D.hoy.max, d = hoy - nmax;
    var zona = zonas.filter(function (z) { return hoy >= z.de && hoy < z.a; })[0] || zonas[zonas.length - 1];
    var aguja = caja.querySelector('.termo-aguja'), valor = caja.querySelector('.termo-valor');
    aguja.style.left = '0%';
    alVer(caja, function () {
      aguja.style.left = pct(hoy) + '%';
      contar(valor, hoy, '°');
    });
    var frase;
    if (zona.cls === 'z-record') frase = 'Hoy es un día excepcional: la máxima de ' + r(hoy) + '° está muy cerca del récord de ' + MESES[m] + ' (' + dec(rec) + '°).';
    else if (zona.cls === 'z-calor') frase = 'Hoy hace bastante más calor que un día típico de ' + MESES[m] + ': ' + difTexto(d) + '.';
    else if (zona.cls === 'z-fresco') frase = 'Hoy está más fresco que un día típico de ' + MESES[m] + ': ' + difTexto(d) + '.';
    else frase = 'Hoy es un día normal para ' + MESES[m] + ' (' + difTexto(d) + ').';
    caja.querySelector('.termo-frase').textContent = frase;
  })();

  /* ---------- 3. Explorador de meses ---------- */
  (function explorador() {
    var caja = document.getElementById('w-mes');
    if (!caja) return;
    var chips = caja.querySelector('.mes-chips');
    function sensDia(t) {
      return t >= 32 ? 'mucho calor' : t >= 27 ? 'calor' : t >= 21 ? 'temperatura agradable' : t >= 15 ? 'fresco' : 'frío';
    }
    function sensNoche(t) {
      return t >= 18 ? 'noches cálidas' : t >= 10 ? 'noches templadas' : t >= 3 ? 'noches frías' : 'noches muy frías, con posibles heladas';
    }
    function ropa(dia, noche) {
      if (dia >= 30) return 'Ropa liviana, gorra, protector solar y mucha agua.' + (dia - noche >= 15 ? ' A la noche refresca: un buzo liviano.' : '');
      if (dia >= 22) return 'Remera durante el día y un abrigo liviano para la noche.';
      if (dia >= 15) return 'Abrigo liviano de día y campera para la noche.';
      return 'Campera abrigada, sobre todo a la mañana temprano y a la noche.';
    }
    function lluviaTexto(mm) {
      return mm < 3 ? 'Casi no llueve en todo el mes.' : mm < 8 ? 'Llueve muy poco: algún día aislado.' : mm < 15 ? 'Llueve poco, en general con tormentas de verano.' : 'Es la época más lluviosa del año, aunque igual llueve poco.';
    }
    function gotas(mm) {
      var g = Math.max(1, Math.round(mm / 4)), s = '';
      for (var i = 0; i < 6; i++) s += '<i class="' + (i < g ? 'on' : '') + '"></i>';
      return s;
    }
    var ficha = caja.querySelector('.mes-ficha');
    function mostrar(m) {
      [].forEach.call(chips.children, function (c, k) {
        c.classList.toggle('activo', k === m);
        c.setAttribute('aria-pressed', k === m ? 'true' : 'false');
      });
      // marca el mes elegido en las barras de lluvia
      document.querySelectorAll('[data-mes-col]').forEach(function (n) {
        n.classList.toggle('elegido', +n.getAttribute('data-mes-col') === m);
      });
      var dia = D.normalMax[m], noche = D.normalMin[m], mm = D.normalLluvia[m];
      ficha.querySelector('.f-titulo').textContent = 'Así es ' + MESES[m] + ' en San Juan' + (m === D.mes ? ' (este mes)' : '');
      contar(ficha.querySelector('.f-dia'), dia, '°');
      contar(ficha.querySelector('.f-noche'), noche, '°');
      ficha.querySelector('.f-lluvia').textContent = dec(mm) + ' mm';
      ficha.querySelector('.f-gotas').innerHTML = gotas(mm);
      ficha.querySelector('.f-rec-max').textContent = dec(D.recordMax[m]) + '°';
      ficha.querySelector('.f-rec-min').textContent = dec(D.recordMin[m]) + '°';
      ficha.querySelector('.f-texto').textContent =
        'De día hace ' + sensDia(dia) + ' (unos ' + r(dia) + '°) y hay ' + sensNoche(noche) + ' (unos ' + r(noche) + '°). ' +
        (dia - noche >= 15 ? 'Entre el día y la noche cambia mucho: ' + r(dia - noche) + '° de diferencia. ' : '') +
        lluviaTexto(mm);
      ficha.querySelector('.f-ropa').textContent = ropa(dia, noche);
      ficha.classList.remove('entra'); void ficha.offsetWidth; ficha.classList.add('entra');
    }
    MESES.forEach(function (nom, k) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'chip-mes';
      b.textContent = nom.slice(0, 3);
      b.setAttribute('aria-label', nom);
      b.addEventListener('click', function () { mostrar(k); });
      chips.appendChild(b);
    });
    document.querySelectorAll('[data-mes-col]').forEach(function (n) {
      n.addEventListener('click', function () { mostrar(+n.getAttribute('data-mes-col')); });
    });
    mostrar(D.mes);
  })();
})();
