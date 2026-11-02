<?php
/**
 * SDK for AFIP Electronic Billing (wsfe1)
 * 
 * @link http://www.afip.gob.ar/fe/documentos/manual_desarrollador_COMPG_v2_10.pdf WS Specification
 *
 * @author 	Afip SDK
 * @package Afip
 * @version 0.8
 **/

#[AllowDynamicProperties]
class ElectronicBilling extends AfipWebService {

	var $soap_version 	= SOAP_1_2;
	var $WSDL 			= 'wsfe-production.wsdl';
	var $URL 			= 'https://servicios1.afip.gov.ar/wsfev1/service.asmx';
	var $WSDL_TEST 		= 'wsfe.wsdl';
	var $URL_TEST 		= 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx';

	/**
	 * Gets last voucher number 
	 * 
	 * Asks to Afip servers for number of the last voucher created for
	 * certain sales point and voucher type {@see WS Specification 
	 * item 4.15} 
	 *
	 * @since 0.7
	 *
	 * @param int $sales_point 	Sales point to ask for last voucher  
	 * @param int $type 		Voucher type to ask for last voucher 
	 *
	 * @return int 
	 **/
	public function GetLastVoucher($sales_point, $type)
	{
		$req = array(
			'PtoVta' 	=> $sales_point,
			'CbteTipo' 	=> $type
			);

		return $this->ExecuteRequest('FECompUltimoAutorizado', $req)->CbteNro;
	}

	/**
	 * Create a voucher from AFIP
	 *
	 * Send to AFIP servers request for create a voucher and assign 
	 * CAE to them {@see WS Specification item 4.1}
	 * 
	 * @since 0.7
	 *
	 * @param array $data Voucher parameters {@see WS Specification 
	 * 	item 4.1.3}, some arrays were simplified for easy use {@example 
	 * 	examples/CreateVoucher.php Example with all allowed
	 * 	 attributes}
	 * @param bool $return_response if is TRUE returns complete response  
	 * 	from AFIP
	 * 
	 * @return array if $return_response is set to FALSE returns 
	 * 	[CAE => CAE assigned to voucher, CAEFchVto => Expiration date 
	 * 	for CAE (yyyy-mm-dd)] else returns complete response from 
	 * 	AFIP {@see WS Specification item 4.1.3}
	**/
	public function CreateVoucher($data, $return_response = FALSE)
	{
		if (isset($data['comprobantes']) && is_array($data['comprobantes'])) {
			$pto_vta   = $data['PtoVta'];
			$cbte_tipo = $data['CbteTipo'];
			$detalles  = array();

			foreach ($data['comprobantes'] as $row) {
				if (!isset($row['PtoVta'])) {
					$row['PtoVta'] = $pto_vta;
				}
				if (!isset($row['CbteTipo'])) {
					$row['CbteTipo'] = $cbte_tipo;
				}
				$detalles[] = $this->FormatVoucherDetail($row);
			}

			$req = array(
				'FeCAEReq' => array(
					'FeCabReq' => array(
						'CantReg' 	=> count($detalles),
						'PtoVta' 	=> $pto_vta,
						'CbteTipo' 	=> $cbte_tipo,
					),
					'FeDetReq' => array(
						'FECAEDetRequest' => count($detalles) === 1 ? $detalles[0] : $detalles,
					),
				),
			);
		} else {
			$req = array(
				'FeCAEReq' => array(
					'FeCabReq' => array(
						'CantReg' 	=> $data['CbteHasta'] - $data['CbteDesde'] + 1,
						'PtoVta' 	=> $data['PtoVta'],
						'CbteTipo' 	=> $data['CbteTipo'],
					),
					'FeDetReq' => array(
						'FECAEDetRequest' => $this->FormatVoucherDetail($data),
					),
				),
			);
		}

		$results = $this->ExecuteRequest('FECAESolicitar', $req);

		return $this->ParseCaeVoucherResponse($results, $return_response);
	}

