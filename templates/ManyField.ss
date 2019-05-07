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

</$Tag>
