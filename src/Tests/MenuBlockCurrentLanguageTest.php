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

    $this->drupalGet('test-page');
    $this->assertLink($link->label());

    // No french translation, test that link is not visible.
    $url = Url::fromRoute('test_page_test.test_page', [
      'language' => 'fr',
    ]);
    $this->drupalGet($url);
    $this->assertNoLink($link->label());

    // Add translation and test that links gets visible.
    $link->addTranslation('fr', ['title' => 'French title'])->save();
    $this->drupalGet($url);
    $this->assertLink('French title');

    // Test that untranslatable link is visible for both languages.
    foreach ([LanguageInterface::LANGCODE_NOT_APPLICABLE, LanguageInterface::LANGCODE_NOT_SPECIFIED] as $langcode) {
      $link = $this->createTestLink($langcode);
      $this->drupalGet('test-page');
      $this->assertLink($link->label());

      $this->drupalGet($url);
      $this->assertLink($link->label());
    }
  }

}