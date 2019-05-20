;
(function($) {
  $(document).ready(function() {
    var wrapManyFields = function() {
      $('.manyfield__holder').each(function(i, elem) {
        var canSort = $(elem).hasClass('manyfield__holder--cansort')
        var canAdd = $(elem).find('.manyfield__add').length > 0
        var canRemove = $(elem).hasClass('manyfield__holder--canremove')

        var field = $(elem).find('.manyfield__row')

        $(elem).find('.manyfield__row').each(function (r, row) {
          if (canRemove) {
            if (!$(row).find('.manyfield__remove').length) {
              $(row).prepend('<a class="btn btn-sm btn-danger manyfield__remove"><i class="fa fa-times"></i></a>');
            }
          } else {
            field.find('.manyfield__remove').remove();
          }

          if (canSort) {
            if (!$(row).find('.manyfield__move').length) {
              $(row).prepend('<span class="btn btn-sm btn-info manyfield__move"><i class="fa fa-sort"></i></span>')
            }
          } else {
            field.find('.manyfield__move').remove()
          }
        })
      })
    }

    wrapManyFields()

    $('body').on('click', '.manyfield__add a', function(e) {
      e.preventDefault();

      var
        form = $(this).parents('form'),
        parents = $(this).parents('.manyfield__holder');

      $.get($(this).attr('href'), { index: parents.find('.manyfield__row').length }, function(data) {
        var rows = parents.find('.manyfield__row').last()
        
        if (rows && rows.length) {
          rows.after(data)
        } else {
          parents.find('.manyfield__outer').append(data)
        }

        wrapManyFields()

        $('body').trigger('manyFieldAdded', {
          parents
        })
      })
    })

    $('body').on('click', '.manyfield__remove', function() {
      var parent = $(this).parents('.manyfield__row')

      parent.remove()

      $('body').trigger('manyFieldRemoved', {
        parent
      })
    })
  })
})(jQuery)
