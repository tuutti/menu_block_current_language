<?php

namespace Drupal\menu_block_current_language;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\menu_link_content\Plugin\Menu\MenuLinkContent;

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
   * MenuLinkTreeManipulator constructor.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(LanguageManagerInterface $language_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->languageManager = $language_manager;
    $this->entityTypeManager = $entity_type_manager;
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
    foreach ($tree as $index => $item) {
      // This only works with translated menu links.
      if (!$item->link instanceof MenuLinkContent || !$entity = $this->getEntity($item->link)) {
        continue;
      }
      /** @var \Drupal\menu_link_content\Entity\MenuLinkContent $entity */
      if (!$entity->isTranslatable()) {
        // Skip untranslatable items.
        continue;
      }
      if (!$entity->hasTranslation($this->languageManager->getCurrentLanguage()->getId())) {
        unset($tree[$index]);
      }
    }
    return $tree;
  }

}