<?php

namespace Drupal\menu_block_current_language\Plugin\Block;

use Drupal\system\Plugin\Block\SystemMenuBlock;

/**
 * Provides a generic Menu block.
 *
 * @Block(
 *   id = "menu_block_current_language",
 *   admin_label = @Translation("Menu block current language: Menu"),
 *   category = @Translation("Menu block current language"),
 *   deriver = "Drupal\system\Plugin\Derivative\SystemMenuBlock"
 * )
 */
class MenuBlockCurrentLanguage extends SystemMenuBlock {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $menu_name = $this->getDerivativeId();
    $parameters = $this->menuTree->getCurrentRouteMenuTreeParameters($menu_name);

    // Adjust the menu tree parameters based on the block's configuration.
    $level = $this->configuration['level'];
    $depth = $this->configuration['depth'];
    $parameters->setMinDepth($level);
    // When the depth is configured to zero, there is no depth limit. When depth
    // is non-zero, it indicates the number of levels that must be displayed.
    // Hence this is a relative depth that we must convert to an actual
    // (absolute) depth, that may never exceed the maximum depth.
    if ($depth > 0) {
      $parameters->setMaxDepth(min($level + $depth - 1, $this->menuTree->maxDepth()));
    }

    $tree = $this->menuTree->load($menu_name, $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
      ['callable' => 'menu_block_current_language_tree_manipulator::filterLanguages'],
    ];
    $tree = $this->menuTree->transform($tree, $manipulators);

    return $this->menuTree->build($tree);
  }

}
