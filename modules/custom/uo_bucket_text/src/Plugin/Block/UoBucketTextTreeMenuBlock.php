<?php

namespace Drupal\uo_bucket_text\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Provides a Tree Menu Block.
 *
 * @Block(
 *   id = "uo_bucket_text_tree_menu_block",
 *   admin_label = @Translation("UO Bucket Text Tree Menu Block"),
 *   category = @Translation("Menus"),
 * )
 */
class UoBucketTextTreeMenuBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The menu link tree service.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuTree;

  /**
   * The active menu trail service.
   *
   * @var \Drupal\Core\Menu\MenuActiveTrailInterface
   */
  protected $menuActiveTrail;


  /**
   * Constructs a new UoBucketTextTreeMenuBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menu_tree
   *   The menu tree service.
   * @param \Drupal\Core\Menu\MenuActiveTrailInterface $menu_active_trail
   *   The active menu trail service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MenuLinkTreeInterface $menu_tree, MenuActiveTrailInterface $menu_active_trail) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->menuTree = $menu_tree;
    $this->menuActiveTrail = $menu_active_trail;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('menu.link_tree'),
      $container->get('menu.active_trail')
    );
  }

  /**
   * {@inheritdoc}
   */  
  public function defaultConfiguration() {
    return [
      'uo_bucket_text_tree_menu_block_menu_name' => $this->t('main'),
      'uo_bucket_text_tree_menu_block_menu_level' => 1,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $config = $this->configuration;

    $defaults = $this->defaultConfiguration();

    $form['uo_bucket_text_tree_menu_block_menu_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Menu Name'),
      '#description' => $this->t('Please provide the menu machine name.'),
      '#default_value' => $config['uo_bucket_text_tree_menu_block_menu_name'] ?? $defaults['uo_bucket_text_tree_menu_block_menu_name'],
    ];
    $options = range(0, $this->menuTree->maxDepth());
    unset($options[0]);
    $form['uo_bucket_text_tree_menu_block_menu_level'] = [
      '#type' => 'select',
      '#title' => $this->t('Initial visibility level'),
      '#description' => $this->t('The menu is only visible if the menu link for the current page is at this level or below it. Use level 1 to always display this menu.'),
      '#default_value' => $config['uo_bucket_text_tree_menu_block_menu_level'] ?? $defaults['uo_bucket_text_tree_menu_block_menu_level'],
      '#options' => $options,
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->configuration['uo_bucket_text_tree_menu_block_menu_name'] = $values['uo_bucket_text_tree_menu_block_menu_name'];
    $this->configuration['uo_bucket_text_tree_menu_block_menu_level'] = $values['uo_bucket_text_tree_menu_block_menu_level'];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Build up tree menus.
    $config = $this->configuration;
    $defaults = $this->defaultConfiguration();
    $menu_name = $config['uo_bucket_text_tree_menu_block_menu_name'] ?? $defaults['uo_bucket_text_tree_menu_block_menu_name'];
    $init_level = $config['uo_bucket_text_tree_menu_block_menu_level'] ?? $defaults['uo_bucket_text_tree_menu_block_menu_level'];
    
    $parameters = $this->menuTree->getCurrentRouteMenuTreeParameters($menu_name);
    $parameters->setMinDepth($init_level);

    // For menu blocks with start level greater than 1, only show menu items
    // from the current active trail. Adjust the root according to the current
    // position in the menu in order to determine if we can show the subtree.
    if ($init_level > 1) {
      if (count($parameters->activeTrail) >= $init_level) {
        // Active trail array is child-first. Reverse it, and pull the new menu
        // root based on the parent of the configured start level.
        $menu_trail_ids = array_reverse(array_values($parameters->activeTrail));
        $menu_root = $menu_trail_ids[$init_level - 1];
        $parameters->setRoot($menu_root)->setMinDepth(1);
      }
      else {
        return [];
      }
    }

    $tree = $this->menuTree->load($menu_name, $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuTree->transform($tree, $manipulators);
    $menu = $this->menuTree->build($tree);
    $menu['#title'] = [
      '#markup' => $this->getActiveTrailRootTitle(),
    ];
    return $menu;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $config = $this->configuration;
    $defaults = $this->defaultConfiguration();
    // Even when the menu block renders to the empty string for a user, we want
    // the cache tag for this menu to be set: whenever the menu is changed, this
    // menu block must also be re-rendered for that user, because maybe a menu
    // link that is accessible for that user has been added.
    $cache_tags = parent::getCacheTags();
    $cache_tags[] = 'config:system.menu.' . $config['uo_bucket_text_tree_menu_block_menu_name'] ?? $defaults['uo_bucket_text_tree_menu_block_menu_name'];
    return $cache_tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $config = $this->configuration;
    $defaults = $this->defaultConfiguration();
    // ::build() uses MenuLinkTreeInterface::getCurrentRouteMenuTreeParameters()
    // to generate menu tree parameters, and those take the active menu trail
    // into account. Therefore, we must vary the rendered menu by the active
    // trail of the rendered menu.
    // Additional cache contexts, e.g. those that determine link text or
    // accessibility of a menu, will be bubbled automatically.
    $menu_name = $config['uo_bucket_text_tree_menu_block_menu_name'] ?? $defaults['uo_bucket_text_tree_menu_block_menu_name'];
    return Cache::mergeContexts(parent::getCacheContexts(), ['route.menu_active_trails:' . $menu_name]);
  }

  /**
   * Gets the current menu item's root menu item title.
   *
   * @return string|null
   *   The root menu item title or NULL if there's no active item.
   */
  protected function getActiveTrailRootTitle() {
    /** @var array $active_trail_ids */
    $active_trail_ids = $this->getDerivativeActiveTrailIds();
    if ($active_trail_ids) {
      return $this->getLinkTitleFromLink(end($active_trail_ids));
    }
  }

  /**
   * Gets an array of the active trail menu link items.
   *
   * @return array
   *   The active trail menu item IDs.
   */
  protected function getDerivativeActiveTrailIds() {
    $menu_id = $this->getDerivativeId();
    return array_filter($this->menuActiveTrail->getActiveTrailIds($menu_id));
  }
  /**
   * Gets the title of a given menu item ID.
   *
   * @param string $link_id
   *   The menu item ID.
   *
   * @return string|null
   *   The menu item title or NULL if the given menu item can't be found.
   */
  protected function getLinkTitleFromLink($link_id) {
    $config = $this->configuration;
    $defaults = $this->defaultConfiguration();
    $menu_name = $config['uo_bucket_text_tree_menu_block_menu_name'] ?? $defaults['uo_bucket_text_tree_menu_block_menu_name'];
    $parameters = $this->menuTree->getCurrentRouteMenuTreeParameters($menu_name);
    $menu = $this->menuTree->load($menu_name, $parameters);
    $link = $this->findLinkInTree($menu, $link_id);
    if ($link) {
      return $link->link->getTitle();
    }
  }

  /**
   * Gets the menu link item from the menu tree.
   *
   * @param array $menu_tree
   *   Associative array containing the menu link tree data.
   * @param string $link_id
   *   Menu link id to find.
   *
   * @return \Drupal\Core\Menu\MenuLinkTreeElement|null
   *   The link element from the given menu tree or NULL if it can't be found.
   */
  protected function findLinkInTree(array $menu_tree, $link_id) {
    if (isset($menu_tree[$link_id])) {
      return $menu_tree[$link_id];
    }
    /** @var \Drupal\Core\Menu\MenuLinkTreeElement $link */
    foreach ($menu_tree as $link) {
      $link = $this->findLinkInTree($link->subtree, $link_id);
      if ($link) {
        return $link;
      }
    }
  }

}
