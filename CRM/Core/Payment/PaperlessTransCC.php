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
            'State'   =>  $this->_getParam('state_province'),
            'Zip'     =>  $this->_getParam('postal_code'),
            'Country' =>  $this->_getParam('country'),
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
