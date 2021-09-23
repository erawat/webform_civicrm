<?php

namespace Drupal\Tests\webform_civicrm\FunctionalJavascript;

use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\DrupalSelenium2Driver;

/**
 * Tests submitting a Webform with CiviCRM: Contact with Membership (Free)
 *
 * @group webform_civicrm
 */
final class MembershipSubmissionTest extends WebformCivicrmTestBase {

  function testSubmitMembershipAutoRenew() {
    $this->createMembershipType(1, TRUE);
    $payment_processor = $this->createPaymentProcessor();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet(Url::fromRoute('entity.webform.civicrm', [
      'webform' => $this->webform->id(),
    ]));
    // The label has a <div> in it which can cause weird failures here.
    $this->assertSession()->waitForText('Enable CiviCRM Processing');
    $this->assertSession()->waitForField('nid');
    $this->getSession()->getPage()->checkField('nid');
    $this->getSession()->getPage()->clickLink('Memberships');

    // Configure Membership tab.
    $this->getSession()->getPage()->selectFieldOption('membership_1_number_of_membership', 1);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('civicrm_1_membership_1_membership_membership_type_id', '- User Select -');
    $this->htmlOutput();
    // $this->createScreenshot($this->htmlOutputDirectory . '/membership_page_settings.png');

    // Configure Contribution tab and enable recurring.
    $this->getSession()->getPage()->clickLink('Contribution');
    $this->getSession()->getPage()->selectFieldOption('civicrm_1_contribution_1_contribution_enable_contribution', 1);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('You must enable an email field for Contact 1 in order to process transactions.');
    $this->getSession()->getPage()->pressButton('Enable It');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('Currency', 'USD');
    $this->getSession()->getPage()->selectFieldOption('Financial Type', 1);
    $this->getSession()->getPage()->selectFieldOption('Frequency of Installments', 'year');

    $this->getSession()->getPage()->selectFieldOption('Payment Processor', $payment_processor['id']);
    // $this->createScreenshot($this->htmlOutputDirectory . '/membership_page_settings_before_save.png');
    $this->enableBillingSection();

    $this->getSession()->getPage()->pressButton('Save Settings');
    $this->assertSession()->pageTextContains('Saved CiviCRM settings');

    $this->drupalGet($this->webform->toUrl('canonical'));
    $this->assertPageNoErrorMessages();
    // $this->createScreenshot($this->htmlOutputDirectory . '/membership_page1.png');

    $this->getSession()->getPage()->fillField('First Name', 'Frederick');
    $this->getSession()->getPage()->fillField('Last Name', 'Pabst');
    $this->getSession()->getPage()->fillField('Email', 'fred@example.com');
    $this->getSession()->getPage()->selectFieldOption('civicrm_1_membership_1_membership_membership_type_id', '1');

    $this->getSession()->getPage()->pressButton('Next >');

    $this->assertSession()->elementExists('css', '#wf-crm-billing-items');
    $this->htmlOutput();
    $this->assertSession()->elementTextContains('css', '#wf-crm-billing-total', '1.00');

    // Wait for the credit card form to load in.
    $this->assertSession()->waitForField('credit_card_number');
    $this->getSession()->getPage()->fillField('Card Number', '4222222222222220');
    $this->getSession()->getPage()->fillField('Security Code', '123');
    $this->getSession()->getPage()->selectFieldOption('credit_card_exp_date[M]', '11');
    $this_year = date('Y');
    $this->getSession()->getPage()->selectFieldOption('credit_card_exp_date[Y]', $this_year + 1);
    $billingValues = [
      'first_name' => 'Frederick',
      'last_name' => 'Pabst',
      'street_address' => '123 Milwaukee Ave',
      'city' => 'Milwaukee',
      'country' => '1228',
      'state_province' => '1048',
      'postal_code' => '53177',
    ];
    $this->fillBillingFields($billingValues);
    $this->getSession()->getPage()->pressButton('Submit');
    $this->htmlOutput();
    $this->assertSession()->pageTextContains('New submission added to CiviCRM Webform Test.');
    $this->assertPageNoErrorMessages();

    // Assert if recur is attached to the created membership.
    $utils = \Drupal::service('webform_civicrm.utils');
    $api_result = $utils->wf_civicrm_api('membership', 'get', [
      'sequential' => 1,
      'return' => 'contribution_recur_id',
    ]);
    $membership = reset($api_result['values']);
    $this->assertNotEmpty($membership['contribution_recur_id']);

    // Let's make sure we have a Contribution by ensuring we have a Transaction ID
    $api_result = $utils->wf_civicrm_api('contribution', 'get', [
      'sequential' => 1,
    ]);
    $contribution = reset($api_result['values']);
    $this->assertNotEmpty($contribution['trxn_id']);
    $this->assertEquals('1.00', $contribution['total_amount']);
  }

