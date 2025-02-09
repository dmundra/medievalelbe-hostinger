<?php

namespace Drupal\uo_bucket_text\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\entityqueue\Entity\EntitySubqueue;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\uo_bucket_text\Utility\UoBucketTextHelper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Xss;

/**
 * Class UoBucketTextController renders the list of all service requests.
 *
 * @package Drupal\uo_bucket_text\Controller
 */
class UoBucketTextController extends ControllerBase {

  /**
   * The utility helper.
   * 
   * @var Drupal\uo_bucket_text\Utility\UoBucketTextHelper
   */
  protected $helper;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Build an instance of this controller.
   */
  public function __construct(
    UoBucketTextHelper $helper,
    EntityTypeManagerInterface $entityTypeManager
  ) {
    $this->helper = $helper;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container):
  UoBucketTextController {
    $helper = $container->get('uo_bucket_text.helper');
    $entityTypeManager = $container->get('entity_type.manager');
    return new static(
      $helper,
      $entityTypeManager
    );
  }

  /**
   * Dynamic title.
   *
   * @return string
   *   the page title.
   */
  public function getTitle($name = '', $label = FALSE): string {
    $default = "Text";
    if (empty($name)) return $default;
    $queue = EntitySubqueue::load($name);
    if (!empty($queue)) {
      $default = $queue->getTitle();
    }
    if ($label) {
      $term = Term::load($label);
      $default = ($term) ? $term->get('name')->value : '';
    }
    return $default;
  }

  /**
   * Render text page.
   *
   * @return array
   *   Render array for the page.
   */
  public function text($name = ''): array {
    return $this->showTextLabel($name);
  }

  /**
   * Render label page.
   *
   * @return array
   *   Render array for the page.
   */
  public function label($name, $label): array {
    return $this->showTextLabel($name, $label);
  }

  /**
   * Return list of footnotes.
   *
   * @return string
   *   JSON string of footnotes.
   */
  public function footnotes(Request $request) {
    $results = [];
    $input = $request->query->get('q') ?? '';

    // Get the typed string from the URL, if it exists.
    if (!$input) {
      return new JsonResponse($results);
    }

    $input = Xss::filter($input);

    $results = $this->uo_bucket_text_get_footnotes($input);
    // Add tab into the list.
    $results[] = [
      'value' => 'tab',
      'label' => 'Tab'
    ];
    return new JsonResponse($results);
  }

  /**
   * Render text/label page.
   *
   * @return array
   *   Render array for the page.
   */
  public function showTextLabel($name = '', $label = FALSE): array {
    $output['bucket-main-content'] = [
      '#markup' => $this->t('<em>Not found</em>'),
    ];

    if (empty($name)) return $output;

    // Attached the js library.
    $output['#attached']['library'][] = 'uo_bucket_text/uo-bucket-text-highlight-js';
    // Get default color settings from configuration,
    // then pass to JS.
    $default_color = 'inherit';
    $output['#attached']['drupalSettings']['uo_bucket_text']['default_color'] = $default_color;

    // Build up main structure.
    // It will hold bucket text and right sidebar.
    $output['bucket-main-content'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'bucket-main-content',
      ],
      '#weight' => 1,
    ];

    $queue = EntitySubqueue::load($name);
    if (!empty($queue)) {
      $title = $queue->getTitle();
      if ($name == 'raids') $title = 'Incursions';
      // Set up page title.
      $output['#title'] = $this->t($title);
      // Set up text main area.
      $output['bucket-main-content']['text'] = [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'bucket-text'
        ],
        '#weight' => 0,
      ];
      if ($label) {
        $term = Term::load($label);
        $output['bucket-main-content']['text']['subtitle'] = [
          '#type' => 'html_tag',
          '#tag'  => 'h2',
          '#attributes' => [
            'class' => 'bucket-text-label',
            'tabindex' => 0,
          ],
          '#value' => $this->t('@term', ['@term' => ($term) ? $term->get('name')->value : 'Not Found']),
          '#weight' => 0,
        ];
        $output['bucket-main-content']['right-sidebar'] = [
          '#type' => 'html_tag',
          '#tag'  => 'div',
          '#attributes' => [
            'id' => 'sidebar-text'
          ],
          '#weight' => 1,
          '#value' => $this->t('<h2 class="title">Analysis</h2><div>@termDescription</div>', [
            '@termDescription' => ($term) ? $term->get('description')->value : ''
          ]),
        ];
      }

