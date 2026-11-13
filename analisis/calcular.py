"""Análisis diario del clima de San Juan con datos abiertos del SMN.

Corre una vez por día en GitHub Actions (.github/workflows/analisis-diario.yml):
  1. Descarga el "Registro de temperatura (365 días)" del SMN (licencia CC BY 2.5 AR).
  2. Se queda con la estación SAN JUAN AERO y la suma al histórico propio
     (datos/historico_san_juan.csv), que crece día a día más allá de los 365 días.
  3. Calcula estadísticas simples con pandas y las guarda en datos/estadisticas.json,
     que lee la página (estadisticas.php).

Uso local:
  pip install pandas
  python analisis/calcular.py                 # descarga del SMN
  python analisis/calcular.py archivo.zip     # usa un archivo ya descargado
"""
from __future__ import annotations

import io
import json
import sys
import urllib.request
import zipfile
from datetime import datetime, timezone
from pathlib import Path

import pandas as pd

URL = "https://ssl.smn.gob.ar/dpd/zipopendata.php?dato=regtemp"
ESTACION = "SAN JUAN AERO"
RAIZ = Path(__file__).resolve().parent.parent
HISTORICO = RAIZ / "datos" / "historico_san_juan.csv"
SALIDA = RAIZ / "datos" / "estadisticas.json"

# Normales 1991-2020 de San Juan Aero (SMN), las mismas que estadisticas.php
NORMAL_MAX = [35.1, 33.3, 30.5, 25.3, 20.5, 17.3, 16.7, 20.3, 23.8, 27.9, 31.6, 34.4]
NORMAL_MIN = [19.8, 18.4, 16.1, 10.9, 6.0, 1.5, 0.3, 2.7, 6.6, 11.3, 15.0, 18.0]
MESES = ["enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto",
         "septiembre", "octubre", "noviembre", "diciembre"]


def descargar(origen: str | None) -> str:
    if origen:
        datos = Path(origen).read_bytes()
    else:
        pedido = urllib.request.Request(URL, headers={"User-Agent": "clima.jaureguitosistemas.com"})
        with urllib.request.urlopen(pedido, timeout=60) as r:
            datos = r.read()
    with zipfile.ZipFile(io.BytesIO(datos)) as z:
        return z.read(z.namelist()[0]).decode("latin-1")


def leer_registro(texto: str) -> pd.DataFrame:
    """Formato de ancho fijo: FECHA(ddmmaaaa) TMAX TMIN NOMBRE."""
    filas = []
    for linea in texto.splitlines():
        partes = linea.split(None, 3)
        if len(partes) < 4 or not partes[0].isdigit() or partes[3].strip() != ESTACION:
            continue
        try:
            fecha = datetime.strptime(partes[0], "%d%m%Y").date()
        except ValueError:
            continue
        filas.append({"fecha": fecha, "tmax": pd.to_numeric(partes[1], errors="coerce"),
                      "tmin": pd.to_numeric(partes[2], errors="coerce")})
    if not filas:
        raise SystemExit(f"No se encontraron datos de {ESTACION}: ¿cambió el formato del SMN?")
    return pd.DataFrame(filas)


def actualizar_historico(nuevos: pd.DataFrame) -> pd.DataFrame:
    if HISTORICO.exists():
        viejo = pd.read_csv(HISTORICO, parse_dates=["fecha"])
        viejo["fecha"] = viejo["fecha"].dt.date
        todo = pd.concat([viejo, nuevos])
    else:
        todo = nuevos
    # si el SMN corrige un día, gana el dato más nuevo
    todo = todo.drop_duplicates("fecha", keep="last").sort_values("fecha")
    HISTORICO.parent.mkdir(parents=True, exist_ok=True)
    todo.to_csv(HISTORICO, index=False, float_format="%.1f")
    return todo


def fecha_texto(f) -> str:
    return f"{f.day} de {MESES[f.month - 1]} de {f.year}"


def racha_maxima(serie: pd.Series) -> int:
    """Mayor cantidad de días seguidos en True."""
    grupos = (serie != serie.shift()).cumsum()
    return int(serie.groupby(grupos).sum().max() or 0)


