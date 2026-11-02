<?php
/**
 * Emisión CAE en lote (varios comprobantes en un solo FECAESolicitar).
 * Homologación: certificados en src/Afip_res/, CUIT y PtoVta de prueba.
 */
include '../src/Afip.php';

$afip = new Afip(array('CUIT' => 20111111112));

$pto_vta   = 1;
$cbte_tipo = 6; // Factura B

$ultimo = $afip->ElectronicBilling->GetLastVoucher($pto_vta, $cbte_tipo);
$desde  = $ultimo + 1;
$hasta  = $ultimo + 2;

$fecha = intval(date('Ymd'));

$comprobante = function ($numero) use ($fecha) {
	return array(
		'Concepto'               => 1,
		'DocTipo'                => 80,
		'DocNro'                 => 20111111112,
		'CondicionIVAReceptorId' => 1,
		'CbteDesde'              => $numero,
		'CbteHasta'              => $numero,
		'CbteFch'                => $fecha,
		'ImpTotal'               => 121,
		'ImpTotConc'             => 0,
		'ImpNeto'                => 100,
		'ImpOpEx'                => 0,
		'ImpIVA'                 => 21,
		'ImpTrib'                => 0,
		'MonId'                  => 'PES',
		'MonCotiz'               => 1,
		'Iva'                    => array(
			array(
				'Id'       => 5,
				'BaseImp'  => 100,
				'Importe'  => 21,
			),
		),
	);
};

$data = array(
	'PtoVta'       => $pto_vta,
	'CbteTipo'     => $cbte_tipo,
	'comprobantes' => array(
		$comprobante($desde),
		$comprobante($hasta),
	),
);

$resultado = $afip->ElectronicBilling->CreateVoucher($data);

print_r($resultado);