      $buckets = $queue->get('items')->referencedEntities();
      $tabindex = 0;
      foreach ($buckets as $nid => $bucket) {
        if ($label) {
          if (!$this->uo_bucket_text_node_has_label($bucket, $label)) {
            continue;
          }
        }
        else {
          if ($bucket->field_bucket_hide->value) {
            continue;
          }
        }
        $text = $bucket->field_bucket_text->value;
        $newparagraph = $bucket->field_bucket_new_paragraph->value;
        if ($label) {
          if (!empty($bucket->field_bucket_new_paragraph_rm)) {
            foreach ($bucket->field_bucket_new_paragraph_rm as $key => $tid) {
              if ($tid->target_id == $label) {
                $newparagraph = FALSE;
              }
            }
          }
          if (!empty($bucket->field_bucket_new_paragraph_add)) {
            foreach ($bucket->field_bucket_new_paragraph_add as $key => $tid) {
              if ($tid->target_id == $label) {
                $newparagraph = TRUE;
              }
            }
          }
        }
        $color = $default_color;
        if ($bucket->field_bucket_highlight_label->target_id) {
          $highlight_label = Term::load($bucket->field_bucket_highlight_label->target_id);
          $color = (strtoupper($highlight_label->field_bucket_color->color) == "#FFFFFF") ? $default_color : $highlight_label->field_bucket_color->color;
        } else {
          $color = (strtoupper($bucket->field_bucket_color->color) == "#FFFFFF") ? $default_color : $bucket->field_bucket_color->color;
        }
        if ($newparagraph) {
          $output['bucket-main-content']['text'][] = [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#prefix' => '<br/>',
            '#attributes' => [
              'tabindex' => $tabindex,
              'class' => 'highlight-text',
              'data-color' => $color,
              'data-labels' => $this->uo_bucket_text_get_labels($bucket),
              'data-title' => $this->uo_bucket_text_tooltip_display($bucket, $name),
            ],
            '#value' => $this->helper->uo_bucket_text_replace_footnote('	' . $text),
          ];
        } else {
          $output['bucket-main-content']['text'][] = [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#attributes' => [
              'tabindex' => $tabindex,
              'class' => 'highlight-text',
              'data-color' => $color,
              'data-labels' => $this->uo_bucket_text_get_labels($bucket),
              'data-title' => $this->uo_bucket_text_tooltip_display($bucket, $name),
            ],
            '#value' => $this->helper->uo_bucket_text_replace_footnote($text),
          ];
        }
      }
    }

    return $output;
  }

  /**
   * Check if node has label (tag).
   */
  function uo_bucket_text_node_has_label($node, $tid) {
    if ($node->getType() == 'bucket') {
      foreach ($node->field_bucket_labels as $delta => $term) {
        if ($term->target_id == $tid) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * Get bucket labels.
   *
   * @param object $node
   *   A node object.
   *
   * @return string
   *   HTML output.
   */
  function uo_bucket_text_get_labels($node) {
    $output = $this->t('');
    if ($node->getType() == 'bucket') {
      $labels = [];
      foreach ($node->field_bucket_labels as $delta => $term) {
        $labels[] = $term->target_id;
      }
      $output = $this->t('[@labels]', [
        '@labels' => implode(', ', $labels),
      ]);
    }
    return $output;
  }

  /**
   * Bucket tooltip display.
   *
   * @param object $node
   *   A node object.
   *
   * @return TranslatableMarkup object
   *   HTML output.
   */
  function uo_bucket_text_tooltip_display($node, $page) {
    $output = $this->t('');
    if ($node->getType() == 'bucket') {
      $labels = [];
      foreach ($node->field_bucket_labels as $delta => $term) {
        $label = Term::load($term->target_id);
        if ($label) {
          $labels[] = Link::fromTextAndUrl($this->t($label->get('name')->value), Url::fromRoute('uo_bucket_text.label.text', ['name' => $page, 'label' => $term->target_id]))->toString();
        }
      }
      $output = $this->t('@labels', [
        '@labels' => implode(', ', $labels),
      ]);
    }
    return $output;
  }

  /**
   * Return footnotes.
   *
   * @param string $string
   *   Footnote title, optional.
   *
   * @return array
   *   List of footnotes.
   */
  function uo_bucket_text_get_footnotes($string) {
    $footnote_query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'footnote')
      ->condition('title', $string, 'CONTAINS')
      ->condition('status', NodeInterface::PUBLISHED)
      ->sort('created', 'DESC');
    $ids = $footnote_query->execute();
    $footnotes = $ids ? $this->entityTypeManager->getStorage('node')->loadMultiple($ids) : [];
    
    $results = [];
    foreach ($footnotes as $footnote) {
      $label = [
        $footnote->getTitle(),
        '<small>(' . $footnote->id() . ')</small>'
      ];
      $results[] = [
        'value' => $footnote->id(),
        'label' => implode(' ', $label),
      ];
    }
  
    return $results;
  }

}