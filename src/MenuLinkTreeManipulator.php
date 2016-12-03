<?php

namespace Drupal\menu_block_current_language;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\menu_link_content\Plugin\Menu\MenuLinkContent;
use Drupal\views\Plugin\Menu\ViewsMenuLink;

/**
 * Class MenuLinkTreeManipulator.
 *
 * @package Drupal\menu_block_current_language\Menu
 */
class MenuLinkTreeManipulator {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * MenuLinkTreeManipulator constructor.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(LanguageManagerInterface $language_manager, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory) {
    $this->languageManager = $language_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * Load entity with given menu link.
   *
   * @param \Drupal\menu_link_content\Plugin\Menu\MenuLinkContent $link
   *   The menu link.
   *
   * @return bool|\Drupal\Core\Entity\EntityInterface|null
   *   Boolean if menu link has no metadata. NULL if entity not found and
   *   an EntityInterface if found.
   */
  protected function getEntity(MenuLinkContent $link) {
    // MenuLinkContent::getEntity() has protected visibility and cannot be used
    // to directly fetch the entity.
    $metadata = $link->getMetaData();

    if (empty($metadata['entity_id'])) {
      return FALSE;
    }
    return $this->entityTypeManager
      ->getStorage('menu_link_content')
      ->load($metadata['entity_id']);
  }

  /**
   * Filter out links that are not translated to the current language.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[] $tree
   *   The menu link tree to manipulate.
   *
   * @return \Drupal\Core\Menu\MenuLinkTreeElement[]
   *   The manipulated menu link tree.
   */
  public function filterLanguages(array $tree) {
    $current_language = $this->languageManager->getCurrentLanguage()->getId();

    foreach ($tree as $index => $item) {
      $link = $item->link;
      // This only works with translated menu links.
      if ($link instanceof MenuLinkContent && $entity = $this->getEntity($link)) {
        /** @var \Drupal\menu_link_content\Entity\MenuLinkContent $entity */
        if (!$entity->isTranslatable()) {
          // Skip untranslatable items.
          continue;
        }
        if (!$entity->hasTranslation($current_language)) {
          unset($tree[$index]);
        }
        continue;
      }
      elseif ($link instanceof ViewsMenuLink) {
        $view_id = sprintf('views.view.%s', $link->getMetaData()['view_id']);

        // Make sure that original configuration exists for given view.
        if (!$original = $this->configFactory->get($view_id)->get('langcode')) {
          continue;
        }
        // ConfigurableLanguageManager::getLnguageConfigOverride() always
        // returns a new configuration override for the original language.
        if ($current_language === $original) {
          continue;
        }
        /** @var \Drupal\language\Config\LanguageConfigOverride $config */
        $config = $this->languageManager->getLanguageConfigOverride($current_language, $view_id);
        // Configuration override will be marked as a new if it does not
        // exist (thus has no translation).
        if ($config->isNew()) {
          unset($tree[$index]);
        }
        continue;
      }
    }
    return $tree;
  }

}