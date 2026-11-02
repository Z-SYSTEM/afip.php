# AFIP PHP SDK

SDK en PHP para Web Services SOAP de ARCA (Argentina): facturación electrónica (wsfev1), padrón contribuyentes y WSAA.

**Integradores y agentes de IA:** leer **[AGENTS.md](AGENTS.md)** — mapa de clases, opciones del constructor, formas de `$data`, servicios WSAA y flujos listos para copiar en otros proyectos.

## Estructura del proyecto

| Ruta | Contenido |
|------|-----------|
| `src/Afip.php` | Clase principal, WSAA, `AfipWebService` |
| `src/Class/` | `ElectronicBilling`, `RegisterScope*` |
| `src/Afip_res/` | Certificados, claves, WSDL, cache TA |
| `examples/` | Scripts ejecutables de referencia |
| `AGENTS.md` | Documentación orientada a IA y apps consumidoras |

## Requisitos

- PHP 7.4+
- Extensión `soap`
- Extensión `openssl`
- Certificado y clave AFIP; cada WS debe estar **autorizado** para ese certificado

## Instalación

**Composer (recomendado en otros proyectos):**

```bash
composer require z-system/afip.php
```

```php
require 'vendor/autoload.php';
$afip = new Afip(['CUIT' => 20123456789]);
```

**Clonar y certificados:** colocar `cert` y `key` (PEM) en `src/Afip_res/` o indicar `res_folder` / `ta_folder` en el constructor (ver AGENTS.md).

## Ejemplos

| Archivo | Descripción |
|---------|-------------|
| [examples/CreateVoucher.php](examples/CreateVoucher.php) | CAE, un comprobante (`CondicionIVAReceptorId`) |
| [examples/CreateVoucherBatch.php](examples/CreateVoucherBatch.php) | CAE en lote |
| [examples/Caea.php](examples/Caea.php) | CAEA: consultas (+ código comentado) |
| [examples/Padron.php](examples/Padron.php) | Padrón A13 por CUIT |

## Servicios disponibles

Acceso: `$afip->ElectronicBilling`, `$afip->RegisterScopeThirteen`, etc.

### Facturación — `ElectronicBilling` (WSAA: `wsfe`)

| Grupo | Métodos |
|-------|---------|
| CAE | `CreateVoucher`, `CreateNextVoucher`, `GetLastVoucher`, `GetVoucherInfo` |
| CAEA | `RequestCaea`, `QueryCaea`, `InformCaeaNoMovement`, `QueryCaeaNoMovement`, `RegisterCaeaInformativo` |
| Parámetros | `GetVoucherTypes`, `GetConceptTypes`, `GetDocumentTypes`, `GetAliquotTypes`, `GetCurrenciesTypes`, `GetOptionsTypes`, `GetTaxTypes`, `GetSalesPoints`, `GetCurrencyQuotation`, `GetReceiverVatConditions`, `GetCountryTypes`, `GetActivityTypes`, `GetMaxRegistrationsPerRequest` |
| Otros | `GetServerStatus`, `FormatDate` |

Lote: `PtoVta`, `CbteTipo`, `comprobantes` => array de detalles. Manual: [homologación wsfev1](https://www.afip.gov.ar/ws/documentacion/homologacion-externa.asp).

### Padrón — WSAA por clase

| Clase | WSAA | Métodos principales |
|-------|------|---------------------|
| `RegisterScopeFour` | `ws_sr_padron_a4` | `GetTaxpayerDetails` |
| `RegisterScopeFive` / `RegisterInscriptionProof` | `ws_sr_padron_a5` | `GetTaxpayerDetails`, `GetTaxpayersDetails` |
| `RegisterScopeTen` | `ws_sr_padron_a10` | `GetTaxpayerDetails` |
| `RegisterScopeThirteen` | `ws_sr_padron_a13` | `GetTaxpayerDetails`, `GetTaxIDByDocument` |

## Seguridad TLS

Por defecto se valida el certificado del servidor ARCA. Opciones en el constructor: `cafile`, `ssl_ciphers`, `verify_ssl` (ver AGENTS.md).

## Licencia

MIT — ver [LICENSE](LICENSE).
