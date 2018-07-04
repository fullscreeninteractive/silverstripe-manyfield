;
(function($) {
  $(document).ready(function() {
    var dynFields = $('.many_field')

    dynFields.filter(':visible').removeClass('inactive').addClass('active')

    var addRemove = function() {
      $('.ManyField__Holder.can-remove').each(function(i, elem) {
        $(elem).find('.many_field')
          .prepend('<a class="btn btn-sm btn-danger ManyField__remove"><i class="fa fa-times"></i></a>')

        if (!$(elem).hasClass('no-sort')) {
          $(elem).find('.many_field').prepend('<span class="btn btn-sm btn-info ManyField__move"><i class="fa fa-sort"></i></span>')
        }
      })
    }

    addRemove()

    $('body').on('click', '.ManyField__add', function() {
      var parentHolder = $(this).parents('.ManyField__Holder')

      if (parentHolder.find('.many_field.active').next('.many_field').length) {
        parentHolder.find('.many_field.active').removeClass('active').next().addClass('active').removeClass('inactive')
      } else {
        parentHolder.find('.many_field').first().removeClass('inactive').addClass('active')
      }
    })

    $('body').on('click', '.ManyField__remove', function() {
      var parent = $(this).parents('.many_field')
      var parentHolder = $(this).parent('.ManyField__Holder')

      if (parent && parent.length) {
        parent.removeClass('active').addClass('inactive')
        parent.find('input').val('')
        parentHolder.find('.ManyField__add').show()
      }
    })
  })
})(jQuery)