def calcular(df: pd.DataFrame) -> dict:
    df = df.copy()
    df["fecha"] = pd.to_datetime(df["fecha"])
    df = df.set_index("fecha").asfreq("D")  # días faltantes quedan como NaN
    df["mes"] = df.index.month - 1
    df["normal_max"] = df["mes"].map(lambda m: NORMAL_MAX[m])
    df["normal_min"] = df["mes"].map(lambda m: NORMAL_MIN[m])
    df["anom_max"] = df["tmax"] - df["normal_max"]

    ultimo = df["tmax"].last_valid_index()
    anio = df.loc[ultimo - pd.Timedelta(days=364):ultimo]

    # 1. Último día medido y hace cuánto no hacía tanto calor (o frío)
    ayer_max = float(df.at[ultimo, "tmax"])
    anteriores = df.loc[:ultimo - pd.Timedelta(days=1), "tmax"].dropna()
    mas_calidos = anteriores[anteriores >= ayer_max]
    if len(mas_calidos):
        desde = mas_calidos.index[-1]
        comparacion = {"tipo": "desde", "fecha": fecha_texto(desde), "valor": float(mas_calidos.iloc[-1]),
                       "dias": int((ultimo - desde).days)}
    else:
        comparacion = {"tipo": "maximo", "dias": int(len(anteriores))}
    ultimo_dia = {"fecha": fecha_texto(ultimo), "tmax": ayer_max,
                  "tmin": None if pd.isna(df.at[ultimo, "tmin"]) else float(df.at[ultimo, "tmin"]),
                  "anom_max": round(float(df.at[ultimo, "anom_max"]), 1), "comparacion": comparacion}

    # 2. Últimos 30 días, para el gráfico
    ult30 = df.loc[ultimo - pd.Timedelta(days=29):ultimo]
    dias30 = [{"f": i.strftime("%d/%m"), "max": None if pd.isna(r.tmax) else float(r.tmax),
               "min": None if pd.isna(r.tmin) else float(r.tmin),
               "nmax": r.normal_max, "nmin": r.normal_min} for i, r in ult30.iterrows()]
    anom30 = float(ult30["anom_max"].mean())

    # 3. Cada mes del último año contra lo normal
    meses = []
    por_mes = anio.groupby(anio.index.to_period("M"))
    for periodo, g in por_mes:
        if g["tmax"].count() < 20:  # mes incompleto (el primero o el actual)
            continue
        m = periodo.month - 1
        prom_max = float(g["tmax"].mean())
        meses.append({"mes": MESES[m], "anio": periodo.year, "prom_max": round(prom_max, 1),
                      "normal_max": NORMAL_MAX[m], "anom": round(prom_max - NORMAL_MAX[m], 1),
                      "dias": int(g["tmax"].count())})

    # 4. Conteos del último año
    conteos = {
        "calor35": int((anio["tmax"] >= 35).sum()),
        "calor40": int((anio["tmax"] >= 40).sum()),
        "noches20": int((anio["tmin"] >= 20).sum()),
        "heladas": int((anio["tmin"] <= 0).sum()),
        "racha35": racha_maxima(anio["tmax"] >= 35),
        "dias_con_dato": int(anio["tmax"].count()),
    }
    i_max, i_min = anio["tmax"].idxmax(), anio["tmin"].idxmin()
    extremos = {"max": float(anio.at[i_max, "tmax"]), "max_fecha": fecha_texto(i_max),
                "min": float(anio.at[i_min, "tmin"]), "min_fecha": fecha_texto(i_min)}

    return {
        "generado": datetime.now(timezone.utc).isoformat(timespec="minutes"),
        "desde": fecha_texto(anio.index[0]), "hasta": fecha_texto(ultimo),
        "ultimo_dia": ultimo_dia,
        "ultimos30": {"dias": dias30, "anom_max": round(anom30, 1)},
        "meses": meses,
        "conteos": conteos,
        "extremos": extremos,
        "fuente": "SMN, Registro de temperatura (365 días), estación San Juan Aero. CC BY 2.5 AR.",
    }


def main() -> None:
    texto = descargar(sys.argv[1] if len(sys.argv) > 1 else None)
    historico = actualizar_historico(leer_registro(texto))
    resultado = calcular(historico)
    SALIDA.write_text(json.dumps(resultado, ensure_ascii=False, indent=1), encoding="utf-8")
    print(f"OK: {len(historico)} días en el histórico, último {resultado['hasta']}")


if __name__ == "__main__":
    main()
