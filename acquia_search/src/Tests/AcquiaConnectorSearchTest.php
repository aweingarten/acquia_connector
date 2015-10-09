<?php

/**
 * @file
 * Definition of Drupal\acquia_search\Tests\AcquiaConnectorSearchTest.
 */

namespace Drupal\acquia_search\Tests;

use Drupal\simpletest\WebTestBase;
use Drupal\acquia_search\EventSubscriber;
use Drupal\search_api\Entity\Server;

/**
 * Tests the functionality of the Acquia Search module.
 *
 * @group Acquia search
 */
class AcquiaConnectorSearchTest extends WebTestBase {
  protected $strictConfigSchema = FALSE;
  protected $id;
  protected $key;
  protected $salt;
  protected $derivedKey;
  protected $url;
  protected $server;
  protected $index;
  protected $settings_path;
  protected $acquia_search_environment_id = 'acquia_search';


  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('acquia_connector', 'search_api', 'search_api_solr', 'toolbar', 'acquia_connector_test', 'node', 'shortcut');

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    // Generate and store a random set of credentials.
    $this->id = $this->randomString(10);
    $this->key = $this->randomString(32);
    $this->salt = $this->randomString(32);
    $this->server = 'acquia_search_server';
    $this->index = 'acquia_search_index';
    $this->settings_path = 'admin/config/search/search-api';

    // Create a new content type
    $content_type = $this->drupalCreateContentType();

    // Add a node of the new content type.
    $node_data = array(
      'type' => $content_type->id(),
    );

    //create node
    $this->drupalCreateNode($node_data);

    //connect
    $this->connect();

