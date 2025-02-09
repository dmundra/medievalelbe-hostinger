/**
 * @file
 * Add js functions to insert footnote in Bucket.
 */
(function ($) {
  $(document).ready(function(){
    $('#uo_bucket_text_add_footnote').on('click', function(e) {
      e.preventDefault();
      const $txt = $("#edit-field-bucket-text-0-value");
      let caretPos = $txt[0].selectionStart;
      let textAreaTxt = $txt.val();
      let footnote = $('#edit-uo-bucket-text-footnotes').val();
      let txtToAdd = '\t';
      if (footnote !== 'tab') {
        txtToAdd = '<fn value="' + footnote + '">';
      }
      $txt.val(textAreaTxt.substring(0, caretPos) + txtToAdd + textAreaTxt.substring(caretPos));
    });
  });
})(jQuery);
