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

  /**
   * Footnote count.
   *
   * @var int
   */
  protected $matchcount = 0;

  /**
   * Replace footnote shortcode with text and popup.
   *
   * @param string $text
   *   Text that contains footnote shortcode.
   */
  public function replaceFootnote($text) {
    $text = preg_replace('|\[fn([^\]]*)\]|', '<fn$1>', $text);
    $text = preg_replace('|\[/fn\]|', '</fn>', $text);
    $pattern = '|<fn([^>]*)>|';
    return preg_replace_callback($pattern, [$this,
      'replaceFootnoteCallback'
    ], $text);
  }

  /**
   * Helper method called from preg_replace_callback() above.
   *
   * Uses static vars to temporarily store footnotes found.
   * This is not threadsafe, but PHP isn't.
   */
  public function replaceFootnoteCallback($matches, $op = '') {
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
