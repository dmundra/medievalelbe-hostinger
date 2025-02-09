/**
 * @file
 * Add js functions to insert footnote.
 */
(function ($) {
  Drupal.behaviors.uo_bucket_text_insert_footnote_page = function() {
    /**
     * Function called when uo_bucket_text_insert_footnote_page is initialized
     */
    var _init = function(){
      const editor = Drupal.CKEditor5Instances.get(
        $('#edit-body-0-value').attr('data-ckeditor5-id'),
      );
      $('#uo_bucket_text_add_footnote').on('click', function(e) {
        e.preventDefault();
        var footnote = $('#edit-uo-bucket-text-footnotes').val();
        if (footnote == 'tab') {
          editor.execute( 'input', { text: "\t" } );
        } else {
          const htmlDP = editor.data.processor;
          const viewFragment = htmlDP.toView('[fn value=' + footnote + ']');
          const modelFragment = editor.data.toModel( viewFragment );
          editor.model.insertContent(modelFragment);
        }
      });
    };

    // Functions that are executed when uo_bucket_text_insert_footnote_page is initialized and jQuery is ready
    $(function(){
      _init();
    });

    // Public Functions
    return {}
  }();
})(jQuery);