<?php

namespace Drupal\uo_bucket_text\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\uo_bucket_text\Utility\UoBucketTextHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Plugin implementation of the 'plain text' formatter.
 *
 * @FieldFormatter(
 *   id = "uo_bucket_text_display_formatter",
 *   label = @Translation("UO Bucket Text Display"),
 *   field_types = {
 *     "string_long",
 *     "text_long",
 *     "text_with_summary"
 *   }
 * )
 */
class UoBucketTextDisplayFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The utility helper.
   *
   * @var Drupal\uo_bucket_text\Utility\UoBucketTextHelper
   */
  protected $helper;

  /**
   * Construct a MyFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   Defines an interface for entity field definitions.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\uo_bucket_text\Utility\UoBucketTextHelper $helper
   *   Helper utility.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    UoBucketTextHelper $helper,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->helper = $helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('uo_bucket_text.helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];
    foreach ($items as $delta => $item) {
      // Render each element format.
      if ($item && !empty($item->value)) {
        $element[$delta] = [
          '#type' => 'processed_text',
          '#text' => $this->helper->replaceFootnote($item->value),
          '#format' => ($item->format) ?? 'full_html',
          '#langcode' => $item->getLangcode(),
        ];
      }
    }

    return $element;
  }

}
