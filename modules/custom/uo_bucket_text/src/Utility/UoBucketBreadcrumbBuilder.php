<?php

namespace Drupal\uo_bucket_text\Utility;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\entityqueue\Entity\EntitySubqueue;
use Drupal\taxonomy\Entity\Term;

/**
 * Class UoBucketBreadcrumbBuilder renders breadcrumbs.
 *
 * @package Drupal\uo_bucket_text\Utility
 */
class UoBucketBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  /**
   * The access check service.
   *
   * @var \Drupal\Core\Access\AccessManagerInterface
   */
  protected $accessManager;

  /**
   * The current user object.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The menu link manager interface.
   *
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface
   */
  protected $menuLinkManager;

  /**
   * Constructs the PathBasedBreadcrumbBuilder.
   *
   * @param \Drupal\Core\Access\AccessManagerInterface $access_manager
   *   The access check service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user object.
   * @param \Drupal\Core\Menu\MenuLinkManagerInterface $menu_link_manager
   *   The menu link service.
   */
  public function __construct(AccessManagerInterface $access_manager, AccountInterface $current_user, MenuLinkManagerInterface $menu_link_manager) {
    $this->accessManager = $access_manager;
    $this->currentUser = $current_user;
    $this->menuLinkManager = $menu_link_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    $parameters = $route_match->getParameters()->all();

    // For a bucket text label return TRUE.
    if (!empty($parameters['name']) && !empty($parameters['label'])) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();
    $access = $this->accessManager->check($route_match, $this->currentUser, NULL, TRUE);
    $breadcrumb->addCacheableDependency($access);
    $breadcrumb->addCacheContexts(['url', 'route', 'url.path', 'languages']);

    $parameters = $route_match->getParameters()->all();
    $links = [];

    if (!empty($parameters['name']) && !empty($parameters['label'])) {
      // Label.
      $term = Term::load($parameters['label']);
      $label_text = ($term) ? $term->get('name')->value : '';
      $links[] = Link::fromTextAndUrl($label_text, Url::fromRoute('<none>'));

      // Text.
      $queue = EntitySubqueue::load($parameters['name']);
      if (!empty($queue)) {
        $menu_links = $this->menuLinkManager->loadLinksByRoute('uo_bucket_text.text', ['name' => $parameters['name']]);
        $menu_link = reset($menu_links);
        $links[] = Link::fromTextAndUrl($menu_link->getTitle(), Url::fromRoute('uo_bucket_text.text', ['name' => $parameters['name']]));

        $parent_menu = $menu_link->getParent();
        $parent_menu_links = $this->menuLinkManager->getParentIds($parent_menu);
        foreach ($parent_menu_links as $id) {
          $plugin = $this->menuLinkManager->createInstance($id);
          $links[] = Link::fromTextAndUrl($plugin->getTitle(), $plugin->getUrlObject());
          $breadcrumb->addCacheableDependency($plugin);
        }
      }
    }

    $links[] = Link::createFromRoute(t('Home'), '<front>');

    return $breadcrumb->setLinks(array_reverse($links));
  }

}