  /**
   * Test submitting a Free Membership
   */
  public function testSubmitWebform() {
    $this->createMembershipType();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet(Url::fromRoute('entity.webform.civicrm', [
      'webform' => $this->webform->id(),
    ]));
    // The label has a <div> in it which can cause weird failures here.
    $this->assertSession()->waitForText('Enable CiviCRM Processing');
    $this->assertSession()->waitForField('nid');
    $this->getSession()->getPage()->checkField('nid');
    $this->getSession()->getPage()->clickLink('Memberships');

    $this->getSession()->getPage()->selectFieldOption('membership_1_number_of_membership', 1);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->htmlOutput();

    $this->getSession()->getPage()->pressButton('Save Settings');
    $this->assertSession()->pageTextContains('Saved CiviCRM settings');

    $this->drupalLogout();
    $this->drupalGet($this->webform->toUrl('canonical'));
    $this->assertPageNoErrorMessages();

    $this->assertSession()->waitForField('First Name');

    $this->getSession()->getPage()->fillField('First Name', 'Frederick');
    $this->getSession()->getPage()->fillField('Last Name', 'Pabst');

    $this->getSession()->getPage()->pressButton('Submit');

    $this->assertPageNoErrorMessages();
    $this->assertSession()->pageTextContains('New submission added to CiviCRM Webform Test.');

    $api_result = \Drupal::service('webform_civicrm.utils')->wf_civicrm_api('membership', 'get', [
      'sequential' => 1,
    ]);
    $this->assertEquals(1, $api_result['count']);
    $membership = reset($api_result['values']);

    $this->assertEquals('Basic', $membership['membership_name']);
    $this->assertEquals('1', $membership['status_id']);

    $today = date('Y-m-d');
    // throw new \Exception(var_export($today, TRUE));

    $this->assertEquals($today,  $membership['join_date']);
    $this->assertEquals($today,  $membership['start_date']);

    $this->assertEquals(date('Y-m-d', strtotime($today. ' +364 days')),  $membership['end_date']);
  }

  /**
   * Test submitting a Membership using query params
   */
  public function testSubmitMembershipQueryParams() {
    $this->createMembershipType(1, TRUE, 'Basic');
    $this->createMembershipType(1, TRUE, 'Basic Plus');

    $this->drupalLogin($this->rootUser);
    $this->drupalGet(Url::fromRoute('entity.webform.civicrm', [
      'webform' => $this->webform->id(),
    ]));
    $this->enableCivicrmOnWebform();

    // Configure Membership tab.
    $this->getSession()->getPage()->clickLink('Memberships');
    $this->getSession()->getPage()->selectFieldOption('membership_1_number_of_membership', 1);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('civicrm_1_membership_1_membership_membership_type_id', '- User Select -');
    $this->htmlOutput();

    $this->saveCiviCRMSettings();

    $this->drupalGet($this->webform->toUrl('edit-form'));
    $this->assertSession()->waitForField('CiviCRM Options');

    // Add the Default -> [current-page:query:membership]
    $membershipElementEdit = $this->assertSession()->elementExists('css', '[data-drupal-selector="edit-webform-ui-elements-civicrm-1-membership-1-membership-membership-type-id-operations"] a.webform-ajax-link');
    $membershipElementEdit->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->checkField('properties[extra][aslist]');
    $this->assertSession()->checkboxChecked('properties[extra][aslist]');

    $this->htmlOutput();

    $this->getSession()->getPage()->clickLink('Advanced');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->htmlOutput();
    $fieldset = $this->assertSession()->elementExists('css', '[data-drupal-selector="edit-default"]');
    $fieldset->click();
    $this->getSession()->getPage()->fillField('Default value', '[current-page:query:membership]');
    $this->getSession()->getPage()->pressButton('Save');

    $this->drupalLogout();
    $this->drupalGet($this->webform->toUrl('canonical', ['query' => ['membership' => 2]]));
    $this->htmlOutput();
    // ToDo ->
    $this->assertPageNoErrorMessages();

    $this->assertSession()->waitForField('First Name');
    $this->getSession()->getPage()->fillField('First Name', 'Frederick');
    $this->getSession()->getPage()->fillField('Last Name', 'Pabst');
    $this->assertSession()->pageTextContains('Basic Plus');
    $this->getSession()->getPage()->pressButton('Submit');
    $this->htmlOutput();
    // ToDo ->
    $this->assertPageNoErrorMessages();

    $this->assertSession()->pageTextContains('New submission added to CiviCRM Webform Test.');

    $api_result = \Drupal::service('webform_civicrm.utils')->wf_civicrm_api('membership', 'get', [
      'sequential' => 1,
    ]);
    $this->assertEquals(1, $api_result['count']);
    $membership = reset($api_result['values']);

    $this->assertEquals('Basic Plus', $membership['membership_name']);
    $this->assertEquals('1', $membership['status_id']);
  }