	/**
	 * Informa comprobantes emitidos bajo régimen CAEA {@see WS Specification}
	 *
	 * Misma forma que CreateVoucher; cada detalle debe incluir el campo CAEA.
	 * También admite clave comprobantes para envío en lote.
	 *
	 * @param array $data
	 * @param bool  $return_response
	 *
	 * @return array|object
	 **/
	public function RegisterCaeaInformativo($data, $return_response = FALSE)
	{
		if (isset($data['comprobantes']) && is_array($data['comprobantes'])) {
			$pto_vta   = $data['PtoVta'];
			$cbte_tipo = $data['CbteTipo'];
			$detalles  = array();

			foreach ($data['comprobantes'] as $row) {
				if (!isset($row['PtoVta'])) {
					$row['PtoVta'] = $pto_vta;
				}
				if (!isset($row['CbteTipo'])) {
					$row['CbteTipo'] = $cbte_tipo;
				}
				$detalles[] = $this->FormatVoucherDetail($row);
			}

			$req = array(
				'FeCAEARegInfReq' => array(
					'FeCabReq' => array(
						'CantReg' 	=> count($detalles),
						'PtoVta' 	=> $pto_vta,
						'CbteTipo' 	=> $cbte_tipo,
					),
					'FeDetReq' => array(
						'FECAEADetRequest' => count($detalles) === 1 ? $detalles[0] : $detalles,
					),
				),
			);
		} else {
			$req = array(
				'FeCAEARegInfReq' => array(
					'FeCabReq' => array(
						'CantReg' 	=> $data['CbteHasta'] - $data['CbteDesde'] + 1,
						'PtoVta' 	=> $data['PtoVta'],
						'CbteTipo' 	=> $data['CbteTipo'],
					),
					'FeDetReq' => array(
						'FECAEADetRequest' => $this->FormatVoucherDetail($data),
					),
				),
			);
		}

		$results = $this->ExecuteRequest('FECAEARegInformativo', $req);

		if ($return_response === TRUE) {
			return $results;
		}

		return $this->ParseCaeaInformativoResponse($results);
	}

	/**
	 * Solicita un CAEA para periodo y quincena {@see WS Specification}
	 *
	 * @param int $periodo Formato yyyymm
	 * @param int $orden   1 = primera quincena, 2 = segunda
	 **/
	public function RequestCaea($periodo, $orden)
	{
		return $this->ExecuteRequest('FECAEASolicitar', array(
			'Periodo' => $periodo,
			'Orden'   => $orden,
		))->ResultGet;
	}

	/**
	 * Consulta CAEA ya otorgado {@see WS Specification}
	 **/
	public function QueryCaea($periodo, $orden)
	{
		return $this->ExecuteRequest('FECAEAConsultar', array(
			'Periodo' => $periodo,
			'Orden'   => $orden,
		))->ResultGet;
	}

	/**
	 * Informa CAEA sin movimiento para un punto de venta
	 **/
	public function InformCaeaNoMovement($sales_point, $caea)
	{
		return $this->ExecuteRequest('FECAEASinMovimientoInformar', array(
			'PtoVta' => $sales_point,
			'CAEA'   => $caea,
		));
	}

	/**
	 * Consulta informes de CAEA sin movimiento
	 **/
	public function QueryCaeaNoMovement($caea, $sales_point)
	{
		return $this->ExecuteRequest('FECAEASinMovimientoConsultar', array(
			'CAEA'   => $caea,
			'PtoVta' => $sales_point,
		))->ResultGet;
	}

	/**
	 * Create next voucher from AFIP
	 *
	 * This method combines Afip::GetLastVoucher and Afip::CreateVoucher
	 * for create the next voucher
	 *
	 * @since 0.7
	 *
	 * @param array $data Same to $data in Afip::CreateVoucher except that
	 * 	don't need CbteDesde and CbteHasta attributes
	 * 
	 * @param bool $return_response if is TRUE returns complete response  
	 * 	from AFIP
	 *
	 * @return array if $return_response is set to false returns 
	 * [CAE => CAE assigned to voucher, CAEFchVto => Expiration 
	 * 	date for CAE (yyyy-mm-dd), voucher_number => Number assigned to 
	 * 	voucher] else returns the complete response (same as in method CreateVoucher)
	 *  and the voucher_number
	**/
	public function CreateNextVoucher($data, $return_response = FALSE)
	{
		$last_voucher = $this->GetLastVoucher($data['PtoVta'], $data['CbteTipo']);
		
		$voucher_number = $last_voucher+1;

		$data['CbteDesde'] = $voucher_number;
		$data['CbteHasta'] = $voucher_number;
		
		$res = $this->CreateVoucher($data, $return_response);

		if ($return_response === TRUE) {
			if (is_object($res)) {
				$res->voucher_number = $voucher_number;
			} else {
				$res['voucher_number'] = $voucher_number;
			}

			return $res;
		}

		$res['voucher_number'] = $voucher_number;

		return $res;
	}