    $subscription = array(
      'timestamp' => REQUEST_TIME - 60,
      'active' => '1',
    );
  }

  /**
   * Connect.
   */
  public function connect(){
    \Drupal::configFactory()->getEditable('acquia_connector.settings')->set('spi.ssl_verify', FALSE)->save();
    \Drupal::configFactory()->getEditable('acquia_connector.settings')->set('spi.ssl_override', TRUE)->save();

    $admin_user = $this->createAdminUser();
    $this->drupalLogin($admin_user);

    $edit_fields = array(
      'acquia_identifier' => $this->randomString(8),
      'acquia_key' => $this->randomString(8),
    );

    $submit_button = 'Connect';
    $this->drupalPostForm('admin/config/system/acquia-connector/credentials', $edit_fields, $submit_button);

    \Drupal::service('module_installer')->install(array('acquia_search'));
    drupal_flush_all_caches();
  }

  /**
   * Creates an admin user.
   */
  public function createAdminUser() {
    $permissions = array(
      'administer site configuration',
      'access administration pages',
      'access toolbar',
      'administer search_api',
    );
    return $this->drupalCreateUser($permissions);
  }

  /**
   * Tests Acquia Search environment creation.
   *
   * Tests executed:
   * - Acquia Search environment is saved and loaded.
   * - Acquia Search environment is set as the default environment when created.
   * - The service class is set to AcquiaSearchService.
   * - The environment's URL is built as expected.
   */
  public function testEnvironment() {
    // Connect site on key and id.
    $this->drupalGet('admin/config/search/search-api');
    $environment =  Server::load('acquia_search_server');
    // Check if the environment is a valid variable.
    $this->assertTrue($environment, t('Acquia Search environment saved.'), 'Acquia Search');
  }

  /**
   * Tests that the Acquia Search environment shows up in the interface and that
   * administrators cannot delete it.
   *
   * Tests executed:
   * - Acquia Search environment is present in the UI.
   * - Admin user receives 403 when attempting to delete the environment.
   */
  public function testEnvironmentUI() {
    $this->drupalGet($this->settings_path);
    // Check the Acquia Search Server is displayed.
    $this->assertLinkByHref('/admin/config/search/search-api/server/' . $this->server, 0, t('The Acquia Search Server is displayed in the UI.'));
    // Check the Acquia Search Index is displayed.
    $this->assertLinkByHref('/admin/config/search/search-api/index/' . $this->index, 0, t('The Acquia Search Index is displayed in the UI.'));
    // Delete the environment.
    $this->drupalGet('/admin/config/search/search-api/server/' . $this->server . '/edit');
    $this->clickLink('Delete', 0);
    $this->assertResponse(403, t('The Acquia Search environment cannot be deleted via the UI.'));
  }

  /**
   * Tests  Acquia Search Server UI
   *
   * Test executed:
   * - Check backend server
   * - Сheck all fields on the existence of
   * - Admin user receives 403 when attempting to delete the server.
   */
  public function testAcquiaSearchServerUI() {
    $settings_path = 'admin/config/search/search-api';
    $this->drupalGet($settings_path);
    $this->clickLink('Edit', 0);
    // Check backend server.
    $this->assertText('Backend', t('The Backend checkbox label exists'), 'Acquia Search');
    $this->assertFieldChecked('edit-backend-search-api-solr-acquia', t('Is used as a backend  Acquia Solr'), 'Acquia Search');
    // Check field Solr server URI.
    $this->assertText('Solr server URI', t('The Solr server URI label exist'), 'Acquia Search');
    // Check http-protocol.
    $this->assertText('HTTP protocol', t('The HTTP protocol label exists'), 'Acquia Search');
    $this->assertOptionSelected('edit-backend-config-scheme', 'http', t('By default selected HTTP protocol'), 'Acquia Search');
    // Check Solr host, port, path.
    $this->assertText('Solr host', t('The Solr host  label exist'), 'Acquia Search');
    $this->assertText('Solr port',  t('The Solr port label exist'), 'Acquia Search');
    $this->assertText('Solr path', t('The Solr path label exist'), 'Acquia Search');
    // Check Basic HTTP authentication.
    $this->assertText('Basic HTTP authentication', t('The basic HTTP authentication label exist'), 'Acquia Search');
    // Ckeck Solr version override.
    $this->assertText('Solr version override', t('The selectbox "Solr version label" exist'), 'Acquia Search');
    $this->assertOptionSelected('edit-backend-config-advanced-solr-version', '4', t('By default selected Solr version 4.x'), 'Acquia Search');
    // Ckeck HTTP method.
    $this->assertText('HTTP method', t('The HTTP method label exist'));
    $this->assertOptionSelected('edit-backend-config-advanced-http-method', 'AUTO', t('By default selected AUTO HTTP method'), 'Acquia Search');
    // Server save.
    $this->drupalPostForm('/admin/config/search/search-api/server/' . $this->server . '/edit', array(), 'Save');
    // Delete server.
    $this->drupalGet('/admin/config/search/search-api/server/' . $this->server . '/delete');
    $this->assertResponse(403, t('The Acquia Search Server cannot be deleted via the UI.'));
  }

  /**
   * Tests  Acquia Search Server UI
   *
   * Test executed:
   * - Сheck all fields on the existence of
   * - Check fields used for indexing
   * - Check save index
   * - Admin user receives 403 when attempting to delete the index.
   */
  public function testAcquiaSearchIndexUI() {
    $settings_path = 'admin/config/search/search-api';
    $this->drupalGet($settings_path);
    $this->clickLink('Edit', 1);
    //Check field data types
    $this->assertText('Data sources', t('The Data types label exist'), 'Acquia Search');
    //Check default selected server
    $this->assertFieldChecked('edit-server-acquia-search-server', t('By default selected Acquia Search Server'), 'Acquia Search');
    //Check fields used for indexing
    $this->drupalGet('/admin/config/search/search-api/index/' . $this->index . '/fields');
    $this->assertFieldChecked('edit-fields-entitynodebody-indexed', t('Body used for searching'), 'Acquia Search');
    $this->assertFieldChecked('edit-fields-entitynodetitle-indexed', t('Title used for searching'), 'Acquia Search');
    //Save index
    $this->drupalPostForm('/admin/config/search/search-api/index/' . $this->index . '/edit', array(), 'Save');
    //Delete index
    $this->drupalGet('/admin/config/search/search-api/index/' . $this->index . '/delete');
    $this->assertResponse(403, t('The Acquia Search Server cannot be deleted via the UI.'));
  }
}
