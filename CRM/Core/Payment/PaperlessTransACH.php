<?php

class CRM_Core_Payment_PaperlessTransACH extends CRM_Core_Payment_PaperlessTrans {

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
    $this->_transactionType = 'ProcessACH';
    $this->_transactionTypeRecur = 'SetupACHSchedule';

    // Get merchant data from config.
    $config = CRM_Core_Config::singleton();
  }


  /**
   * Return an array of all the details about the fields potentially required for payment fields.
   *
   * Only those determined by getPaymentFormFields will actually be assigned to the form
   *
   * CheckNumber field no longer required.  We don't have to do this.
   * @return array
   *   field metadata
   */
  /*public function getPaymentFormFieldsMetadata() {
    $array = parent::getPaymentFormFieldsMetadata();
    $array += array(
      'bank_check_number' => array(
        'htmlType' => 'text',
        'name' => 'bank_check_number',
        'title' => ts('Check Number'),
        'cc_field' => TRUE,
        'attributes' => array(
          'size' => 20,
          'maxlength' => 34,
          'autocomplete' => 'off',
        ),
        'rules' => array(
          array(
            'rule_message' => ts('Please enter a valid Check Number (value must not contain punctuation characters).'),
            'rule_name' => 'nopunctuation',
            'rule_parameters' => NULL,
          ),
        ),
        'is_required' => TRUE,
      ),
    );

    return $array;
  }*/

  /**
   * Get array of fields that should be displayed on the payment form for direct debits.
   *
   * CheckNumber field no longer required.  We don't have to do this.
   * @return array
   */
  /*protected function getDirectDebitFormFields() {
    return array(
      'account_holder',
      'bank_account_number',
      'bank_identification_number',
      'bank_check_number',
      'bank_name',
    );
  }*/


  /**
   * Generate the remainder of SOAP request array for processing ACH/EBT.
   *
   * @param array &$reqParams
   *   @todo  Might not use this! @TODO
   *
   * @return array
   *   The remainder of the SOAP transaction parameters for Credit Cards.
   */
  public function _processACHFields($reqParams = array()) {
    //$full_name = $this->_getParam('billing_first_name') . ' ' . $this->_getParam('billing_last_name');

    // Fix CiviCRM odd behavior.
    // In contributions, country is the iso_code, in updates it is the full name.
    if (!empty($this->_getParam('country_id'))) {
      $country = civicrm_api3('Country', 'get', array('id' => $this->_getParam('country_id')));
      $country_code = $country['values'][$this->_getParam('country_id')]['iso_code'];
    }
    else {
      $country_code = $this->_getParam('country');
    }

    // Requires January 2017+ version of CiviCRM api.
    //$state = civicrm_api3('StateProvince', 'get', array('id' => $this->_getParam('state_province_id')));
    //$state_code = $country['values'][$this->_getParam('state_province_id')]['abbreviation'];

    // Fix CiviCRM odd behavior.
    // In contributions, state_province is the iso_code, in updates it is the full name.
    if (!empty($this->_getParam('state_province_id'))) {
      $state_code = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_StateProvince',
        $this->_getParam('state_province_id'),
        'abbreviation'
      );
    }
    else {
      $state_code = $this->_getParam('state_province');
    }

    $params = array(
      'req' => array(
        // No longer required.
        //'CheckNumber' =>     $this->_getParam('bank_check_number'),
        'Check'        => array(
          'RoutingNumber' => $this->_getParam('bank_identification_number'),
          'AccountNumber' => $this->_getParam('bank_account_number'),
          'NameOnAccount' => $this->_getParam('account_holder'),
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

    return $params;
  }

  public function _createACHProfile() {

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

    // Process ACH-related fields.
    $processParams = self::_processACHFields();

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

    // Process ACH-related fields.
    $processParams = self::_processACHFields($additional_req_params);

    // Handle the update-request specific fields.
    $updateParams = parent::_processUpdateFields($additional_req_params);

    // Merge the defaults with current processParams.
    $this->_reqParams = array_merge_recursive($defaultParams, $processParams, $updateParams);

    // Run the SOAP transaction.
    $result = parent::_soapTransaction('UpdateACHSchedule', $this->_reqParams);

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