	/**
	 * Get complete voucher information
	 *
	 * Asks to AFIP servers for complete information of voucher {@see WS 
	 * Specification item 4.19}
	 *
	 * @since 0.7
	 *
	 * @param int $number 		Number of voucher to get information
	 * @param int $sales_point 	Sales point of voucher to get information
	 * @param int $type 			Type of voucher to get information
	 *
	 * @return array|null returns array with complete voucher information 
	 * 	{@see WS Specification item 4.19} or null if there not exists 
	**/
	public function GetVoucherInfo($number, $sales_point, $type)
	{
		$req = array(
			'FeCompConsReq' => array(
				'CbteNro' 	=> $number,
				'PtoVta' 	=> $sales_point,
				'CbteTipo' 	=> $type
			)
		);

		try {
			$result = $this->ExecuteRequest('FECompConsultar', $req);
		} catch (Exception $e) {
			if ($e->getCode() == 602) 
				return NULL;
			else
				throw $e;
		}

		return $result->ResultGet;
	}

	/**
	 * Asks to AFIP Servers for sales points availables {@see WS 
	 * Specification item 4.11}
	 *
	 * @return array All sales points availables
	**/
	public function GetSalesPoints()
	{
		return $this->ExecuteRequest('FEParamGetPtosVenta')->ResultGet->PtoVenta;
	}

	/**
	 * Asks to AFIP Servers for voucher types availables {@see WS 
	 * Specification item 4.4}
	 *
	 * @since 0.7
	 *
	 * @return array All voucher types availables
	**/
	public function GetVoucherTypes()
	{
		return $this->ExecuteRequest('FEParamGetTiposCbte')->ResultGet->CbteTipo;
	}

	/**
	 * Asks to AFIP Servers for voucher concepts availables {@see WS 
	 * Specification item 4.5}
	 *
	 * @since 0.7
	 *
	 * @return array All voucher concepts availables
	**/
	public function GetConceptTypes()
	{
		return $this->ExecuteRequest('FEParamGetTiposConcepto')->ResultGet->ConceptoTipo;
	}

	/**
	 * Asks to AFIP Servers for document types availables {@see WS 
	 * Specification item 4.6}
	 *
	 * @since 0.7
	 *
	 * @return array All document types availables
	**/
	public function GetDocumentTypes()
	{
		return $this->ExecuteRequest('FEParamGetTiposDoc')->ResultGet->DocTipo;
	}

	/**
	 * Asks to AFIP Servers for aliquot availables {@see WS 
	 * Specification item 4.7}
	 *
	 * @since 0.7
	 *
	 * @return array All aliquot availables
	**/
	public function GetAliquotTypes()
	{
		return $this->ExecuteRequest('FEParamGetTiposIva')->ResultGet->IvaTipo;
	}

	/**
	 * Asks to AFIP Servers for currencies availables {@see WS 
	 * Specification item 4.8}
	 *
	 * @since 0.7
	 *
	 * @return array All currencies availables
	**/
	public function GetCurrenciesTypes()
	{
		return $this->ExecuteRequest('FEParamGetTiposMonedas')->ResultGet->Moneda;
	}

	/**
	 * Asks to AFIP Servers for voucher optional data available {@see WS 
	 * Specification item 4.9}
	 *
	 * @since 0.7
	 *
	 * @return array All voucher optional data available
	**/
	public function GetOptionsTypes()
	{
		return $this->ExecuteRequest('FEParamGetTiposOpcional')->ResultGet->OpcionalTipo;
	}

