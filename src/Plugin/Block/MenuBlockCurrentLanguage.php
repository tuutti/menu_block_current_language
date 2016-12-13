<?php

namespace Drupal\menu_block_current_language\Plugin\Block;

use Drupal\Core\Form\FormStateInterface;
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
      [
        'callable' => 'menu_block_current_language_tree_manipulator::filterLanguages',
        'args' => [$this->configuration['translation_providers']],
      ],
    ];
    $tree = $this->menuTree->transform($tree, $manipulators);

    return $this->menuTree->build($tree);
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['translation_providers'] = $form_state->getValue('translation_providers');
    parent::blockSubmit($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $form['translation_providers'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Enabled Core link types'),
      '#options' => [
        'menu_link_content' => $this->t('Menu link content'),
        'views' => $this->t('Views'),
        'default' => $this->t('String translation (Experimental)'),
      ],
      '#default_value' => $this->configuration['translation_providers'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    // Translate views and menu link content links by default.
    $config = [
      'translation_providers' => [
        'views' => 'views',
        'menu_link_content' => 'menu_link_content',
        'default' => 0,
      ],
    ];
    return $config + parent::defaultConfiguration();
  }

}
