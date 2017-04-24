<?php

namespace Drupal\menu_block_current_language\Tests;

use Drupal\content_translation\Tests\ContentTranslationTestBase;
use Drupal\Core\Language\LanguageInterface;
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
    'locale',
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
   * The menu block.
   *
   * @var \Drupal\block\Entity\Block
   */
  protected $menuBlock;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser(['administer languages', 'access administration pages']);
    // User to check non-admin access.
    $this->regularUser = $this->drupalCreateUser();

    $this->drupalLogin($this->adminUser);

    $edit = [
      'language_interface[enabled][language-session]' => TRUE,
      'language_interface[weight][language-session]' => -12,
    ];
    $this->drupalPostForm('admin/config/regional/language/detection', $edit, t('Save settings'));
    $this->menuBlock = $this->placeBlock('menu_block_current_language:main');
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
   * @param string $title
   *   The title.
   * @param array $overrides
   *   The overrides.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The menu link.
   */
  protected function createTestLink($langcode, $title, array $overrides = []) {
    $defaults = [
      'menu_name' => 'main',
      'title' => $title,
      'langcode' => $langcode,
      'link' => [
        'uri' => 'internal:/test-page',
      ],
    ];
    $link = MenuLinkContent::create($overrides + $defaults);
    $link->save();

    return $link;
  }

  /**
   * Tests that menu links are only visible for translated languages.
   */
  public function testMenuBlockLanguageFilters() {
    $config_key = sprintf('block.block.%s', $this->menuBlock->id());

    // Disable content entity links translation.
    $this->config($config_key)->set('settings.translation_providers', [
      'menu_link_content' => '0',
      'views' => 'views',
      'default' => 'default',
    ])->save();

    $link = $this->createTestLink('en', 'First link', [
      'expanded' => 1,
    ]);

    $this->drupalGet('test-page', ['query' => ['language' => 'en']]);
    $this->assertLink($link->label());

    // Make sure menu link is visible for both languages when
    // menu_link_content provider is disabled.
    $this->drupalGet('test-page', ['query' => ['language' => 'fr']]);
    $this->assertLink($link->label());

    // Enable content entity links translation.
    $this->config($config_key)->set('settings.translation_providers', [
      'menu_link_content' => 'menu_link_content',
      'views' => 'views',
      'default' => 'default',
    ])->save();

    // Make sure link is not visible when menu_link_content
    // provider is enabled and no translation is available.
    $this->drupalGet('test-page', ['query' => ['language' => 'fr']]);
    $this->assertNoLink($link->label());

    // Add translation and test that links gets visible.
    $link->addTranslation('fr', ['title' => 'First french title'])->save();
    $this->drupalGet('test-page', ['query' => ['language' => 'fr']]);
    $this->assertLink('First french title');

    // French link should not be visible to english.
    $this->drupalGet('test-page', ['query' => ['language' => 'en']]);
    $this->assertNoLink('First french title');

    // Test French only link.
    $link2 = $this->createTestLink('fr', 'French only title');
    $this->drupalGet('test-page', ['query' => ['language' => 'en']]);
    $this->assertNoLink($link2->label());

    // Test expanded menu links.
    $sublink = $this->createTestLink('en', 'Sublink en', [
      'parent' => $link->getPluginId(),
    ]);
    $this->drupalGet('test-page', ['query' => ['language' => 'en']]);
    $this->assertLink($sublink->label());
    $this->drupalGet('test-page', ['query' => ['language' => 'fr']]);
    $this->assertNoLink($sublink->label());
    $sublink->addTranslation('fr', ['title' => 'French sublink'])->save();
    $this->drupalGet('test-page', ['query' => ['language' => 'fr']]);
    $this->assertLink('French sublink');

    // Test that untranslatable link is visible for both languages.
    foreach ([LanguageInterface::LANGCODE_NOT_APPLICABLE, LanguageInterface::LANGCODE_NOT_SPECIFIED] as $langcode) {
      $link = $this->createTestLink($langcode, 'Untranslated ' . $langcode);

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

    // Disable views links translation.
    $this->config($config_key)->set('settings.translation_providers', [
      'menu_link_content' => 'menu_link_content',
      'views' => '0',
      'default' => 'default',
    ])->save();

    // Test that english views menu link is visible for fr
    // without a translation when provider is disabled.
    $this->drupalGet('test-view', ['query' => ['language' => 'fr']]);
    $this->assertLink('Test menu link');

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

    $this->config($config_key)->set('settings.translation_providers', [
      'menu_link_content' => 'menu_link_content',
      'views' => 'views',
      'default' => 'default',
    ])->save();

    // Make sure untranslated (string) menu link is not visible.
    $this->drupalGet('test-view', ['query' => ['language' => 'fr']]);
    $this->assertNoLink('Home');

    /** @var \Drupal\locale\StringDatabaseStorage $locale_storage */
    $locale_storage = $this->container->get('locale.storage');
    $translations = $locale_storage->getTranslations([], [
      'filters' => ['source' => 'Home'],
    ]);

    /** @var \Drupal\locale\TranslationString $translation */
    foreach ($translations as $translation) {
      if ($translation->source !== 'Home') {
        continue;
      }
      $target = $locale_storage->createTranslation([
        'lid' => $translation->lid,
        'language' => 'fr',
      ]);
      $target->setString('French home')
        ->setCustomized()
        ->save();
      _locale_refresh_translations(['fr'], [$translation->lid]);
    }
    // Make sure translated link is visible and translated link is not visible
    // to wrong language.
    $this->drupalGet('test-page', ['query' => ['language' => 'fr']]);
    $this->assertLink('French home');
    $this->drupalGet('test-page', ['query' => ['language' => 'en']]);
    $this->assertLink('Home');
    $this->assertNoLink('French home');
  }

}
