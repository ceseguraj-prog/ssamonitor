-- ============================================================================
-- Concepto 267 · ejercicio 2026 · base `catalogos`
-- Tablas: federal, homologados, regularizados, formalizados,
--         formalizados2016, formalizados2015
--
-- Cada registro guarda hasta 50 conceptos en grupos de 3 columnas:
--   TR{n}TCP varchar(5) = concepto  (3 digitos + 2 de subclave: 26700, 267CG…)
--   TR{n}IM  double     = importe
--   TR{n}AQ  varchar(6) = año/quincena
-- Por eso el concepto 267 se busca como TR{n}TCP LIKE '267%' sobre los 28
-- slots, y se desunifican (unpivot) con UNION ALL.
--
-- ANIO = '2026' aprovecha el indice ANIO que existe en las 6 tablas.
-- Version recortada: en 2026 MAX(TTR)=28 y se verifico que los slots
-- 29..50 estan vacios, asi que revisar mas seria trabajo perdido.
-- Si se reutiliza para otro año, revalidar con MAX(TTR).
-- ============================================================================

SELECT *
  FROM (
    SELECT 'federal' AS tabla, 1  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR1TCP AS concepto, TR1IM AS importe, TR1AQ AS aq
      FROM `federal` WHERE ANIO = '2026' AND TR1TCP LIKE '267%'
  UNION ALL
    SELECT 'federal' AS tabla, 2  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR2TCP AS concepto, TR2IM AS importe, TR2AQ AS aq
      FROM `federal` WHERE ANIO = '2026' AND TR2TCP LIKE '267%'
  UNION ALL
    SELECT 'federal' AS tabla, 3  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR3TCP AS concepto, TR3IM AS importe, TR3AQ AS aq
      FROM `federal` WHERE ANIO = '2026' AND TR3TCP LIKE '267%'
  UNION ALL
    SELECT 'federal' AS tabla, 4  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR4TCP AS concepto, TR4IM AS importe, TR4AQ AS aq
      FROM `federal` WHERE ANIO = '2026' AND TR4TCP LIKE '267%'
  UNION ALL
    SELECT 'federal' AS tabla, 5  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR5TCP AS concepto, TR5IM AS importe, TR5AQ AS aq
      FROM `federal` WHERE ANIO = '2026' AND TR5TCP LIKE '267%'
  UNION ALL
    SELECT 'federal' AS tabla, 6  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR6TCP AS concepto, TR6IM AS importe, TR6AQ AS aq
      FROM `federal` WHERE ANIO = '2026' AND TR6TCP LIKE '267%'
  UNION ALL
    SELECT 'federal' AS tabla, 7  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR7TCP AS concepto, TR7IM AS importe, TR7AQ AS aq
      FROM `federal` WHERE ANIO = '2026' AND TR7TCP LIKE '267%'
  UNION ALL
    SELECT 'federal' AS tabla, 8  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR8TCP AS concepto, TR8IM AS importe, TR8AQ AS aq
      FROM `federal` WHERE ANIO = '2026' AND TR8TCP LIKE '267%'
  UNION ALL
    SELECT 'federal' AS tabla, 9  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR9TCP AS concepto, TR9IM AS importe, TR9AQ AS aq
      FROM `federal` WHERE ANIO = '2026' AND TR9TCP LIKE '267%'
  UNION ALL
    SELECT 'federal' AS tabla, 10 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR10TCP AS concepto, TR10IM AS importe, TR10AQ AS aq
      FROM `federal` WHERE ANIO = '2026' AND TR10TCP LIKE '267%'
  UNION ALL
    SELECT 'federal' AS tabla, 11 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR11TCP AS concepto, TR11IM AS importe, TR11AQ AS aq
      FROM `federal` WHERE ANIO = '2026' AND TR11TCP LIKE '267%'
  UNION ALL
    SELECT 'federal' AS tabla, 12 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR12TCP AS concepto, TR12IM AS importe, TR12AQ AS aq
      FROM `federal` WHERE ANIO = '2026' AND TR12TCP LIKE '267%'
  UNION ALL
    SELECT 'federal' AS tabla, 13 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR13TCP AS concepto, TR13IM AS importe, TR13AQ AS aq
      FROM `federal` WHERE ANIO = '2026' AND TR13TCP LIKE '267%'
  UNION ALL
    SELECT 'federal' AS tabla, 14 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR14TCP AS concepto, TR14IM AS importe, TR14AQ AS aq
      FROM `federal` WHERE ANIO = '2026' AND TR14TCP LIKE '267%'
  UNION ALL
    SELECT 'federal' AS tabla, 15 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR15TCP AS concepto, TR15IM AS importe, TR15AQ AS aq
      FROM `federal` WHERE ANIO = '2026' AND TR15TCP LIKE '267%'
  UNION ALL
    SELECT 'federal' AS tabla, 16 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR16TCP AS concepto, TR16IM AS importe, TR16AQ AS aq
      FROM `federal` WHERE ANIO = '2026' AND TR16TCP LIKE '267%'
  UNION ALL
    SELECT 'federal' AS tabla, 17 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR17TCP AS concepto, TR17IM AS importe, TR17AQ AS aq
      FROM `federal` WHERE ANIO = '2026' AND TR17TCP LIKE '267%'
  UNION ALL
    SELECT 'federal' AS tabla, 18 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR18TCP AS concepto, TR18IM AS importe, TR18AQ AS aq
      FROM `federal` WHERE ANIO = '2026' AND TR18TCP LIKE '267%'
  UNION ALL
    SELECT 'federal' AS tabla, 19 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR19TCP AS concepto, TR19IM AS importe, TR19AQ AS aq
      FROM `federal` WHERE ANIO = '2026' AND TR19TCP LIKE '267%'
  UNION ALL
    SELECT 'federal' AS tabla, 20 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR20TCP AS concepto, TR20IM AS importe, TR20AQ AS aq
      FROM `federal` WHERE ANIO = '2026' AND TR20TCP LIKE '267%'
  UNION ALL
    SELECT 'federal' AS tabla, 21 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR21TCP AS concepto, TR21IM AS importe, TR21AQ AS aq
      FROM `federal` WHERE ANIO = '2026' AND TR21TCP LIKE '267%'
  UNION ALL
    SELECT 'federal' AS tabla, 22 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR22TCP AS concepto, TR22IM AS importe, TR22AQ AS aq
      FROM `federal` WHERE ANIO = '2026' AND TR22TCP LIKE '267%'
  UNION ALL
    SELECT 'federal' AS tabla, 23 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR23TCP AS concepto, TR23IM AS importe, TR23AQ AS aq
      FROM `federal` WHERE ANIO = '2026' AND TR23TCP LIKE '267%'
  UNION ALL
    SELECT 'federal' AS tabla, 24 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR24TCP AS concepto, TR24IM AS importe, TR24AQ AS aq
      FROM `federal` WHERE ANIO = '2026' AND TR24TCP LIKE '267%'
  UNION ALL
    SELECT 'federal' AS tabla, 25 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR25TCP AS concepto, TR25IM AS importe, TR25AQ AS aq
      FROM `federal` WHERE ANIO = '2026' AND TR25TCP LIKE '267%'
  UNION ALL
    SELECT 'federal' AS tabla, 26 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR26TCP AS concepto, TR26IM AS importe, TR26AQ AS aq
      FROM `federal` WHERE ANIO = '2026' AND TR26TCP LIKE '267%'
  UNION ALL
    SELECT 'federal' AS tabla, 27 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR27TCP AS concepto, TR27IM AS importe, TR27AQ AS aq
      FROM `federal` WHERE ANIO = '2026' AND TR27TCP LIKE '267%'
  UNION ALL
    SELECT 'federal' AS tabla, 28 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR28TCP AS concepto, TR28IM AS importe, TR28AQ AS aq
      FROM `federal` WHERE ANIO = '2026' AND TR28TCP LIKE '267%'
  UNION ALL
    SELECT 'homologados' AS tabla, 1  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR1TCP AS concepto, TR1IM AS importe, TR1AQ AS aq
      FROM `homologados` WHERE ANIO = '2026' AND TR1TCP LIKE '267%'
  UNION ALL
    SELECT 'homologados' AS tabla, 2  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR2TCP AS concepto, TR2IM AS importe, TR2AQ AS aq
      FROM `homologados` WHERE ANIO = '2026' AND TR2TCP LIKE '267%'
  UNION ALL
    SELECT 'homologados' AS tabla, 3  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR3TCP AS concepto, TR3IM AS importe, TR3AQ AS aq
      FROM `homologados` WHERE ANIO = '2026' AND TR3TCP LIKE '267%'
  UNION ALL
    SELECT 'homologados' AS tabla, 4  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR4TCP AS concepto, TR4IM AS importe, TR4AQ AS aq
      FROM `homologados` WHERE ANIO = '2026' AND TR4TCP LIKE '267%'
  UNION ALL
    SELECT 'homologados' AS tabla, 5  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR5TCP AS concepto, TR5IM AS importe, TR5AQ AS aq
      FROM `homologados` WHERE ANIO = '2026' AND TR5TCP LIKE '267%'
  UNION ALL
    SELECT 'homologados' AS tabla, 6  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR6TCP AS concepto, TR6IM AS importe, TR6AQ AS aq
      FROM `homologados` WHERE ANIO = '2026' AND TR6TCP LIKE '267%'
  UNION ALL
    SELECT 'homologados' AS tabla, 7  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR7TCP AS concepto, TR7IM AS importe, TR7AQ AS aq
      FROM `homologados` WHERE ANIO = '2026' AND TR7TCP LIKE '267%'
  UNION ALL
    SELECT 'homologados' AS tabla, 8  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR8TCP AS concepto, TR8IM AS importe, TR8AQ AS aq
      FROM `homologados` WHERE ANIO = '2026' AND TR8TCP LIKE '267%'
  UNION ALL
    SELECT 'homologados' AS tabla, 9  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR9TCP AS concepto, TR9IM AS importe, TR9AQ AS aq
      FROM `homologados` WHERE ANIO = '2026' AND TR9TCP LIKE '267%'
  UNION ALL
    SELECT 'homologados' AS tabla, 10 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR10TCP AS concepto, TR10IM AS importe, TR10AQ AS aq
      FROM `homologados` WHERE ANIO = '2026' AND TR10TCP LIKE '267%'
  UNION ALL
    SELECT 'homologados' AS tabla, 11 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR11TCP AS concepto, TR11IM AS importe, TR11AQ AS aq
      FROM `homologados` WHERE ANIO = '2026' AND TR11TCP LIKE '267%'
  UNION ALL
    SELECT 'homologados' AS tabla, 12 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR12TCP AS concepto, TR12IM AS importe, TR12AQ AS aq
      FROM `homologados` WHERE ANIO = '2026' AND TR12TCP LIKE '267%'
  UNION ALL
    SELECT 'homologados' AS tabla, 13 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR13TCP AS concepto, TR13IM AS importe, TR13AQ AS aq
      FROM `homologados` WHERE ANIO = '2026' AND TR13TCP LIKE '267%'
  UNION ALL
    SELECT 'homologados' AS tabla, 14 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR14TCP AS concepto, TR14IM AS importe, TR14AQ AS aq
      FROM `homologados` WHERE ANIO = '2026' AND TR14TCP LIKE '267%'
  UNION ALL
    SELECT 'homologados' AS tabla, 15 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR15TCP AS concepto, TR15IM AS importe, TR15AQ AS aq
      FROM `homologados` WHERE ANIO = '2026' AND TR15TCP LIKE '267%'
  UNION ALL
    SELECT 'homologados' AS tabla, 16 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR16TCP AS concepto, TR16IM AS importe, TR16AQ AS aq
      FROM `homologados` WHERE ANIO = '2026' AND TR16TCP LIKE '267%'
  UNION ALL
    SELECT 'homologados' AS tabla, 17 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR17TCP AS concepto, TR17IM AS importe, TR17AQ AS aq
      FROM `homologados` WHERE ANIO = '2026' AND TR17TCP LIKE '267%'
  UNION ALL
    SELECT 'homologados' AS tabla, 18 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR18TCP AS concepto, TR18IM AS importe, TR18AQ AS aq
      FROM `homologados` WHERE ANIO = '2026' AND TR18TCP LIKE '267%'
  UNION ALL
    SELECT 'homologados' AS tabla, 19 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR19TCP AS concepto, TR19IM AS importe, TR19AQ AS aq
      FROM `homologados` WHERE ANIO = '2026' AND TR19TCP LIKE '267%'
  UNION ALL
    SELECT 'homologados' AS tabla, 20 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR20TCP AS concepto, TR20IM AS importe, TR20AQ AS aq
      FROM `homologados` WHERE ANIO = '2026' AND TR20TCP LIKE '267%'
  UNION ALL
    SELECT 'homologados' AS tabla, 21 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR21TCP AS concepto, TR21IM AS importe, TR21AQ AS aq
      FROM `homologados` WHERE ANIO = '2026' AND TR21TCP LIKE '267%'
  UNION ALL
    SELECT 'homologados' AS tabla, 22 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR22TCP AS concepto, TR22IM AS importe, TR22AQ AS aq
      FROM `homologados` WHERE ANIO = '2026' AND TR22TCP LIKE '267%'
  UNION ALL
    SELECT 'homologados' AS tabla, 23 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR23TCP AS concepto, TR23IM AS importe, TR23AQ AS aq
      FROM `homologados` WHERE ANIO = '2026' AND TR23TCP LIKE '267%'
  UNION ALL
    SELECT 'homologados' AS tabla, 24 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR24TCP AS concepto, TR24IM AS importe, TR24AQ AS aq
      FROM `homologados` WHERE ANIO = '2026' AND TR24TCP LIKE '267%'
  UNION ALL
    SELECT 'homologados' AS tabla, 25 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR25TCP AS concepto, TR25IM AS importe, TR25AQ AS aq
      FROM `homologados` WHERE ANIO = '2026' AND TR25TCP LIKE '267%'
  UNION ALL
    SELECT 'homologados' AS tabla, 26 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR26TCP AS concepto, TR26IM AS importe, TR26AQ AS aq
      FROM `homologados` WHERE ANIO = '2026' AND TR26TCP LIKE '267%'
  UNION ALL
    SELECT 'homologados' AS tabla, 27 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR27TCP AS concepto, TR27IM AS importe, TR27AQ AS aq
      FROM `homologados` WHERE ANIO = '2026' AND TR27TCP LIKE '267%'
  UNION ALL
    SELECT 'homologados' AS tabla, 28 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR28TCP AS concepto, TR28IM AS importe, TR28AQ AS aq
      FROM `homologados` WHERE ANIO = '2026' AND TR28TCP LIKE '267%'
  UNION ALL
    SELECT 'regularizados' AS tabla, 1  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR1TCP AS concepto, TR1IM AS importe, TR1AQ AS aq
      FROM `regularizados` WHERE ANIO = '2026' AND TR1TCP LIKE '267%'
  UNION ALL
    SELECT 'regularizados' AS tabla, 2  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR2TCP AS concepto, TR2IM AS importe, TR2AQ AS aq
      FROM `regularizados` WHERE ANIO = '2026' AND TR2TCP LIKE '267%'
  UNION ALL
    SELECT 'regularizados' AS tabla, 3  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR3TCP AS concepto, TR3IM AS importe, TR3AQ AS aq
      FROM `regularizados` WHERE ANIO = '2026' AND TR3TCP LIKE '267%'
  UNION ALL
    SELECT 'regularizados' AS tabla, 4  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR4TCP AS concepto, TR4IM AS importe, TR4AQ AS aq
      FROM `regularizados` WHERE ANIO = '2026' AND TR4TCP LIKE '267%'
  UNION ALL
    SELECT 'regularizados' AS tabla, 5  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR5TCP AS concepto, TR5IM AS importe, TR5AQ AS aq
      FROM `regularizados` WHERE ANIO = '2026' AND TR5TCP LIKE '267%'
  UNION ALL
    SELECT 'regularizados' AS tabla, 6  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR6TCP AS concepto, TR6IM AS importe, TR6AQ AS aq
      FROM `regularizados` WHERE ANIO = '2026' AND TR6TCP LIKE '267%'
  UNION ALL
    SELECT 'regularizados' AS tabla, 7  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR7TCP AS concepto, TR7IM AS importe, TR7AQ AS aq
      FROM `regularizados` WHERE ANIO = '2026' AND TR7TCP LIKE '267%'
  UNION ALL
    SELECT 'regularizados' AS tabla, 8  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR8TCP AS concepto, TR8IM AS importe, TR8AQ AS aq
      FROM `regularizados` WHERE ANIO = '2026' AND TR8TCP LIKE '267%'
  UNION ALL
    SELECT 'regularizados' AS tabla, 9  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR9TCP AS concepto, TR9IM AS importe, TR9AQ AS aq
      FROM `regularizados` WHERE ANIO = '2026' AND TR9TCP LIKE '267%'
  UNION ALL
    SELECT 'regularizados' AS tabla, 10 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR10TCP AS concepto, TR10IM AS importe, TR10AQ AS aq
      FROM `regularizados` WHERE ANIO = '2026' AND TR10TCP LIKE '267%'
  UNION ALL
    SELECT 'regularizados' AS tabla, 11 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR11TCP AS concepto, TR11IM AS importe, TR11AQ AS aq
      FROM `regularizados` WHERE ANIO = '2026' AND TR11TCP LIKE '267%'
  UNION ALL
    SELECT 'regularizados' AS tabla, 12 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR12TCP AS concepto, TR12IM AS importe, TR12AQ AS aq
      FROM `regularizados` WHERE ANIO = '2026' AND TR12TCP LIKE '267%'
  UNION ALL
    SELECT 'regularizados' AS tabla, 13 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR13TCP AS concepto, TR13IM AS importe, TR13AQ AS aq
      FROM `regularizados` WHERE ANIO = '2026' AND TR13TCP LIKE '267%'
  UNION ALL
    SELECT 'regularizados' AS tabla, 14 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR14TCP AS concepto, TR14IM AS importe, TR14AQ AS aq
      FROM `regularizados` WHERE ANIO = '2026' AND TR14TCP LIKE '267%'
  UNION ALL
    SELECT 'regularizados' AS tabla, 15 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR15TCP AS concepto, TR15IM AS importe, TR15AQ AS aq
      FROM `regularizados` WHERE ANIO = '2026' AND TR15TCP LIKE '267%'
  UNION ALL
    SELECT 'regularizados' AS tabla, 16 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR16TCP AS concepto, TR16IM AS importe, TR16AQ AS aq
      FROM `regularizados` WHERE ANIO = '2026' AND TR16TCP LIKE '267%'
  UNION ALL
    SELECT 'regularizados' AS tabla, 17 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR17TCP AS concepto, TR17IM AS importe, TR17AQ AS aq
      FROM `regularizados` WHERE ANIO = '2026' AND TR17TCP LIKE '267%'
  UNION ALL
    SELECT 'regularizados' AS tabla, 18 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR18TCP AS concepto, TR18IM AS importe, TR18AQ AS aq
      FROM `regularizados` WHERE ANIO = '2026' AND TR18TCP LIKE '267%'
  UNION ALL
    SELECT 'regularizados' AS tabla, 19 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR19TCP AS concepto, TR19IM AS importe, TR19AQ AS aq
      FROM `regularizados` WHERE ANIO = '2026' AND TR19TCP LIKE '267%'
  UNION ALL
    SELECT 'regularizados' AS tabla, 20 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR20TCP AS concepto, TR20IM AS importe, TR20AQ AS aq
      FROM `regularizados` WHERE ANIO = '2026' AND TR20TCP LIKE '267%'
  UNION ALL
    SELECT 'regularizados' AS tabla, 21 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR21TCP AS concepto, TR21IM AS importe, TR21AQ AS aq
      FROM `regularizados` WHERE ANIO = '2026' AND TR21TCP LIKE '267%'
  UNION ALL
    SELECT 'regularizados' AS tabla, 22 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR22TCP AS concepto, TR22IM AS importe, TR22AQ AS aq
      FROM `regularizados` WHERE ANIO = '2026' AND TR22TCP LIKE '267%'
  UNION ALL
    SELECT 'regularizados' AS tabla, 23 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR23TCP AS concepto, TR23IM AS importe, TR23AQ AS aq
      FROM `regularizados` WHERE ANIO = '2026' AND TR23TCP LIKE '267%'
  UNION ALL
    SELECT 'regularizados' AS tabla, 24 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR24TCP AS concepto, TR24IM AS importe, TR24AQ AS aq
      FROM `regularizados` WHERE ANIO = '2026' AND TR24TCP LIKE '267%'
  UNION ALL
    SELECT 'regularizados' AS tabla, 25 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR25TCP AS concepto, TR25IM AS importe, TR25AQ AS aq
      FROM `regularizados` WHERE ANIO = '2026' AND TR25TCP LIKE '267%'
  UNION ALL
    SELECT 'regularizados' AS tabla, 26 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR26TCP AS concepto, TR26IM AS importe, TR26AQ AS aq
      FROM `regularizados` WHERE ANIO = '2026' AND TR26TCP LIKE '267%'
  UNION ALL
    SELECT 'regularizados' AS tabla, 27 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR27TCP AS concepto, TR27IM AS importe, TR27AQ AS aq
      FROM `regularizados` WHERE ANIO = '2026' AND TR27TCP LIKE '267%'
  UNION ALL
    SELECT 'regularizados' AS tabla, 28 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR28TCP AS concepto, TR28IM AS importe, TR28AQ AS aq
      FROM `regularizados` WHERE ANIO = '2026' AND TR28TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados' AS tabla, 1  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR1TCP AS concepto, TR1IM AS importe, TR1AQ AS aq
      FROM `formalizados` WHERE ANIO = '2026' AND TR1TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados' AS tabla, 2  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR2TCP AS concepto, TR2IM AS importe, TR2AQ AS aq
      FROM `formalizados` WHERE ANIO = '2026' AND TR2TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados' AS tabla, 3  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR3TCP AS concepto, TR3IM AS importe, TR3AQ AS aq
      FROM `formalizados` WHERE ANIO = '2026' AND TR3TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados' AS tabla, 4  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR4TCP AS concepto, TR4IM AS importe, TR4AQ AS aq
      FROM `formalizados` WHERE ANIO = '2026' AND TR4TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados' AS tabla, 5  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR5TCP AS concepto, TR5IM AS importe, TR5AQ AS aq
      FROM `formalizados` WHERE ANIO = '2026' AND TR5TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados' AS tabla, 6  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR6TCP AS concepto, TR6IM AS importe, TR6AQ AS aq
      FROM `formalizados` WHERE ANIO = '2026' AND TR6TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados' AS tabla, 7  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR7TCP AS concepto, TR7IM AS importe, TR7AQ AS aq
      FROM `formalizados` WHERE ANIO = '2026' AND TR7TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados' AS tabla, 8  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR8TCP AS concepto, TR8IM AS importe, TR8AQ AS aq
      FROM `formalizados` WHERE ANIO = '2026' AND TR8TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados' AS tabla, 9  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR9TCP AS concepto, TR9IM AS importe, TR9AQ AS aq
      FROM `formalizados` WHERE ANIO = '2026' AND TR9TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados' AS tabla, 10 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR10TCP AS concepto, TR10IM AS importe, TR10AQ AS aq
      FROM `formalizados` WHERE ANIO = '2026' AND TR10TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados' AS tabla, 11 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR11TCP AS concepto, TR11IM AS importe, TR11AQ AS aq
      FROM `formalizados` WHERE ANIO = '2026' AND TR11TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados' AS tabla, 12 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR12TCP AS concepto, TR12IM AS importe, TR12AQ AS aq
      FROM `formalizados` WHERE ANIO = '2026' AND TR12TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados' AS tabla, 13 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR13TCP AS concepto, TR13IM AS importe, TR13AQ AS aq
      FROM `formalizados` WHERE ANIO = '2026' AND TR13TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados' AS tabla, 14 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR14TCP AS concepto, TR14IM AS importe, TR14AQ AS aq
      FROM `formalizados` WHERE ANIO = '2026' AND TR14TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados' AS tabla, 15 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR15TCP AS concepto, TR15IM AS importe, TR15AQ AS aq
      FROM `formalizados` WHERE ANIO = '2026' AND TR15TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados' AS tabla, 16 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR16TCP AS concepto, TR16IM AS importe, TR16AQ AS aq
      FROM `formalizados` WHERE ANIO = '2026' AND TR16TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados' AS tabla, 17 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR17TCP AS concepto, TR17IM AS importe, TR17AQ AS aq
      FROM `formalizados` WHERE ANIO = '2026' AND TR17TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados' AS tabla, 18 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR18TCP AS concepto, TR18IM AS importe, TR18AQ AS aq
      FROM `formalizados` WHERE ANIO = '2026' AND TR18TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados' AS tabla, 19 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR19TCP AS concepto, TR19IM AS importe, TR19AQ AS aq
      FROM `formalizados` WHERE ANIO = '2026' AND TR19TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados' AS tabla, 20 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR20TCP AS concepto, TR20IM AS importe, TR20AQ AS aq
      FROM `formalizados` WHERE ANIO = '2026' AND TR20TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados' AS tabla, 21 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR21TCP AS concepto, TR21IM AS importe, TR21AQ AS aq
      FROM `formalizados` WHERE ANIO = '2026' AND TR21TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados' AS tabla, 22 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR22TCP AS concepto, TR22IM AS importe, TR22AQ AS aq
      FROM `formalizados` WHERE ANIO = '2026' AND TR22TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados' AS tabla, 23 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR23TCP AS concepto, TR23IM AS importe, TR23AQ AS aq
      FROM `formalizados` WHERE ANIO = '2026' AND TR23TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados' AS tabla, 24 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR24TCP AS concepto, TR24IM AS importe, TR24AQ AS aq
      FROM `formalizados` WHERE ANIO = '2026' AND TR24TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados' AS tabla, 25 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR25TCP AS concepto, TR25IM AS importe, TR25AQ AS aq
      FROM `formalizados` WHERE ANIO = '2026' AND TR25TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados' AS tabla, 26 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR26TCP AS concepto, TR26IM AS importe, TR26AQ AS aq
      FROM `formalizados` WHERE ANIO = '2026' AND TR26TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados' AS tabla, 27 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR27TCP AS concepto, TR27IM AS importe, TR27AQ AS aq
      FROM `formalizados` WHERE ANIO = '2026' AND TR27TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados' AS tabla, 28 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR28TCP AS concepto, TR28IM AS importe, TR28AQ AS aq
      FROM `formalizados` WHERE ANIO = '2026' AND TR28TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2016' AS tabla, 1  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR1TCP AS concepto, TR1IM AS importe, TR1AQ AS aq
      FROM `formalizados2016` WHERE ANIO = '2026' AND TR1TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2016' AS tabla, 2  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR2TCP AS concepto, TR2IM AS importe, TR2AQ AS aq
      FROM `formalizados2016` WHERE ANIO = '2026' AND TR2TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2016' AS tabla, 3  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR3TCP AS concepto, TR3IM AS importe, TR3AQ AS aq
      FROM `formalizados2016` WHERE ANIO = '2026' AND TR3TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2016' AS tabla, 4  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR4TCP AS concepto, TR4IM AS importe, TR4AQ AS aq
      FROM `formalizados2016` WHERE ANIO = '2026' AND TR4TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2016' AS tabla, 5  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR5TCP AS concepto, TR5IM AS importe, TR5AQ AS aq
      FROM `formalizados2016` WHERE ANIO = '2026' AND TR5TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2016' AS tabla, 6  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR6TCP AS concepto, TR6IM AS importe, TR6AQ AS aq
      FROM `formalizados2016` WHERE ANIO = '2026' AND TR6TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2016' AS tabla, 7  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR7TCP AS concepto, TR7IM AS importe, TR7AQ AS aq
      FROM `formalizados2016` WHERE ANIO = '2026' AND TR7TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2016' AS tabla, 8  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR8TCP AS concepto, TR8IM AS importe, TR8AQ AS aq
      FROM `formalizados2016` WHERE ANIO = '2026' AND TR8TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2016' AS tabla, 9  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR9TCP AS concepto, TR9IM AS importe, TR9AQ AS aq
      FROM `formalizados2016` WHERE ANIO = '2026' AND TR9TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2016' AS tabla, 10 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR10TCP AS concepto, TR10IM AS importe, TR10AQ AS aq
      FROM `formalizados2016` WHERE ANIO = '2026' AND TR10TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2016' AS tabla, 11 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR11TCP AS concepto, TR11IM AS importe, TR11AQ AS aq
      FROM `formalizados2016` WHERE ANIO = '2026' AND TR11TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2016' AS tabla, 12 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR12TCP AS concepto, TR12IM AS importe, TR12AQ AS aq
      FROM `formalizados2016` WHERE ANIO = '2026' AND TR12TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2016' AS tabla, 13 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR13TCP AS concepto, TR13IM AS importe, TR13AQ AS aq
      FROM `formalizados2016` WHERE ANIO = '2026' AND TR13TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2016' AS tabla, 14 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR14TCP AS concepto, TR14IM AS importe, TR14AQ AS aq
      FROM `formalizados2016` WHERE ANIO = '2026' AND TR14TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2016' AS tabla, 15 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR15TCP AS concepto, TR15IM AS importe, TR15AQ AS aq
      FROM `formalizados2016` WHERE ANIO = '2026' AND TR15TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2016' AS tabla, 16 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR16TCP AS concepto, TR16IM AS importe, TR16AQ AS aq
      FROM `formalizados2016` WHERE ANIO = '2026' AND TR16TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2016' AS tabla, 17 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR17TCP AS concepto, TR17IM AS importe, TR17AQ AS aq
      FROM `formalizados2016` WHERE ANIO = '2026' AND TR17TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2016' AS tabla, 18 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR18TCP AS concepto, TR18IM AS importe, TR18AQ AS aq
      FROM `formalizados2016` WHERE ANIO = '2026' AND TR18TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2016' AS tabla, 19 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR19TCP AS concepto, TR19IM AS importe, TR19AQ AS aq
      FROM `formalizados2016` WHERE ANIO = '2026' AND TR19TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2016' AS tabla, 20 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR20TCP AS concepto, TR20IM AS importe, TR20AQ AS aq
      FROM `formalizados2016` WHERE ANIO = '2026' AND TR20TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2016' AS tabla, 21 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR21TCP AS concepto, TR21IM AS importe, TR21AQ AS aq
      FROM `formalizados2016` WHERE ANIO = '2026' AND TR21TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2016' AS tabla, 22 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR22TCP AS concepto, TR22IM AS importe, TR22AQ AS aq
      FROM `formalizados2016` WHERE ANIO = '2026' AND TR22TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2016' AS tabla, 23 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR23TCP AS concepto, TR23IM AS importe, TR23AQ AS aq
      FROM `formalizados2016` WHERE ANIO = '2026' AND TR23TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2016' AS tabla, 24 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR24TCP AS concepto, TR24IM AS importe, TR24AQ AS aq
      FROM `formalizados2016` WHERE ANIO = '2026' AND TR24TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2016' AS tabla, 25 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR25TCP AS concepto, TR25IM AS importe, TR25AQ AS aq
      FROM `formalizados2016` WHERE ANIO = '2026' AND TR25TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2016' AS tabla, 26 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR26TCP AS concepto, TR26IM AS importe, TR26AQ AS aq
      FROM `formalizados2016` WHERE ANIO = '2026' AND TR26TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2016' AS tabla, 27 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR27TCP AS concepto, TR27IM AS importe, TR27AQ AS aq
      FROM `formalizados2016` WHERE ANIO = '2026' AND TR27TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2016' AS tabla, 28 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR28TCP AS concepto, TR28IM AS importe, TR28AQ AS aq
      FROM `formalizados2016` WHERE ANIO = '2026' AND TR28TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2015' AS tabla, 1  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR1TCP AS concepto, TR1IM AS importe, TR1AQ AS aq
      FROM `formalizados2015` WHERE ANIO = '2026' AND TR1TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2015' AS tabla, 2  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR2TCP AS concepto, TR2IM AS importe, TR2AQ AS aq
      FROM `formalizados2015` WHERE ANIO = '2026' AND TR2TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2015' AS tabla, 3  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR3TCP AS concepto, TR3IM AS importe, TR3AQ AS aq
      FROM `formalizados2015` WHERE ANIO = '2026' AND TR3TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2015' AS tabla, 4  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR4TCP AS concepto, TR4IM AS importe, TR4AQ AS aq
      FROM `formalizados2015` WHERE ANIO = '2026' AND TR4TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2015' AS tabla, 5  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR5TCP AS concepto, TR5IM AS importe, TR5AQ AS aq
      FROM `formalizados2015` WHERE ANIO = '2026' AND TR5TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2015' AS tabla, 6  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR6TCP AS concepto, TR6IM AS importe, TR6AQ AS aq
      FROM `formalizados2015` WHERE ANIO = '2026' AND TR6TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2015' AS tabla, 7  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR7TCP AS concepto, TR7IM AS importe, TR7AQ AS aq
      FROM `formalizados2015` WHERE ANIO = '2026' AND TR7TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2015' AS tabla, 8  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR8TCP AS concepto, TR8IM AS importe, TR8AQ AS aq
      FROM `formalizados2015` WHERE ANIO = '2026' AND TR8TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2015' AS tabla, 9  AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR9TCP AS concepto, TR9IM AS importe, TR9AQ AS aq
      FROM `formalizados2015` WHERE ANIO = '2026' AND TR9TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2015' AS tabla, 10 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR10TCP AS concepto, TR10IM AS importe, TR10AQ AS aq
      FROM `formalizados2015` WHERE ANIO = '2026' AND TR10TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2015' AS tabla, 11 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR11TCP AS concepto, TR11IM AS importe, TR11AQ AS aq
      FROM `formalizados2015` WHERE ANIO = '2026' AND TR11TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2015' AS tabla, 12 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR12TCP AS concepto, TR12IM AS importe, TR12AQ AS aq
      FROM `formalizados2015` WHERE ANIO = '2026' AND TR12TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2015' AS tabla, 13 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR13TCP AS concepto, TR13IM AS importe, TR13AQ AS aq
      FROM `formalizados2015` WHERE ANIO = '2026' AND TR13TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2015' AS tabla, 14 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR14TCP AS concepto, TR14IM AS importe, TR14AQ AS aq
      FROM `formalizados2015` WHERE ANIO = '2026' AND TR14TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2015' AS tabla, 15 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR15TCP AS concepto, TR15IM AS importe, TR15AQ AS aq
      FROM `formalizados2015` WHERE ANIO = '2026' AND TR15TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2015' AS tabla, 16 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR16TCP AS concepto, TR16IM AS importe, TR16AQ AS aq
      FROM `formalizados2015` WHERE ANIO = '2026' AND TR16TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2015' AS tabla, 17 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR17TCP AS concepto, TR17IM AS importe, TR17AQ AS aq
      FROM `formalizados2015` WHERE ANIO = '2026' AND TR17TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2015' AS tabla, 18 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR18TCP AS concepto, TR18IM AS importe, TR18AQ AS aq
      FROM `formalizados2015` WHERE ANIO = '2026' AND TR18TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2015' AS tabla, 19 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR19TCP AS concepto, TR19IM AS importe, TR19AQ AS aq
      FROM `formalizados2015` WHERE ANIO = '2026' AND TR19TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2015' AS tabla, 20 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR20TCP AS concepto, TR20IM AS importe, TR20AQ AS aq
      FROM `formalizados2015` WHERE ANIO = '2026' AND TR20TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2015' AS tabla, 21 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR21TCP AS concepto, TR21IM AS importe, TR21AQ AS aq
      FROM `formalizados2015` WHERE ANIO = '2026' AND TR21TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2015' AS tabla, 22 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR22TCP AS concepto, TR22IM AS importe, TR22AQ AS aq
      FROM `formalizados2015` WHERE ANIO = '2026' AND TR22TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2015' AS tabla, 23 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR23TCP AS concepto, TR23IM AS importe, TR23AQ AS aq
      FROM `formalizados2015` WHERE ANIO = '2026' AND TR23TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2015' AS tabla, 24 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR24TCP AS concepto, TR24IM AS importe, TR24AQ AS aq
      FROM `formalizados2015` WHERE ANIO = '2026' AND TR24TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2015' AS tabla, 25 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR25TCP AS concepto, TR25IM AS importe, TR25AQ AS aq
      FROM `formalizados2015` WHERE ANIO = '2026' AND TR25TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2015' AS tabla, 26 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR26TCP AS concepto, TR26IM AS importe, TR26AQ AS aq
      FROM `formalizados2015` WHERE ANIO = '2026' AND TR26TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2015' AS tabla, 27 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR27TCP AS concepto, TR27IM AS importe, TR27AQ AS aq
      FROM `formalizados2015` WHERE ANIO = '2026' AND TR27TCP LIKE '267%'
  UNION ALL
    SELECT 'formalizados2015' AS tabla, 28 AS slot, `RFC`, `NOMB`, `CURP`, `ANIO`, `QNA`, `TIPO`, `NUMREG`, `cr`, `PERPAGI`, `PERPAGF`, `TTR`, TR28TCP AS concepto, TR28IM AS importe, TR28AQ AS aq
      FROM `formalizados2015` WHERE ANIO = '2026' AND TR28TCP LIKE '267%'
  ) AS t
 ORDER BY tabla, RFC, QNA, slot;
