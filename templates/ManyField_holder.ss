<$Tag id="$HolderID" class="manyfield__holder field $extraClass <% if ColumnCount %>manyfield__holder--multicolumn<% end_if %> <% if canRemove %>manyfield__holder--canremove<% end_if %>">
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

<div class="manyfield__icon-templates" aria-hidden="true">
	<span class="manyfield__icon-template manyfield__icon-template--remove"><% include FullscreenInteractive/ManyField/Includes/ManyFieldIconRemove %></span>
	<span class="manyfield__icon-template manyfield__icon-template--move"><% include FullscreenInteractive/ManyField/Includes/ManyFieldIconMove %></span>
</div>

<% if canAdd %>
<div class="manyfield__add">
	<a class="btn btn-primary" href="$AddLink">$AddLabel +</a>
</div>
<% end_if %>

</$Tag>
