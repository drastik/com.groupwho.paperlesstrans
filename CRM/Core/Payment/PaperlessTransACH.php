<?php

class CRM_Core_Payment_PaperlessTransACH extends CRM_Core_Payment_PaperlessTrans {

  protected $_mode = NULL;
  //protected $_params = array();
  //protected $_resultFunctionsMap = array();
  //protected $_reqParams = array();
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
    $this->_resultFunctionsMap = $this->_mapResultFunctions();

    // Get merchant data from config.
    $config = CRM_Core_Config::singleton();

  }

  /**
   * This function checks to see if we have the right config values.
   *
   * @return string
   *   the error message if any
   */
  /*public function checkConfig() {
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
  }*/


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
      $return['error'] = self::error(1, 'No $transaction_type passed to _soapTransaction!');
      return $return;
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
      // If setting up recurring payment or profile, we have different returns.
      elseif (strstr($transaction_type, 'Setup') || strstr($transaction_type, 'Create')) {
        if (!empty($run->{$resultFunction}->ProfileNumber)) {
          $this->_setParam('pt_profile_number', $run->{$resultFunction}->ProfileNumber);
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
      $return['error'] = self::error($run->{$resultFunction}->ResponseCode, $run->{$resultFunction}->Message);
    }

    return $return;
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
  public function _processACHFields($reqParams = array()) {
    //$full_name = $this->_getParam('billing_first_name') . ' ' . $this->_getParam('billing_last_name');

    $params = array(
      'req' => array(
        // No longer required.
        //'CheckNumber' =>     $this->_getParam('bank_check_number'),
        'Check'        => array(
          'RoutingNumber' => $this->_getParam('bank_identification_number'),
          'AccountNumber' => $this->_getParam('bank_account_number'),
          'NameOnAccount' => $this->_getParam('account_holder'),
          //'NameOnAccount' => $full_name,
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

  public function _createACHProfile() {

  }

  /**
   * Prepare the fields for recurring subscription requests.
   *
   * @param string $profile_number
   *   May not be used.  The ProfileNumber from PaperlessTrans.
   *
   * @return array
   *   The array of additional SOAP request params.
   */
  /*public function _processRecurFields($profile_number = '') {
    $full_name = $this->_getParam('billing_first_name') . ' ' . $this->_getParam('billing_last_name');

    $frequency_map = array(
      '52' => 'Weekly',
      '26' => 'Semi-Weekly',
      '24' => 'Bi-Monthly',
      '12' => 'Monthly',
      '4' => 'Quarterly',
      '2' => 'Bi-Annually',
      '1' => 'Annually',
    );

    $params = array(
      'req' => array(
        // This is for updating existing subscriptions.
        // 'ProfileNumber' =>  $profile_number,
        'ListingName' =>  $full_name,
        'Frequency'   =>  '12',                                     //Required Field
        'StartDateTime' =>  '12/01/2013',                                 //Required Field
        'EndingDateTime'=>  '12/01/2019',
        'Memo'      =>  'CiviCRM recurring charge.',
      ),
    );
    return $params;
  }*/


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

    // Transaction type.
    $transaction_type = 'ProcessACH';

    // Switch / if for Credit Card vs ACH.
    $processParams = self::_processACHFields();

    // Merge the defaults with current processParams.
    $this->_reqParams = array_merge_recursive($defaultParams, $processParams);

    // Recurring payments.
    if (!empty($params['is_recur']) && !empty($params['contributionRecurID'])) {
      // Transaction type.
      $transaction_type = 'SetupACHSchedule';

      $result = $this->doRecurPayment();
      if (is_a($result, 'CRM_Core_Error')) {
        return $result;
      }
      //return $params;
    }

    // @TODO Debugging - remove me.
    CRM_Core_Error::debug_var('this reqParams in DDP', $this->_reqParams);

    // Run the SOAP transaction.
    $result = self::_soapTransaction($transaction_type, $this->_reqParams);
    if (!empty($result['error'])) {
      CRM_Core_Error::debug_log_message($result['error']);
      echo $result['error'] . '<p>';
      return FALSE;
        //return $return['error'];
    }

    if (!empty($result['trxn_id'])) {
      $params['trxn_id'] = $result['trxn_id'];
      // Set contribution status to success.
      $params['contribution_status_id'] = 1;
      // Payment success for CiviCRM versions >= 4.6.6.
      $params['payment_status_id'] = 1;

      if (!empty($params['is_recur']) && !empty($params['contributionRecurID'])) {
        $query_params = array(
          1 => array($this->_getParam('pt_profile_number'), 'String'),
          2 => array($_SERVER['REMOTE_ADDR'], 'String'),
          3 => array(0, 'Integer'),
          4 => array($params['contactID'], 'Integer'),
          5 => array($this->_getParam('email'), 'String'),
          6 => array($params['contributionRecurID'], 'Integer'),
        );
        CRM_Core_DAO::executeQuery("INSERT INTO civicrm_iats_customer_codes
          (profile_number, ip, is_ach, cid, email, recur_id)
          VALUES (%1, %2, %3, %4, %5, %6)", $query_params);
      }
    }

    // @TODO Debugging - remove me.
    CRM_Core_Error::debug_var('Result in doDirectPayment', $result);

    return $params;
  }

  /**
   * Create a recurring billing subscription.
   */
  public function doRecurPayment() {
    // Create a Credit Card Customer Profile.
    //$profile_number = $this->_createCCProfile();

    // @TODO Create Profile, then get ProfileNumber.
    $recurParams = self::_processRecurFields();

    // Merge the defaults with current processParams.
    $currentParams = $this->_reqParams;
    $this->_reqParams = array_merge_recursive($currentParams, $recurParams);
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
    CRM_Core_Error::debug_var('updateSubscriptionBillingInfo message', $message);
    CRM_Core_Error::debug_var('updateSubscriptionBillingInfo All params', $params);

    $dao = CRM_Core_DAO::executeQuery("SELECT cr.payment_processor_id, pt.profile_number, pt.cid
      FROM civicrm_contribution_recur cr
      LEFT JOIN civicrm_paperlesstrans_profilenumbers pt ON cr.id = pt.recur_id
      WHERE cr.id=%1", array(1 => array($params['crid'], 'Int')));
    $dao->fetch();

    CRM_Core_Error::debug_var('updateSubscriptionBillingInfo dao', $dao);

    /*"ProfileNumber" =>  "1055105",
    "ListingName" =>  "Jane and Jane Doe",
    "Frequency"   =>  "12",                                     //Required Field
    "StartDateTime" =>  "12/01/2013",                                 //Required Field
    "EndingDateTime"=>  "12/01/2019",
    "Amount"    =>  "1.00",                                     //Required Field
    "Currency"    =>  "USD",                                      //Required Field
    "Memo"      =>  "Membership Fee",*/
    return TRUE;
  }

}
