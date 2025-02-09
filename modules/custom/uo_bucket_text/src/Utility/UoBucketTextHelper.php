<?php

namespace Drupal\uo_bucket_text\Utility;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\Entity\Node;


/**
 * Class UoBucketTextHelper.
 *
 * @package Drupal\uo_bucket_text\Utility
 */
class UoBucketTextHelper {
  use StringTranslationTrait;

  protected $matchcount = 0;

  /**
   * Replace footnote shortcode with text and popup.
   *
   * @param string $text
   *   Text that contains footnote shortcode.
   *
   * @return TranslatableMarkup object
   *   HTML output.
   */
  function uo_bucket_text_replace_footnote($text) {
    $text = preg_replace('|\[fn([^\]]*)\]|', '<fn$1>', $text);
    $text = preg_replace('|\[/fn\]|', '</fn>', $text);
    $pattern = '|<fn([^>]*)>|';
    $text = preg_replace_callback($pattern, [$this, 'uo_bucket_text_replace_footnote_callback'], $text);
    return $text;
  }

  /**
   * Helper method called from preg_replace_callback() above.
   *
   * Uses static vars to temporarily store footnotes found.
   * This is not threadsafe, but PHP isn't.
   */
  function uo_bucket_text_replace_footnote_callback($matches, $op = '') {
    $footnote = "";
    if ($matches[1]) {
      $nid = '';
      if (preg_match('|value=["\']?([0-9]*)["\']?|', $matches[1], $nid_match)) {
        $nid = $nid_match[1];
      }
      $node = Node::load($nid);
      if ($node and $node->getType() == 'footnote') {
        $footnote = $this->t('<a class="see-footnote" title="@text" href="#@id">@title</a>', [
          '@text' => $node->body->value,
          '@id' => $nid,
          '@title' => ++$this->matchcount,
        ])->render();
      }
    }
    return $footnote;
  }
}