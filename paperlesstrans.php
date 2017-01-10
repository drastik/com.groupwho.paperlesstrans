<?php

require_once 'paperlesstrans.civix.php';

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function paperlesstrans_civicrm_config(&$config) {
  _paperlesstrans_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @param array $files
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function paperlesstrans_civicrm_xmlMenu(&$files) {
  _paperlesstrans_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function paperlesstrans_civicrm_install() {
  _paperlesstrans_civix_civicrm_install();
}

/**
* Implements hook_civicrm_postInstall().
*
* @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
*/
function paperlesstrans_civicrm_postInstall() {
  _paperlesstrans_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function paperlesstrans_civicrm_uninstall() {
  _paperlesstrans_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function paperlesstrans_civicrm_enable() {
  _paperlesstrans_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function paperlesstrans_civicrm_disable() {
  _paperlesstrans_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed
 *   Based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function paperlesstrans_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _paperlesstrans_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function paperlesstrans_civicrm_managed(&$entities) {
  $entities[] = array(
    'module' => 'com.groupwho.paperlesstrans',
    'name' => 'Paperless Transactions Credit Card',
    'entity' => 'PaymentProcessorType',
    'params' => array(
      'version' => 3,
      'name' => 'Paperless Transactions Credit Card',
      'title' => 'Paperless Transactions Credit Card',
      'description' => 'Paperless Transactions Payment Processor',
      'class_name' => 'Payment_PaperlessTrans',
      'billing_mode' => 'form',
      'user_name_label' => 'Terminal ID',
      'password_label' => 'Terminal Key',
      'url_site_default' => 'https://svc.paperlesstrans.com:9999/?wsdl',
      'url_recur_default' => 'https://svc.paperlesstrans.com:9999/?wsdl',
      'url_site_test_default' => 'http://svc.paperlesstrans.com:8888/?wsdl',
      'url_recur_test_default' => 'http://svc.paperlesstrans.com:8888/?wsdl',
      'is_recur' => 1,
      'payment_type' => 1,
    ),
  );

  $entities[] = array(
    'module' => 'com.groupwho.paperlesstrans',
    'name' => 'Paperless Transactions ACH/ETF',
    'entity' => 'PaymentProcessorType',
    'params' => array(
      'version' => 3,
      'name' => 'Paperless Transactions ACH/ETF',
      'title' => 'Paperless Transactions ACH/ETF',
      'description' => 'Paperless Transactions Payment Processor',
      'class_name' => 'Payment_PaperlessTransACH',
      'billing_mode' => 'form',
      'user_name_label' => 'Terminal ID',
      'password_label' => 'Terminal Key',
      'url_site_default' => 'https://svc.paperlesstrans.com:9999/?wsdl',
      'url_recur_default' => 'https://svc.paperlesstrans.com:9999/?wsdl',
      'url_site_test_default' => 'http://svc.paperlesstrans.com:8888/?wsdl',
      'url_recur_test_default' => 'http://svc.paperlesstrans.com:8888/?wsdl',
      'is_recur' => 1,
      'payment_type' => 2,
      'payment_instrument_id' => '2',
    ),
  );

  _paperlesstrans_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * @param array $caseTypes
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function paperlesstrans_civicrm_caseTypes(&$caseTypes) {
  _paperlesstrans_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function paperlesstrans_civicrm_angularModules(&$angularModules) {
_paperlesstrans_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function paperlesstrans_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _paperlesstrans_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Functions below this ship commented out. Uncomment as required.
 *

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_preProcess
 *
function paperlesstrans_civicrm_preProcess($formName, &$form) {

} // */

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 *
function paperlesstrans_civicrm_navigationMenu(&$menu) {
  _paperlesstrans_civix_insert_navigation_menu($menu, NULL, array(
    'label' => ts('The Page', array('domain' => 'com.groupwho.paperlesstrans')),
    'name' => 'the_page',
    'url' => 'civicrm/the-page',
    'permission' => 'access CiviReport,access CiviContribute',
    'operator' => 'OR',
    'separator' => 0,
  ));
  _paperlesstrans_civix_navigationMenu($menu);
} // */