  /**
   * Test submitting Membership with different Financial Types
   * (5% and 13% Sales Tax - based on province)
   */
  public function testMembershipVaryingSalesTax() {
    // Add Canada
    \Civi::settings()->set('countryLimit', [1228, 1039]);
    // Make it the default country
    \Civi::settings()->set('defaultContactCountry', 1039);

    // Create Financial Type and Attach Sales Tax to it
    $this->createFinancialType('Member5');
    $this->setupSalesTax(5, $accountParams = [], 5);
    // Create Financial Type and Attach Sales Tax to it
    $this->createFinancialType('Member13');
    $this->setupSalesTax(6, $accountParams = [], 13);

    $this->createMembershipType(100, FALSE, 'Basic', 'Member Dues');

    $payment_processor = $this->createPaymentProcessor();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet(Url::fromRoute('entity.webform.civicrm', [
      'webform' => $this->webform->id(),
    ]));
    $this->enableCivicrmOnWebform();
    // Enable Address fields.
    $this->getSession()->getPage()->selectFieldOption('contact_1_number_of_address', 1);
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Configure Contribution tab.
    $this->configureContributionTab(FALSE, $payment_processor['id'], '2');
    $this->htmlOutputDirectory = '/Applications/MAMP/htdocs/d9civicrm.local/web/sites/default/files/simpletest/';
    $this->createScreenshot($this->htmlOutputDirectory . 'KG0.png');

    // Configure Membership tab.
    $this->getSession()->getPage()->clickLink('Memberships');
    $this->getSession()->getPage()->selectFieldOption('membership_1_number_of_membership', 1);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('civicrm_1_membership_1_membership_membership_type_id', 'Basic');
    $this->getSession()->getPage()->selectFieldOption('civicrm_1_membership_1_membership_financial_type_id', '- User Select -');
    // $this->getSession()->getPage()->checkField('Membership Fee');

    $this->htmlOutput();

    $this->htmlOutputDirectory = '/Applications/MAMP/htdocs/d9civicrm.local/web/sites/default/files/simpletest/';
    $this->createScreenshot($this->htmlOutputDirectory . 'KG1.png');

    $this->getSession()->getPage()->pressButton('Save Settings');
    $this->assertSession()->pageTextContains('Saved CiviCRM settings');

    $this->drupalLogout();
    $this->drupalGet($this->webform->toUrl('canonical'));
    $this->assertPageNoErrorMessages();

    $this->assertSession()->waitForField('First Name');

    $this->getSession()->getPage()->fillField('First Name', 'Albert');
    $this->getSession()->getPage()->fillField('Last Name', 'Alberta');
    $this->getSession()->getPage()->fillField('Street Address', '39 Street');
    $this->getSession()->getPage()->fillField('City', 'Calgary');
    $this->getSession()->getPage()->fillField('Postal Code', 'A0B 1C2');
    $this->getSession()->getPage()->selectFieldOption('civicrm_1_contact_1_address_state_province_id', 'AB');
    $this->getSession()->getPage()->fillField('Email', 'alberta@example.com');

    // Financial Type id for Member5 = 5
    $this->getSession()->getPage()->selectFieldOption('civicrm_1_membership_1_membership_financial_type_id', '5');

    $this->htmlOutputDirectory = '/Applications/MAMP/htdocs/d9civicrm.local/web/sites/default/files/simpletest/';
    $this->createScreenshot($this->htmlOutputDirectory . 'KG2.png');

    $this->getSession()->getPage()->pressButton('Next >');

    $this->fillCardAndSubmit();

    // KG
    $this->htmlOutputDirectory = '/Applications/MAMP/htdocs/d9civicrm.local/web/sites/default/files/simpletest/';
    $this->createScreenshot($this->htmlOutputDirectory . 'KG3.png');

    $this->assertPageNoErrorMessages();
    $this->assertSession()->pageTextContains('New submission added to CiviCRM Webform Test.');

    // Ok let's check the results
    $api_result = $this->utils->wf_civicrm_api('contribution', 'get', [
      'sequential' => 1,
    ]);
    $this->assertEquals(1, $api_result['count']);
    $contribution = reset($api_result['values']);
    $this->assertNotEmpty($contribution['trxn_id']);
    $this->assertEquals($this->webform->label(), $contribution['contribution_source']);
    $this->assertEquals('Member Dues', $contribution['financial_type']);
    $this->assertEquals('105.00', $contribution['total_amount']);
    $this->assertEquals('Completed', $contribution['contribution_status']);

    // Also retrieve tax_amount (have to ask for it to be returned)
    $api_result = $this->utils->wf_civicrm_api('contribution', 'get', [
      'sequential' => 1,
      'return' => 'tax_amount',
    ]);
    $contribution = reset($api_result['values']);
    $this->assertEquals('5.00', $contribution['tax_amount']);
    $tax_total_amount = $contribution['tax_amount'];

    $api_result = $this->utils->wf_civicrm_api('line_item', 'get', [
      'sequential' => 1,
    ]);
    $line_items = reset($api_result['values']);

    // throw new \Exception(var_export($api_result, TRUE));

    $this->assertEquals('1.00', $line_items['qty']);
    $this->assertEquals('100.00', $line_items['unit_price']);
    $this->assertEquals('100.00', $line_items['line_total']);
    $this->assertEquals('5.00', $line_items['tax_amount']);
    $this->assertEquals('5', $line_items['financial_type_id']);

    // 'price_field_id' => '2',

  }
}
