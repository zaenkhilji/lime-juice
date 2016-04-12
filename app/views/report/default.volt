<h1 class="head"><span class="glyphicon glyphicon-tags"></span> &nbsp;Databases ({{ dbsl|length }})</h1>
<p><i>Pick a database connection from the list below or create a new connection!</i></p>
{% for itm in dbsl %}
    <div class="bitm">
        <a href="{{ url("report/index", ["id" : itm.getId()]) }}" class="cnt small">
            <h2><span class="glyphicon glyphicon-tag"></span> {{ itm.name }}</h2>
            <p><b class="orange">{{ itm.getAdapter()|upper }}</b> | {{ itm.countReports() }} reports</p>
        </a>
        <div>
            <a href="{{ url('db/delete', ['id': itm.getId()]) }}" title="Delete" class="btn pull-right btn-danger btn-mrg-left runModal"><span class="glyphicon glyphicon-trash"></span></a>
            <a href="{{ url('db/edit', ['id': itm.getId()]) }}" title="Edit" class="btn btn-default pull-right"><span class="glyphicon glyphicon-pencil"></span></a>
        </div>
    </div>
{% endfor %}
<div class="bitm">
    <a href="{{ url("db/new") }}" class="add small" title="New connection">
        <h2>
            <span class="glyphicon glyphicon-plus"></span><br/><br/>
            New database<br/>connection<br/>
        </h2>
    </a>
</div>
<script>
    $(function(){
        //launch modal
        $('.runModal').click(function(e){
            $.get($(this).attr('href'), function(data){
                $('#loadModal').empty().html(data);
            });
            e.preventDefault();
        });
    });
</script>
