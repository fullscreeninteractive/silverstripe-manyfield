<table>
    <% loop $Rows %>
        <% if $HeaderRow %>
            <tr>
                <% loop $Columns %><th>$Title</th><% end_loop %>
            </tr>
        <% end_if %>

        <tr>
            <% loop $Columns %>
                <td>{$FormattedValue}</td>
            <% end_loop %>
        <tr>
    <% end_loop %>
</table>
