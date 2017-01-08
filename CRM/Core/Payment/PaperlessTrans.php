<?php

class CRM_Core_Payment_PaperlessTrans extends CRM_Core_Payment {

  protected $_mode = NULL;
  protected $_params = array();
  protected $_resultFunctionsMap = array();
  protected $_reqParams = array();
  protected $_islive = NULL;
  protected $_isTestString = 'False';

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

    // Get merchant data from config.
    $config = CRM_Core_Config::singleton();

  }

  /**
   * This function checks to see if we have the right config values.
   *
   * @return string
   *   the error message if any
   */
  public function checkConfig() {
    $error = array();
    if (empty($this->_paymentProcessor['user_name'])) {
      $error[] = ts('APILogin is not set for this payment processor');
    }

    if (empty($this->_paymentProcessor['password'])) {
      $error[] = ts('Key is not set for this payment processor');
    }

    if (!empty($error)) {
      return implode('<p>', $error);
    }
    else {
      return NULL;
    }
  }

  /**
   * Set a field to the specified value.  Value must be a scalar (int,
   * float, string, or boolean).
   *
   * @param string $field
   * @param mixed $value
   *
   * @return bool
   *   false if value is not a scalar, true if successful
   */
  public function _setParam($field, $value) {
    if (!is_scalar($value)) {
      return FALSE;
    }
    else {
      $this->_params[$field] = $value;
    }
  }

  /**
   * Get the value of a field if set.
   *
   * @param string $field
   *   The field.
   *
   * @return mixed
   *   value of the field, or empty string if the field is
   *   not set
   */
  public function _getParam($field) {
    if (isset($this->_params[$field])) {
      return $this->_params[$field];
    }
    else {
      return '';
    }
  }

  /**
   * Submit the SOAP transaction.
   *
   * @param  String $transaction_type
   *   The type of transaction to push, options are:
   *   - CreateACHProfile
   *   - CreateCardProfile
   *   - ProcessACH
   *   - AuthorizeCard
   *   - processCard
   *   - RefundCardTransaction (not yet implemented)
   *   - SettleCardAuthorization (not yet implemented)
   *
   *
   * @param array $params
   *   The request parameters/arguments for the SOAP call.
   *
   * @return [type]                   [description]
   */
  public function _soapTransaction($transaction_type = '', $params = array()) {
    // Don't want to assume anything here.  Must be passed.
    if (empty($transaction_type)) {
      return self::error(1, 'No $transaction_type passed to _soapTransaction!');
    }

    // Passing $params to this function may be useful later.
    if (empty($params)) {
      $params = $this->_reqParams;
    }

    $return = array();

    //$client = new SoapClient('https://svc.paperlesstrans.com:9999/?wsdl');
    // @TODO Does this get the right URL?
    $client = new SoapClient($this->_paymentProcessor['url_site']);

    // Need to swap for __soapCall() since __call() is deprecated.
    $run = $client->__call($transaction_type, array('parameters' => $params));

    // Get the property name of this transaction_type's result.
    $resultFunction = $this->_resultFunctionsMap[$transaction_type];

    $return['dateTimeStamp'] = $run->{$resultFunction}->DateTimeStamp;
    $return['ResponseCode'] = $run->{$resultFunction}->ResponseCode;

    // @TODO Debugging - remove me.
    CRM_Core_Error::debug_var('SOAP resultFunction', $run->{$resultFunction});

    if ($run->{$resultFunction}->ResponseCode == 0) {
      $this->_setParam('trxn_id', $run->{$resultFunction}->TransactionID);
      $return['trxn_id'] = $run->{$resultFunction}->TransactionID;

      // Different propertyName for Card vs ACH processing.
      // Determine approval per transaction type.
      $approval = 'False';
      if ($transaction_type == 'ProcessACH') {
        $approval = $run->{$resultFunction}->IsAccepted;
      }
      elseif ($transaction_type == 'SetupCardSchedule') {
        $profile_number = $run->{$resultFunction}->ProfileNumber;
        if (!empty($profile_number)) {
          $approval = 'True';
        }
      }
      else {
        $approval = $run->{$resultFunction}->IsApproved;
      }

      // Success!
      if ($approval == 'True') {
        $this->_setParam('authorization_id', $run->{$resultFunction}->AuthorizationNumber);
        $return['AuthorizationNumber'] = $run->{$resultFunction}->AuthorizationNumber;
      }

    }
    else {
      // Error message.
      return self::error($run->{$resultFunction}->ResponseCode, $run->{$resultFunction}->Message);
    }

    return $return;
  }

  /**
   * Build the default array to send to PaperlessTrans.
   *
   * @return array
   *   The scaffolding for the SOAP transaction parameters.
   */
  public function _buildRequestDefaults() {
    $defaults = array(
      'req' => array(
        'Token' => array(
          'TerminalID' => $this->_paymentProcessor['user_name'],
          'TerminalKey' =>  $this->_paymentProcessor['password'],
        ),
        'TestMode'    =>  $this->_isTestString,
        'Currency'    =>  $this->_getParam('currencyID'),
        'Amount'      =>  $this->_getParam('amount'),
        'CardPresent' =>  'False',
        // I think these have to be configured in the gateway account.
        'CustomFields'  => array(
          'Field_1' =>  'InvoiceID: ' . $this->_getParam('invoiceID'),
          'Field_2' =>  'IP Addr: ' . $this->_getParam('ip_address'),
          /*'Field_3' =>  '',
          'Field_4' =>  '',
          'Field_5' =>  '',
          'Field_6' =>  '',
          'Field_7' =>  '',
          'Field_8' =>  '',
          'Field_9' =>  '',
          'Field_10'  =>  '',*/
        ),
      ),
    );

    return $defaults;
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

    $params = array(
      'req' => array(
        'Card'        => array(
          'CardNumber'  => $this->_getParam('credit_card_number'),                  //Required Field
          'ExpirationMonth' => $this->_getParam('month'),                        //Required Field
          'ExpirationYear'=> $this->_getParam('year'),
          'SecurityCode'  => $this->_getParam('cvv2'),
          'NameOnAccount' => $full_name,                   //Required Field
          'Address'   => array(
            'Street'  =>  $this->_getParam('street_address'),     //Required Field
            'City'    =>  $this->_getParam('city'),         //Required Field
            'State'   =>  $this->_getParam('state_province'),           //Required Field
            'Zip'     =>  $this->_getParam('postal_code'),          //Required Field
            'Country' =>  $this->_getParam('country'),
          ),            //Required Field
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

  /**
   * Map the transaction_type to the property name on the result.
   *
   * @return array
   *   Array of transaction_type => resultPropertyName.
   */
  public function _mapResultFunctions() {
    $map = array(
      'CreateACHProfile' => 'CreateACHProfileResult',
      'CreateCardProfile' => 'CreateCardProfileResult',
      'ProcessACH' => 'ProcessACHResult',
      'AuthorizeCard' => 'AuthorizeCardResult',
      'processCard' => 'ProcessCardResult',
      'RefundCardTransaction' => 'RefundCardTransactionResult',
      'SettleCardAuthorization' => 'SettleCardAuthorizationResult',
      'SetupCardSchedule' => 'SetupCardScheduleResult',
    );

    return $map;
  }

  /**
   * @param null $errorCode
   * @param null $errorMessage
   *
   * @return object
   */
  public function &error($errorCode = NULL, $errorMessage = NULL) {
    $e = CRM_Core_Error::singleton();
    if ($errorCode) {
      $e->push($errorCode, 0, array(), $errorMessage);
    }
    else {
      $e->push(9001, 0, array(), 'Unknown System Error.');
    }
    return $e;
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

    // @TODO Debugging - remove me.
    CRM_Core_Error::debug_var('All params', $params);

    // Build defaults for request parameters.
    $defaultParams = $this->_reqParams = self::_buildRequestDefaults();

    // Credit Card transation type.
    $transaction_type = 'processCard';

    // Recurring payments.
    if (!empty($params['is_recur']) && !empty($params['contributionRecurID'])) {
      // Credit Card transation type.
      $transaction_type = 'SetupCardSchedule';

      $result = $this->doRecurPayment();
      if (is_a($result, 'CRM_Core_Error')) {
        return $result;
      }
      return $params;
    }

    // Switch / if for Credit Card vs ACH.
    $processParams = self::_processCardFields();

    // Merge the defaults with current processParams.
    $this->_reqParams = array_merge_recursive($defaultParams, $processParams);

    // @TODO Debugging - remove me.
    CRM_Core_Error::debug_var('this reqParams in DDP', $this->_reqParams);

    // Run the SOAP transaction.
    $result = self::_soapTransaction($transaction_type, $this->_reqParams);

    if (!empty($return['trxn_id'])) {
      $params['trxn_id'] = $return['trxn_id'];
    }

    // @TODO Debugging - remove me.
    CRM_Core_Error::debug_var('Result in doDirectPayment', $result);

    return $params;
  }

  /**
   * Create a recurring billing subscription.
   */
  public function doRecurPayment() {


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

    return TRUE;
  }

}
