<?php

class CRM_Core_Payment_PaperlessTransCC extends CRM_Core_Payment_PaperlessTrans {

  protected $_mode = NULL;

  /**
   * We only need one instance of this object. So we use the singleton
   * pattern and cache the instance in this variable
   *
   * @var object
   */
  static private $_singleton = NULL;

  /**
   * Constructor.
   *
   * @param string $mode
   *   the mode of operation: live or test.
   *
   * @param array $paymentProcessor
   */
  public function __construct($mode, &$paymentProcessor) {
    $this->_mode = $mode;
    $this->_islive = ($mode == 'live' ? TRUE : FALSE);
    // Final version because Paperless wants a string.
    $this->_isTestString = ($mode == 'test' ? 'True' : 'False');
    $this->_paymentProcessor = $paymentProcessor;
    $this->_processorName = ts('PaperlessTrans');
    // Array of the result function names by Soap request function name.
    $this->_resultFunctionsMap = self::_mapResultFunctions();
    $this->_frequencyMap = self::_mapFrequency();

    // Set transaction type for Soap call.
    $this->_transactionType = 'processCard';
    $this->_transactionTypeRecur = 'SetupCardSchedule';

    // Get merchant data from config.
    $config = CRM_Core_Config::singleton();
  }


  /**
   * Generate the remainder of SOAP request array for processing Credit Cards.
   *
   * @param array &$reqParams
   *   @todo  Might not use this! @TODO
   *
   * @return array
   *   The remainder of the SOAP transaction parameters for Credit Cards.
   */
  public function _processCardFields($reqParams = array()) {
    $full_name = $this->_getParam('billing_first_name') . ' ' . $this->_getParam('billing_last_name');
    $country = civicrm_api3('Country', 'get', array('id' => $this->_getParam('country_id')));
    $country_code = $country['values'][$this->_getParam('country_id')]['iso_code'];

    // Requires January 2017+ version of CiviCRM api.
    //$state = civicrm_api3('StateProvince', 'get', array('id' => $this->_getParam('state_province_id')));
    //$state_code = $country['values'][$this->_getParam('state_province_id')]['abbreviation'];

    $state_code = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_StateProvince',
      $this->_getParam('state_province_id'),
      'abbreviation'
    );

    $params = array(
      'req' => array(
        'CardPresent' =>  'False',
        'Card'        => array(
          'CardNumber'  => $this->_getParam('credit_card_number'),
          'ExpirationMonth' => $this->_getParam('month'),
          'ExpirationYear'=> $this->_getParam('year'),
          'SecurityCode'  => $this->_getParam('cvv2'),
          'NameOnAccount' => $full_name,
          'Address'   => array(
            'Street'  =>  $this->_getParam('street_address'),
            'City'    =>  $this->_getParam('city'),
            'State'   =>  $state_code,
            'Zip'     =>  $this->_getParam('postal_code'),
            'Country' =>  $country_code,
          ),
          /*'Identification'=> array(
            'IDType'  =>  '1',
            'State'   =>  'TX',
            'Number'  =>  '12345678',
            'Expiration'=>  '12/31/2012',
            'DOB'   =>  '12/31/1956',
            'Address' => array(
              'Street'  =>  '1234 Main Street',
              'City'    =>  'Anytown',
              'State'   =>  'TX',
              'Zip'   =>  '99999',
              'Country' =>  'US',
            ),
          ),*/
        ),
      ),
    );

    // Add the ProfileNumber to update existing subscription.
    /*if (!empty($reqParams['ProfileNumber'])) {
      $params['req']['ProfileNumber'] = $reqParams['ProfileNumber'];
    }*/

    return $params;
  }

  public function _createCCProfile() {

  }


 /**
   * Submit a payment.
   *
   * @param array $params
   *   Assoc array of input parameters for this transaction.
   *
   * @return array
   *   The result in a nice formatted array (or an error object).
   */
  public function doDirectPayment(&$params) {
    // Set params in our own storage.
    foreach ($params as $field => $value) {
      $this->_setParam($field, $value);
    }

    // Build defaults for request parameters.
    $defaultParams = $this->_reqParams = self::_buildRequestDefaults();

    // Switch for Credit Card vs ACH.
    $processParams = self::_processCardFields();

    // Merge the defaults with current processParams.
    $this->_reqParams = array_merge_recursive($defaultParams, $processParams);

    // Let the main class handle everything else.
    return parent::doDirectPayment($params);
  }


  /**
   * Update recurring billing subscription.
   *
   * @param string $message
   * @param array $params
   *
   * @return bool|object
   */
  public function updateSubscriptionBillingInfo(&$message = '', $params = array()) {
    // Build generic params, fetching contrib recur id and other values.
    $additional_req_params = parent::updateSubscriptionBillingInfoPrep($message, $params);
    if (empty($additional_req_params) || is_a($additional_req_params, 'CRM_Core_Error')) {
      return self::error(2, $additional_req_params);
    }

    // Set params in our own storage.
    foreach ($params as $field => $value) {
      $this->_setParam($field, $value);
    }

    // Build defaults for request parameters.
    $defaultParams = $this->_reqParams = self::_buildRequestDefaults();

    // Process card-related fields.
    $processParams = self::_processCardFields($additional_req_params);

    // Handle the update-request specific fields.
    $updateParams = parent::_processUpdateFields($additional_req_params);

    // Merge the defaults with current processParams.
    $this->_reqParams = array_merge_recursive($defaultParams, $processParams, $updateParams);

    // Run the SOAP transaction.
    $result = parent::_soapTransaction('UpdateCardSchedule', $this->_reqParams);

    // Handle errors.
    if (is_a($result, 'CRM_Core_Error')) {
      $error_message = 'There was an error with the transaction.  Please check logs: ';
      echo $error_message . '<p>';
      CRM_Core_Error::debug_log_message($error_message);
      return $result;
    }

    $message = "{$result['ResponseCode']}: {$result['ProfileNumber']}";
    return TRUE;
  }

}
