<?php

namespace Drupal\menu_block_current_language\Tests;

use Drupal\content_translation\Tests\ContentTranslationTestBase;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\menu_link_content\Entity\MenuLinkContent;

/**
 * Functional tests for menu_block_current_language.
 *
 * @group menu_block_current_language
 */
class MenuBlockCurrentLanguageTest extends ContentTranslationTestBase {

  /**
   * {@inheritdoc}
   */
  protected $entityTypeId = 'menu_link_content';

  /**
   * {@inheritdoc}
   */
  protected $bundle = 'menu_link_content';

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'language',
    'content_translation',
    'block',
    'test_page_test',
    'menu_ui',
    'menu_link_content',
    'menu_block_current_language',
    'menu_block_current_language_views_test',
  ];

  /**
   * A user with permission to access admin pages and administer languages.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * A non-administrator user for this test.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $regularUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser(array('administer languages', 'access administration pages'));
    // User to check non-admin access.
    $this->regularUser = $this->drupalCreateUser();

    $this->drupalLogin($this->adminUser);

    $edit = [
      'language_interface[enabled][language-session]' => TRUE,
      'language_interface[weight][language-session]' => -12,
    ];
    $this->drupalPostForm('admin/config/regional/language/detection', $edit, t('Save settings'));
    $this->placeBlock('menu_block_current_language:main');
  }

  /**
   * {@inheritdoc}
   */
  protected function setupUsers() {}

  /**
   * {@inheritdoc}
   */
  protected function setupTestFields() {}

  /**
   * Create new menu link.
   *
   * @param string $langcode
   *   The language code.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The menu link.
   */
  protected function createTestLink($langcode) {
    $link = MenuLinkContent::create([
      'menu_name' => 'main',
      'title' => $this->randomString(),
      'langcode' => $langcode,
      'link' => [
        'uri' => 'internal:/test-page',
      ],
    ]);
    $link->save();

    return $link;
  }

  /**
   * Tests that menu links are only visible for translated languages.
   */
  public function testSingleLanguage() {
    $link = $this->createTestLink('en');

    $this->drupalGet('test-page', ['query' => ['language' => 'en']]);
    $this->assertLink($link->label());

    $this->drupalGet('test-page', ['query' => ['language' => 'fr']]);
    $this->assertNoLink($link->label());

    // Add translation and test that links gets visible.
    $link->addTranslation('fr', ['title' => 'French title'])->save();
    $this->drupalGet('test-page', ['query' => ['language' => 'fr']]);
    $this->assertLink('French title');

    // French link should not be visible to english.
    $this->drupalGet('test-page', ['query' => ['language' => 'en']]);
    $this->assertNoLink('French title');

    // Test French only link.
    $link = $this->createTestLink('fr');
    $this->drupalGet('test-page', ['query' => ['language' => 'en']]);
    $this->assertNoLink($link->label());

    // Test that untranslatable link is visible for both languages.
    foreach ([LanguageInterface::LANGCODE_NOT_APPLICABLE, LanguageInterface::LANGCODE_NOT_SPECIFIED] as $langcode) {
      $link = $this->createTestLink($langcode);

      foreach (['fr', 'en'] as $lang) {
        $this->drupalGet('test-page', ['query' => ['language' => $lang]]);
        $this->assertLink($link->label());
      }
    }

    // Test that views menu link is visible for english.
    $this->drupalGet('test-view', ['query' => ['language' => 'en']]);
    $this->assertLink('Test menu link');

    // Test that views menu link is not visible for fr without a translation.
    $this->drupalGet('test-view', ['query' => ['language' => 'fr']]);
    $this->assertNoLink('Test menu link');

    /* @var \Drupal\Core\Config\StorageInterface $sync */
    $sync = \Drupal::service('config.storage.sync');
    $this->copyConfig(\Drupal::service('config.storage'), $sync);
    /* @var \Drupal\Core\Config\StorageInterface $override_sync */
    $override_sync = $sync->createCollection('language.fr');
    $override_sync->write('views.view.test_view', [
      'display' => [
        'page_1' => [
          'display_options' => ['menu' => ['title' => 'FR Test menu link']],
        ],
      ],
    ]);
    $this->configImporter()->import();
    $this->rebuildContainer();
    \Drupal::service('router.builder')->rebuild();

    // Make sure view title gets translated and english title is not visible.
    $this->drupalGet('test-view', ['query' => ['language' => 'fr']]);
    $this->assertLink('FR Test menu link');

    // Make sure french title is not visible to english page.
    $this->drupalGet('test-view', ['query' => ['language' => 'en']]);
    $this->assertNoLink('FR Test menu link');
  }

}