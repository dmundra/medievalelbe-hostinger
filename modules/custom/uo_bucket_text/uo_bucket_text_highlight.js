/**
 * @file
 * Add js functions to hightlight text and labels.
 */
(function ($, Drupal) {
  Drupal.behaviors.uo_bucket_text_hightlight = {
    attach: function (context, drupalSettings) {
      /**
       * Function that highlights all texts that have the label ID.
       */
      function highlight_texts(label_element, color) {
        var classes = $(label_element).attr('class');
        var matches = classes.match(/label-(\d+)/g);
        var labels = matches[0].split("-");
        var tid = parseInt(labels[1]);
        $('.label-' + tid).css('background-color', color);
        var buckets = $('.highlight-text');
        $.each(buckets, function (index, text) {
          var labels = $(text).data('labels');
          $.each(labels, function (index, value) {
            if (value === tid) {
              $(text).css('background-color', color);
            }
          });
        });
      }

      /**
       * Function that highlights labels.
       */
      function highlight_labels(text_element, color) {
        $(text_element).css('background-color', color);
        var labels = $(text_element).data('labels');
        $.each(labels, function (index, value) {
          var label_color = color;
          if (color) {
            label_color = get_label_color($('.label-' + value).find('span'));
          }
          $('.label-' + value).css('background-color', label_color);
        });
      }

      /**
       * Function that get's label color.
       */
      function get_label_color(label_text) {
        var label_color = drupalSettings.uo_bucket_text.default_color;
        if (label_text.data('color') != '#ffffff') {
          label_color = label_text.data('color');
        }
        return label_color;
      }

      /**
       * Function that opens collapsed active text recursively.
       */
      function display_active_label(label) {
        var parent_list = label.closest('.views_tree_parent');
        parent_list.children('.item-list').slideDown();
        parent_list.children('.views_tree_link').addClass('views_tree_link_expanded');
        parent_list.children('.views_tree_link').removeClass('views_tree_link_collapsed');
        if (parent_list.parent().closest('.views_tree_parent').length > 0) {
          display_active_label(parent_list.parent());
        }
      }

      /**
       * Function called when uo_bucket_text_highlight is initialized
       */
      var _init = function() {
        $('.highlight-text', context).on('mouseover focusin', function (e) {
          var color = $(this).data('color');
          // Reset color white to the default color.
          if (color == '#ffffff') color='inherit';
          highlight_labels(this, color);
        });
        $('.highlight-text', context).on('mouseout focusout', function (e) {
          highlight_labels(this, '');
        });
        // Highlight label
        $('.highlight-label', context).on('mouseover focusin', function (e) {
          label_color = get_label_color($(this).find('span'));
          highlight_texts(this, label_color);
        });
        // Highlight label
        $('.highlight-label', context).on('mouseout focusout', function (e) {
          highlight_texts(this, '');
        });
        $.each($('.highlight-label a.is-active', context), function (e) {
          display_active_label($(this));
        });
        $('.highlight-text', context).on('focusin', function (e) {
          var title = $(this).data('title');
          $('.highlight-labels-sidebar').append('<div class="highlight-labels"><h2 class="title">Components</h2>' + title + '</div>');
        });
        $('.highlight-text', context).on('focusout', function (e) {
          if ($('.highlight-labels a:hover').length) {
            return;
          }
          $('.highlight-labels').remove();
        });
      };

      // Functions that are executed when uo_bucket_text_insert_footnote is initialized and jQuery is ready
      $(function() {
        _init();
      });

      // Public Functions
      return {}
    }
  };
})(jQuery, Drupal);
