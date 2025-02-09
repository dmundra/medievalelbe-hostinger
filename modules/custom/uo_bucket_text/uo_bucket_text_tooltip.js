/**
 * @file
 * Add js functions to show jQuery UI Tooltip.
 */
(function ($) {
  $(document).ready(function(){
    $('.see-footnote').tooltip({
      content: function () {
        return $(this).prop('title');
      }
    });
  });
})(jQuery);