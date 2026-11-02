# Guía para agentes de IA — AFIP PHP SDK

Este documento describe cómo usar **correctamente** la librería `z-system/afip.php` al integrarla en otros proyectos (Laravel, scripts CLI, etc.). Leer esto antes de generar código contra ARCA/AFIP.

## Qué es este paquete

- SDK **PHP** para Web Services SOAP de **ARCA** (ex AFIP), Argentina.
- Punto de entrada: clase `Afip` en `src/Afip.php`.
- Servicios implementados como clases en `src/Class/*.php`, cargadas **bajo demanda** vía `$afip->NombreClase` (`__get` + `include_once`).
- Recursos: certificados, claves, WSDL y tickets de acceso (TA) en `src/Afip_res/` (o rutas custom).

**No incluye:** UI, base de datos, numeración de negocio, PDF de facturas, ni altas en el portal AFIP (eso es responsabilidad del proyecto consumidor).

## Requisitos previos (obligatorios en runtime)

1. **PHP** ≥ 7.4 con extensiones `soap` y `openssl`.
2. **Certificado X.509** y **clave privada** en PEM, emitidos para el CUIT emisor/representado.
3. En [Administrador de Relaciones de AFIP/ARCA](https://www.afip.gob.ar/ws/), el certificado debe tener **autorizado cada Web Service** que se vaya a llamar (ej. `wsfe`, `ws_sr_padron_a13`).
4. **Homologación** (`production => false`) para pruebas; **producción** solo con certificado de producción.

Sin certificado o sin WS autorizado, las llamadas fallan con error SOAP o de negocio de ARCA — no es un bug del SDK.

## Instalación en otro proyecto

```json
{
  "require": {
    "z-system/afip.php": "^0.8"
  }
}
```

O path local:

```json
"repositories": [{ "type": "path", "url": "../afip.php" }],
"require": { "z-system/afip.php": "@dev" }
```

Autoload (Composer):

```php
require __DIR__ . '/vendor/autoload.php';
// Carga src/Afip.php vía composer "files"
```

Sin Composer:

```php
require '/ruta/al/repo/src/Afip.php';
```

Las clases de servicio (`ElectronicBilling`, etc.) **no** están en PSR-4; se cargan al acceder `$afip->ElectronicBilling`.

## Constructor `new Afip($options)`

| Clave | Obligatorio | Default | Descripción |
|--------|-------------|---------|-------------|
| `CUIT` | **Sí** | — | CUIT del contribuyente (11 dígitos, sin guiones). Debe coincidir con el certificado. |
| `production` | No | `false` | `true`/`1`/`"true"` → URLs y WSDL de producción. Cualquier otro valor → homologación. |
| `cert` | No | `'cert'` | Nombre de archivo del certificado PEM dentro de `res_folder`. |
| `key` | No | `'key'` | Nombre de archivo de la clave privada PEM dentro de `res_folder`. |
| `passphrase` | No | `'xxxxx'` | Passphrase de la clave privada. |
| `res_folder` | No | `src/Afip_res/` | Directorio de cert, key y WSDL de servicios (no mover `wsaa.wsdl` del core). |
| `ta_folder` | No | `src/Afip_res/` | Donde se guardan TRA/TA XML cacheados. Debe ser escribible. |
| `exceptions` | No | `false` | Se pasa a `SoapClient`; el SDK igual lanza `Exception` en errores propios y en `_CheckErrors` de FE. |
| `verify_ssl` | No | validar | `false` desactiva verificación TLS (solo diagnóstico). |
| `cafile` | No | auto | Bundle CA; si falta, usa `openssl.cafile` o `curl.cainfo` de PHP. |
| `ssl_ciphers` | No | — | Lista OpenSSL si un entorno lo exige. |

Ejemplo típico en app consumidora (certificados fuera del vendor):

```php
$afip = new Afip([
    'CUIT'       => (int) config('afip.cuit'),
    'production' => config('afip.production'),
    'res_folder' => storage_path('afip/'),
    'ta_folder'  => storage_path('afip/ta/'),
    'cert'       => 'certificado.pem',
    'key'        => 'clave.pem',
    'passphrase' => config('afip.key_passphrase'),
]);
```

## Arquitectura

```
Afip
 ├── GetServiceTA('nombre_servicio_wsaa')  → TokenAuthorization (token, sign)
 ├── WebService($service, $options)         → AfipWebService genérico
 └── __get → ElectronicBilling | RegisterScope* | ...
       └── ExecuteRequest() → SOAP + manejo de errores (según clase)
```

- **WSAA:** cada servicio ARCA exige un **nombre de servicio** distinto al pedir el TA (ver tabla abajo).
- **TA cache:** archivo `TA-{CUIT}-{servicio}[-production].xml` en `ta_folder`. Se renueva antes de expirar.

## Servicios implementados — mapa para agentes

| Propiedad `$afip->…` | Servicio WSAA | Clase | Uso |
|----------------------|---------------|-------|-----|
| `ElectronicBilling` | `wsfe` | wsfev1 | CAE, CAEA, parámetros FE, consulta comprobantes |
| `RegisterScopeFour` | `ws_sr_padron_a4` | Padrón A4 | `GetTaxpayerDetails($cuit)` → `persona` |
| `RegisterScopeFive` | `ws_sr_padron_a5` | Padrón A5 | `getPersona_v2`, `getPersonaList_v2` |
| `RegisterInscriptionProof` | `ws_sr_padron_a5` | Igual que A5 (alias histórico) | Mismos métodos que A5 |
| `RegisterScopeTen` | `ws_sr_padron_a10` | Padrón A10 | `GetTaxpayerDetails` |
| `RegisterScopeThirteen` | `ws_sr_padron_a13` | Padrón A13 | `GetTaxpayerDetails`, `GetTaxIDByDocument` |

**Elegir padrón:** usar el alcance que tenga **dado de alta** el certificado del cliente. Si no se sabe, probar A13 en homologación o consultar documentación ARCA del padrón vigente para nuevas integraciones.

### `ElectronicBilling` — métodos públicos

**CAE (emisión normal)**

| Método | Descripción |
|--------|-------------|
| `GetLastVoucher($ptoVta, $cbteTipo)` | Último número autorizado |
| `CreateVoucher($data, $returnResponse = false)` | Solicita CAE |
| `CreateNextVoucher($data, $returnResponse = false)` | Último + 1 y emite |
| `GetVoucherInfo($nro, $ptoVta, $cbteTipo)` | Consulta; `null` si no existe (código 602) |

**CAEA (contingencia)** — requiere rol CAEA en AFIP

| Método | Descripción |
|--------|-------------|
| `RequestCaea($periodo, $orden)` | Solicita CAEA (`yyyymm`, quincena 1\|2) |
| `QueryCaea($periodo, $orden)` | Consulta CAEA |
| `InformCaeaNoMovement($ptoVta, $caea)` | Sin movimiento |
| `QueryCaeaNoMovement($caea, $ptoVta)` | Consulta sin movimiento |
| `RegisterCaeaInformativo($data, $returnResponse = false)` | Informe de comprobantes con CAEA |

**Parámetros / utilidad**

| Método | SOAP / notas |
|--------|----------------|
| `GetSalesPoints()` | Puntos de venta habilitados |
| `GetVoucherTypes()` | Tipos de comprobante |
| `GetConceptTypes()` | Conceptos |
| `GetDocumentTypes()` | Tipos documento receptor |
| `GetAliquotTypes()` | Alícuotas IVA |
| `GetCurrenciesTypes()` | Monedas |
| `GetOptionsTypes()` | Opcionales |
| `GetTaxTypes()` | Tributos |
| `GetCurrencyQuotation($monId, $fchCotiz = null)` | Cotización |
| `GetReceiverVatConditions($claseCmp = null)` | **Condiciones IVA receptor** (RG 5616); filtrar por clase `A`, `B`, `C`, etc. |
| `GetCountryTypes()` | Países |
| `GetActivityTypes()` | Actividades del emisor |
| `GetMaxRegistrationsPerRequest()` | Tope por request en lote |
| `GetServerStatus()` | FEDummy |
| `FormatDate($yyyymmdd)` | Convierte fecha AFIP a `Y-m-d` |

### Padrón — métodos

| Clase | Métodos |
|-------|---------|
| A4, A10, A13 | `GetServerStatus()`, `GetTaxpayerDetails($idPersona)` → `persona` o `null` |
| A13 | `GetTaxIDByDocument($documento)` |
| A5 / RegisterInscriptionProof | `GetTaxpayerDetails`, `GetTaxpayersDetails($arrayIds)` |

`GetTaxpayerDetails` captura mensajes con `"No existe"` y devuelve `null`.

## Emisión CAE — forma del array `$data`

### Un comprobante (modo simple)

Campos de detalle al **mismo nivel** (el SDK arma `FeCAEReq`):

- Obligatorios habituales: `PtoVta`, `CbteTipo`, `Concepto`, `DocTipo`, `DocNro`, `CbteDesde`, `CbteHasta`, importes (`ImpTotal`, `ImpTotConc`, `ImpNeto`, `ImpOpEx`, `ImpIVA`, `ImpTrib`), `MonId`, `MonCotiz`.
- **`CondicionIVAReceptorId`** (int): obligatorio según normativa ARCA para emitir; validar con `GetReceiverVatConditions()` y datos del receptor (padrón).
- Opcionales: `CbteFch` (yyyymmdd), fechas de servicio, `CanMisMonExt`, `PeriodoAsoc`, etc.

**Arrays anidados:** pasar como **lista PHP simple**; el SDK envuelve al XML SOAP:

| Clave en `$data` | Envoltorio SOAP |
|------------------|-----------------|
| `Tributos` | `Tributo[]` |
| `Iva` | `AlicIva[]` |
| `Opcionales` | `Opcional[]` |
| `CbtesAsoc` | `CbteAsoc[]` |
| `Compradores` | `Comprador[]` |
| `Actividades` | `Actividad[]` (items `['Id' => long]`) |

Referencia completa de campos: WSDL `src/Afip_res/wsfe*.wsdl` y manual [wsfev1 RG 4291](https://www.afip.gov.ar/ws/documentacion/homologacion-externa.asp).

### Lote (varios comprobantes, un request)

```php
$data = [
    'PtoVta'   => 1,
    'CbteTipo' => 6,
    'comprobantes' => [
        [ /* detalle 1 sin PtoVta/CbteTipo o con ellos */ ],
        [ /* detalle 2 */ ],
    ],
];
$result = $afip->ElectronicBilling->CreateVoucher($data);
// Varios ítems → array de arrays con CAE, CAEFchVto, CbteDesde, CbteHasta, Resultado
```

Respetar `GetMaxRegistrationsPerRequest()`.

### Respuesta `CreateVoucher`

- `$returnResponse === false` (default): array `['CAE' => ..., 'CAEFchVto' => 'Y-m-d', ...]` o lista de esos arrays.
- `$returnResponse === true`: objeto respuesta ARCA sin simplificar.

## Flujos recomendados (copiar en proyectos)

### 1. Emitir próxima Factura B

```php
$fe = $afip->ElectronicBilling;
$data = [
    'PtoVta' => 1,
    'CbteTipo' => 6,
    'Concepto' => 1,
    'DocTipo' => 80,
    'DocNro' => 30712345678,
    'CondicionIVAReceptorId' => 1, // validar con GetReceiverVatConditions('B')
    'CbteFch' => (int) date('Ymd'),
    'ImpTotal' => 121,
    'ImpTotConc' => 0,
    'ImpNeto' => 100,
    'ImpOpEx' => 0,
    'ImpIVA' => 21,
    'ImpTrib' => 0,
    'MonId' => 'PES',
    'MonCotiz' => 1,
    'Iva' => [['Id' => 5, 'BaseImp' => 100, 'Importe' => 21]],
];
$result = $fe->CreateNextVoucher($data);
// + voucher_number
```

### 2. Validar condición IVA del receptor

```php
$condiciones = $afip->ElectronicBilling->GetReceiverVatConditions('B');
// Cruzar Id con situación del receptor (padrón / base propia)
```

### 3. Consultar CUIT en padrón (A13)

```php
$persona = $afip->RegisterScopeThirteen->GetTaxpayerDetails(30712345678);
if ($persona === null) {
    // no existe en padrón
}
```

Ver `examples/Padron.php`.

## Errores

- El SDK lanza `Exception` con mensaje `(codigo) texto` y **`$e->getCode()`** = código ARCA cuando aplica.
- `ElectronicBilling::_CheckErrors` mapea observaciones de rechazo en `FECAESolicitar` / `FECAEARegInformativo` a excepción.
- **Events** de ARCA no se exponen como API separada; revisar respuesta completa con `$returnResponse = true` si hace falta.
- Fallos TLS: configurar `cafile` o revisar `verify_ssl`.

## Extender otro Web Service SOAP

```php
$ws = $afip->WebService('nombre_servicio_wsaa', [
    'WSDL'      => '/path/prod.wsdl',
    'URL'       => 'https://...',
    'WSDL_TEST' => '/path/homo.wsdl',
    'URL_TEST'  => 'https://...',
    'service'   => 'nombre_servicio_wsaa',
    'soap_version' => SOAP_1_2, // opcional
]);
$ws->GetTokenAuthorization();
$ws->ExecuteRequest('operacionSoap', $params);
```

El servicio debe estar autorizado en AFIP con el mismo nombre WSAA.

## Ejemplos en el repo

| Archivo | Contenido |
|---------|-----------|
| `examples/CreateVoucher.php` | CAE un comprobante |
| `examples/CreateVoucherBatch.php` | CAE lote |
| `examples/Caea.php` | Consultas CAEA (+ bloques comentados) |
| `examples/Padron.php` | Padrón A13 |

## Checklist agente antes de producción

- [ ] `production => true` solo con certificado de producción
- [ ] WS `wsfe` (y padrón si aplica) autorizados para el certificado
- [ ] `CondicionIVAReceptorId` coherente con receptor y tipo de comprobante
- [ ] Punto de venta y tipo de cbte existen en `GetSalesPoints` / `GetVoucherTypes`
- [ ] Importes, IVA y tributos cuadran (validaciones ARCA)
- [ ] No commitear certificados, claves ni TA en git
- [ ] Homologación exitosa con los mismos flujos

## Errores comunes al generar código (evitar)

1. Olvidar `require` / autoload de `Afip.php`.
2. Usar CUIT con guiones o string no numérico donde AFIP espera long.
3. Llamar padrón A5 sin tener `ws_sr_padron_a5` autorizado.
4. No enviar `CondicionIVAReceptorId` en comprobantes nuevos.
5. Pasar `CbtesAsoc` sin envolver manualmente (el SDK ya envuelve si es array plano).
6. Asumir PSR-4 para clases en `src/Class/` — **solo** acceder vía `$afip->ElectronicBilling`.
7. Mezclar archivos TA de homologación y producción (sufijo `-production` en TA).

## Documentación oficial ARCA

- Índice homologación: https://www.afip.gov.ar/ws/documentacion/homologacion-externa.asp  
- wsfev1: manual RG 4291 (v4.8 en portal)  
- Padrón: manuales por alcance (A4, A5, A10, A13) en sitio AFIP/ARCA  

---

**Versión de esta guía:** alineada con `ElectronicBilling` 0.8 y TLS en `Afip` 0.8. Si el código y este archivo difieren, prevalece el código en `src/`.
