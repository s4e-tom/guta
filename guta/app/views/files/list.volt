{{ content() }}

<h1 class="text-center">Mes fichiers</h1>
<button href="#myModal" role="button"  data-toggle="modal">Nouveau dossier</button>

<!-- Modal creation folder -->
<div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="myModalLabel">Nouveau dossier</h4>
            </div>
            {{ form('files/createFolder' ~ currentDir, 'method': 'post', "class": "col-lg-12", "style": "width:340px;margin:0 auto;") }}
                <div class="modal-body">
                    <div class="form-group ">
                        <label> Nom du dossier </label>
                        {{ text_field("foldername", 'class': 'form-control input-lg', "id": "inputUsername","placeholder":"Nom de dossier") }}
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Fermer</button>
                    {{ submit_button('Valider', "class":"btn btn-primary") }}
                </div>
            {{ end_form() }}
        </div>
    </div>
</div>


<table class="table table-hover" style="width:80%" data-sortable>
	<thead>
		<tr>
			<th>Nom</th>
			<th>Taille</th>
			<th>Modification</th>
		</tr>
	</thead>
	<tbody>
		
			{% for dir in directories %}
				<tr>
					<td><span class="glyphicon glyphicon-folder-open"> {{ link_to("files/list" ~ currentDir ~ "/" ~ dir['name'], dir['name']) }}</span></td>
					<td>{% if dir['size'] != null %} {{ dir['size'] }} ko {% endif %}</td>
					<td></td>
				</tr>
			{% endfor %}
			{% for file in files %}
				<tr>
					<td><span class="glyphicon glyphicon-file"> {{ link_to("files/view" ~ currentDir  ~ "/" ~ file['name'], file['name']) }}</span></td>
					<td>{% if file['size'] != null %} {{ file['size'] }} ko {% endif %}</td>
					<td>{{ file['modifyDate'] }}</td>
				</tr>
			{% endfor %}
		
	</tbody>
</table>