	/**
	 * Asks to AFIP Servers for tax availables {@see WS 
	 * Specification item 4.10}
	 *
	 * @since 0.7
	 *
	 * @return array All tax availables
	**/
	public function GetTaxTypes()
	{
		return $this->ExecuteRequest('FEParamGetTiposTributos')->ResultGet->TributoTipo;
	}

	/**
	 * Cotización de moneda (FEParamGetCotizacion)
	 *
	 * @param string      $currency_id  Código MonId (ej. DOL, PES)
	 * @param string|null $quote_date   Fecha yyyymmdd; null = cotización del día
	 **/
	public function GetCurrencyQuotation($currency_id, $quote_date = NULL)
	{
		$params = array('MonId' => $currency_id);
		if ($quote_date !== NULL) {
			$params['FchCotiz'] = $quote_date;
		}

		return $this->ExecuteRequest('FEParamGetCotizacion', $params)->ResultGet;
	}

	/**
	 * Condiciones de IVA del receptor válidas para emitir (RG 5616)
	 *
	 * @param string|null $voucher_class Clase de comprobante (Cmp_Clase), ej. A, B, C
	 **/
	public function GetReceiverVatConditions($voucher_class = NULL)
	{
		$params = array();
		if ($voucher_class !== NULL) {
			$params['ClaseCmp'] = $voucher_class;
		}

		return $this->ExecuteRequest('FEParamGetCondicionIvaReceptor', $params)->ResultGet->CondicionIvaReceptor;
	}

	/**
	 * Países habilitados para el servicio
	 **/
	public function GetCountryTypes()
	{
		return $this->ExecuteRequest('FEParamGetTiposPaises')->ResultGet->PaisTipo;
	}

	/**
	 * Actividades habilitadas para el emisor
	 **/
	public function GetActivityTypes()
	{
		return $this->ExecuteRequest('FEParamGetActividades')->ResultGet->ActividadesTipo;
	}

	/**
	 * Cantidad máxima de registros por FECAESolicitar / FECAEARegInformativo
	 **/
	public function GetMaxRegistrationsPerRequest()
	{
		return $this->ExecuteRequest('FECompTotXRequest')->RegXReq;
	}

	/**
	 * Asks to web service for servers status {@see WS 
	 * Specification item 4.14}
	 *
	 * @since 0.7
	 *
	 * @return object { AppServer => Web Service status, 
	 * DbServer => Database status, AuthServer => Autentication 
	 * server status}
	**/
	public function GetServerStatus()
	{
		return $this->ExecuteRequest('FEDummy');
	}

	/**
	 * Change date from AFIP used format (yyyymmdd) to yyyy-mm-dd
	 *
	 * @since 0.7
	 *
	 * @param string|int date to format
	 *
	 * @return string date in format yyyy-mm-dd
	**/
	public function FormatDate($date)
	{
		$parsed = DateTime::createFromFormat('Ymd', (string) $date);
		if ($parsed === false) {
			throw new Exception('Invalid AFIP date format: '.$date);
		}

		return $parsed->format('Y-m-d');
	}

	/**
	 * Sends request to AFIP servers
	 * 
	 * @since 0.7
	 *
	 * @param string 	$operation 	SOAP operation to do 
	 * @param array 	$params 	Parameters to send
	 *
	 * @return mixed Operation results 
	 **/
	public function ExecuteRequest($operation, $params = array())
	{
		$params = array_replace($this->GetWSInitialRequest($operation), $params); 

		$results = parent::ExecuteRequest($operation, $params);

		$this->_CheckErrors($operation, $results);

		return $results->{$operation.'Result'};
	}

	/**
	 * Make default request parameters for most of the operations
	 * 
	 * @since 0.7
	 *
	 * @param string $operation SOAP Operation to do 
	 *
	 * @return array Request parameters  
	 **/
	private function GetWSInitialRequest($operation)
	{
		if ($operation == 'FEDummy') {
			return array();
		}

		$ta = $this->afip->GetServiceTA('wsfe');

		return array(
			'Auth' => array( 
				'Token' => $ta->token,
				'Sign' 	=> $ta->sign,
				'Cuit' 	=> $this->afip->CUIT
				)
		);
	}

