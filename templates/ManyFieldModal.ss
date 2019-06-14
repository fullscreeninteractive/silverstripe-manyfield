<$Tag id="$HolderID" class="manyfield__holder field $extraClass <% if ColumnCount %>manyfield__holder--multicolumn<% end_if %> <% if canRemove %>manyfield__holder--canremove<% end_if %> <% if canSort %>manyfield__holder--cansort<% end_if %>">
<% if $Tag == 'fieldset' && $Legend %>
    <legend>$Legend</legend>
<% end_if %>

<div class="manyfield__outer">
<% loop FieldList %>
    <% if ColumnCount %>
        <div class="column-{$ColumnCount} $FirstLast">
            $FieldHolder
        </div>
    <% else %>
        $FieldHolder
    <% end_if %>
<% end_loop %>
</div>

<% if canAdd %>
<div class="manyfield__add">
    <a class="btn btn-primary" href="$AddLink">$AddLabel +</a>
</div>
<% end_if %>

<div class="modal" id="{$HolderID}_modal" data-form-url="$EditLink" data-save-url="$SaveLink">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
        <h4 class="modal-title"></h4>
      </div>
      <div class="modal-body">

      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-primary manyfield__save">Save</button>
        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
      </div>
    </div><!-- /.modal-content -->
  </div><!-- /.modal-dialog -->
</div>

</$Tag>
