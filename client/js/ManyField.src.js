(function ($) {
  $(document).ready(function () {
    var wrapManyFields = function () {
      $(".manyfield__holder").each(function (i, elem) {
        var canSort = $(elem).hasClass("manyfield__holder--cansort");
        var canRemove = $(elem).hasClass("manyfield__holder--canremove");
        var field = $(elem).find(".manyfield__row");

        $(elem)
          .find(".manyfield__row")
          .each(function (r, row) {
            if (canRemove) {
              if (!$(row).find(".manyfield__remove").length) {
                var href = $(this).data("inline-delete");

                if (href) {
                  href.replace("saveRecord", "deleteRecord");
                  var id = $(row).find('input[type="hidden"][name*=ID]');

                  if (id.length < 1) {
                    console.error("No ID hidden field in ManyField row");
                  }

                  href = href + "?ID=" + id.val();
                }

                href = href || "#";

                $(row).prepend(
                  '<a class="btn btn-sm btn-danger manyfield__remove" href="' +
                    href +
                    '"><i class="fa-solid fa-times"></i></a>'
                );
              }
            } else {
              field.find(".manyfield__remove").remove();
            }

            if (canSort) {
              if (!$(row).find(".manyfield__move").length) {
                $(row).prepend(
                  '<span class="btn btn-sm btn-info manyfield__move"><i class="fa-solid fa-sort"></i></span>'
                );
              }
            } else {
              field.find(".manyfield__move").remove();
            }
          });
      });
    };

    wrapManyFields();

    $("body").on("change", "[data-inline-save] .field", function (e) {
      // saves the record line
      var parent = $(this).parents("[data-inline-save]");
      var row = $(this).parents(".row");
      var url = parent.attr("data-inline-save");
      var csrf = $("input[name=SecurityID]").val();
      var data = [];

      row.find("[name]").each(function (i, field) {
        var name = $(field).attr("name");
        var value = null;

        if ($(field).is(":checkbox")) {
          value = $(field).is(":checked");
        } else {
          value = $(field).val();
        }

        var cleanName = name.substr(
          name.indexOf("[") + 1,
          name.indexOf("]") - (name.indexOf("[") + 1)
        );

        if (name.split("[").length > 3) {
          cleanName += name.substr(
            name.lastIndexOf("["),
            name.lastIndexOf("]") - (name.lastIndexOf("[") - 1)
          );
        }

        data.push({
          name: cleanName,
          value: value,
        });
      });

      data.push({
        name: "SecurityID",
        value: csrf,
      });

      $.post(url, $.param(data), function () {
        $("body").trigger("manyFieldSaved");
      });
    });

    $("body").on("click", ".manyfield__add a", function (e) {
      e.preventDefault();

      var parents = $(this).parents(".manyfield__holder");

      $.get(
        $(this).attr("href"),
        { index: parents.find(".manyfield__row").length },
        function (data) {
          var rows = parents.find(".manyfield__row").last();

          // if this is the inline modal version then we want to open that in
          // a popup
          if (parents.hasClass("manyfieldmodal")) {
            // find the add modal, set the content to that and open it.
            var id = parents.attr("id");
            var modal = $("#" + id + "_modal");

            // write the provided names as we don't need to namespace them in this
            // case - we can only edit one at a time
            var content = $(data);
            content.find("input[name]").each(function (i, field) {
              if ($(field).attr("name").indexOf("[") !== false) {
                var name = $(field)
                  .attr("name")
                  .substring(
                    $(field).attr("name").indexOf("[") + 1,
                    $(field).attr("name").indexOf("]")
                  );

                $(field).attr("name", name);
              }
            });

            // move the modal form outside of the main form
            if (modal.parents("form").length > 0) {
              modal.detach().prependTo("body");
            }

            var saveURL = modal.attr("data-save-url");
            content = $('<form action="' + saveURL + '"></form>').html(data);
            modal.find(".modal-body").html(content);
            modal.modal("show");
          } else {
            if (rows && rows.length) {
              rows.after(data);
            } else {
              parents.find(".manyfield__outer").append(data);
            }

            wrapManyFields();
          }

          $("body").trigger("manyFieldAdded", {
            parents,
          });
        }
      );
    });

    /**
     *
     */
    $("body").on("click", ".manyfield__remove", function () {
      var parent = $(this).parents(".manyfield__row");

      if (parent.length < 1) {
        parent = $(this).parents("[data-many-id]");
      }

      parent.remove();

      var data = {
        SecurityID: $("input[name=SecurityID]").val(),
      };

      if ($(this).attr("href")) {
        $.post($(this).attr("href"), data);
      }

      $("body").trigger("manyFieldRemoved", {
        parent,
      });

      return false;
    });

    /**
     *
     */
    $("body").on("click", ".manyfield__save", function (e) {
      var form = $(this).parents(".modal-content").find("form");

      $("body").trigger("manyFormModalSave", {
        form,
      });

      if (form.length > 0 && form.get(0).checkValidity()) {
        var body = $(this)
          .parents(".modal-content")
          .find(".modal-body")
          .addClass("loading");

        var action = form.attr("action");

        if (!action) {
          console.error("No action found on form");
          return;
        }

        $.post(action, form.serialize(), function (reply) {
          // reply should be the updated content for
          if (reply) {
            form.parents(".manyfield__holder").html(reply);
            body.html("");
          }

          $(".modal").modal("hide");
          $("body").removeClass("modal-open");
          $(".modal-backdrop").remove();

          $("body").trigger("manyFormModalSaved", {
            form,
          });
        });
      } else {
        // highlight issues
        e.preventDefault();

        alert("You are missing one or more fields");

        return false;
      }
    });

    /**
     *
     */
    $("body").on(
      "click",
      ".manyfieldmodal .manyfield__outer > div",
      function () {
        var parents = $(this).parents(".manyfieldmodal");
        var recordId = $(this).data("many-id");

        // find the add modal, set the content to that and open it.
        var id = parents.attr("id");
        var modal = $("#" + id + "_modal");
        var saveURL = modal.attr("data-save-url");

        $.get(modal.data("form-url"), { RecordID: recordId }, function (data) {
          // write the provided names as we don't need to namespace them in this
          // case - we can only edit one at a time
          var content = $('<form action="' + saveURL + '"></form>').html(data);
          modal.find(".modal-body").html(content).removeClass("loading");
          modal.modal("show");
        });
      }
    );

    /**
     * Ajax load results.
     */
    function populateViaAjax(holder) {
      var outer = holder.find(".manyfield__outer");
      var form = holder.parents("form");

      outer.addClass("loading");

      $.get(holder.data("ajax-url"), form.serialize(), function (rows) {
        outer
          .html($(rows).find(".manyfield__outer").html())
          .removeClass("loading");

        $("body").trigger("manyFieldLoaded", {
          holder,
        });
      });
    }

    $(".manyfield__holder[data-ajax-url]").each(function (i, elem) {
      populateViaAjax($(elem));

      var form = $(elem).parents("form");

      $("body").on(
        "change",
        "[data-updates-manyfield=" + $(elem).attr("id") + "]",
        function (e) {
          populateViaAjax($(elem));
        }
      );
    });
  });
})(jQuery);