	/**
	 * Check if occurs an error on Web Service request
	 * 
	 * @since 0.7
	 *
	 * @param string 	$operation 	SOAP operation to check 
	 * @param mixed 	$results 	AFIP response
	 *
	 * @throws Exception if exists an error in response 
	 * 
	 * @return void 
	 **/
	private function _CheckErrors($operation, $results)
	{
		$res = $results->{$operation.'Result'};

		if ($operation === 'FECAESolicitar') {
			$this->MapVoucherObservationsToErrors($res, 'FECAEDetResponse');
		}

		if ($operation === 'FECAEARegInformativo') {
			$this->MapVoucherObservationsToErrors($res, 'FECAEADetResponse');
		}

		if (isset($res->Errors)) {
			$err = is_array($res->Errors->Err) ? $res->Errors->Err[0] : $res->Errors->Err;
			throw new Exception('('.$err->Code.') '.$err->Msg, $err->Code);
		}
	}

	/**
	 * Normaliza arrays anidados del detalle de comprobante para SOAP
	 **/
	private function FormatVoucherDetail(array $data)
	{
		unset($data['CantReg'], $data['PtoVta'], $data['CbteTipo'], $data['comprobantes']);

		if (isset($data['Tributos'])) {
			$data['Tributos'] = array('Tributo' => $data['Tributos']);
		}

		if (isset($data['Iva'])) {
			$data['Iva'] = array('AlicIva' => $data['Iva']);
		}

		if (isset($data['Opcionales'])) {
			$data['Opcionales'] = array('Opcional' => $data['Opcionales']);
		}

		if (isset($data['CbtesAsoc'])) {
			$data['CbtesAsoc'] = array('CbteAsoc' => $data['CbtesAsoc']);
		}

		if (isset($data['Compradores'])) {
			$data['Compradores'] = array('Comprador' => $data['Compradores']);
		}

		if (isset($data['Actividades'])) {
			$data['Actividades'] = array('Actividad' => $data['Actividades']);
		}

		return $data;
	}

	private function ParseCaeVoucherResponse($results, $return_response)
	{
		if ($return_response === TRUE) {
			return $results;
		}

		$parsed = $this->MapCaeDetailResponses($results->FeDetResp->FECAEDetResponse);

		return count($parsed) === 1 ? $parsed[0] : $parsed;
	}

	private function ParseCaeaInformativoResponse($results)
	{
		$det_responses = $results->FeDetResp->FECAEADetResponse;
		if (!is_array($det_responses)) {
			$det_responses = array($det_responses);
		}

		$parsed = array();
		foreach ($det_responses as $det) {
			$parsed[] = array(
				'CAEA'       => isset($det->CAEA) ? $det->CAEA : NULL,
				'CbteDesde'  => $det->CbteDesde,
				'CbteHasta'  => $det->CbteHasta,
				'Resultado'  => $det->Resultado,
			);
		}

		return count($parsed) === 1 ? $parsed[0] : $parsed;
	}

	private function MapCaeDetailResponses($det_responses, $format_cae_expiration = TRUE)
	{
		if (!is_array($det_responses)) {
			$det_responses = array($det_responses);
		}

		$parsed = array();
		foreach ($det_responses as $det) {
			$row = array(
				'CAE'         => $det->CAE,
				'CbteDesde'   => $det->CbteDesde,
				'CbteHasta'   => $det->CbteHasta,
				'Resultado'   => $det->Resultado,
			);
			if ($format_cae_expiration && isset($det->CAEFchVto)) {
				$row['CAEFchVto'] = $this->FormatDate($det->CAEFchVto);
			}
			$parsed[] = $row;
		}

		return $parsed;
	}

	private function MapVoucherObservationsToErrors($res, $detail_key)
	{
		if (!isset($res->FeDetResp->{$detail_key})) {
			return;
		}

		$details = $res->FeDetResp->{$detail_key};
		if (!is_array($details)) {
			$details = array($details);
		}

		foreach ($details as $det) {
			if (isset($det->Observaciones) && isset($det->Resultado) && $det->Resultado !== 'A') {
				$res->Errors = new StdClass();
				$res->Errors->Err = $det->Observaciones->Obs;
				return;
			}
		}
	}

}

