//We add the contextual menu and prevent the clicks on the document to do what we want
$(document).on("ready", function(){
	$(menu()).appendTo("body");
})
$(document).on('click', 'a.menulink', function(event){
	event.preventDefault();
	menu_click($(this).attr('id'));
});

var clicked;

$(".contextMenu").bind("contextmenu", function(event){
	event.preventDefault();
	clicked = $(this);
	$("ul.dropdown-menu").css({display: "block", top: event.pageY + "px", left: event.pageX + "px"});
	$("li.share").css({display: "block"});
	$("li.delete").css({display: "block"});
	$("li.copy").hide();
	$("li.version").hide();
	$("li.download").css({display: "block"});
});
$('.btn-operation').bind('click', function(event){
	clicked = $(this).parent().parent().parent();
	menu_click($(this).attr('id'));
});
$('.navigate').css({"cursor":"pointer"});
$('.navigate').bind("click", function(event){
	event.preventDefault();
	var file = false;
	$(this).find('span').each(function(){
		if($(this).attr('class').search('glyphicon-file') != -1){
			file = true;
		}
	})
	if(!file){
		$(this).find('a').each(function(){
			$(location).attr('href', $(this).attr('href'));
		})
	}
	else{
		$(this).find('a').each(function(){
			showFile($(this).attr('path'), $(this).attr('file'));
		})
	}
});
$(".shared").bind("contextmenu", function(event){
	$("li.share").hide();
	$("li.delete").hide();
	$("li.copy").hide();
});
$(".doublepoint").bind("contextmenu",function(event){
	$("li.share").hide();
	$("li.delete").hide();
	$("li.copy").hide();
	$("li.version").hide();
});
$(".downloadable").bind("contextmenu", function(event){
	$("li.version").css({display: "block"});
	$("li.copy").css({display: "block"});
	$("li.download").css({display: "block"});
});
$(".noversions").bind("contextmenu", function(event){
	$("li.copy").css({display: "block"});
	$("li.download").css({display: "block"});
});
$(".sharednoversions").bind("contextmenu", function(event){
	$("li.download").css({display: "block"});
});
$(document).bind("click", function(event){
	$("ul.dropdown-menu").hide();
	$("li.download").hide();
});

//empty the versions modal when not in use (prevents incorrect data displays during loadings)
$('#myVersionsModal').on('hidden.bs.modal', function () {
  $("#versionsRows").html("<button class='btn btn-lg btn-warning'><span class='glyphicon glyphicon-refresh spinning'></span> Chargement...</button>");
});

//HTML of the contextual menu
menu = function(){
	string = "<ul class='dropdown-menu'>";
	//contextual menu here 
	string += "<li class='download'><a id='download' class='menulink' href=''><span class='glyphicon glyphicon-download-alt' aria-hidden='true'></span> Télécharger</a></li>"
	string += "<li class='share'><a id='share' class='menulink' href='#shareModal' data-toggle='modal'><span class='glyphicon glyphicon-share' aria-hidden='true'></span> Partager</a></li>"
	string += "<li class='version'><a id='version' class='menulink' href='#myVersionsModal' data-toggle='modal'> <span class='glyphicon glyphicon-fast-backward' aria-hidden='true'></span> Versions</a></li>"
	string += "<li class='divider'></li>";
	string += "<li class='copy'><a id='copy' class='menulink' href=''> <span class='glyphicon glyphicon-copy' aria-hidden='true'></span> Copier</a></li>"
	string += "<li class='delete'><a id='delete' class='menulink' href=''><span class='glyphicon glyphicon-trash' aria-hidden='true'></span> Supprimer</a></li>"
	// menu's end
	string += "</ul>";
	return string;
}

function httpGet(theUrl)
{
    var xmlHttp = null;

    xmlHttp = new XMLHttpRequest();
    xmlHttp.open( "GET", theUrl, false );
    xmlHttp.send( null );
    console.dir(xmlHttp.responseText);
    return xmlHttp.responseText;
}

function versionsRequest(theUrl, folderPath, target){
	$.getJSON(theUrl, {}, function(ver){
		$("#versionsRows").empty();
		$.each(ver, function(key, value){
			var splittedValues = value.split(" ");
		    $("#versionsRows").append("<a class='btn btn-primary' href='"+ folderPath + "downloadVersion/" + target  +"/"+splittedValues[0]+"' key="+ key +" ver="+ splittedValues[0] +">"+splittedValues[1]+" "+ splittedValues[2] + "</a></p>");
		});
	}).fail(function (j, t, e) {
	   console.error(e);
	});
}
//Associate the actions of the contextual menu here
menu_click = function(attr){
	var pos;
	var target;
	var folderPath;
	var str = "files/";
	clicked.find('input').each(function(){
		pos = $(this).attr('id').indexOf(str);
		target = $(this).attr('id').substring(pos + str.length).replace(/\//g, '¤');
		folderPath = $(this).attr('id').substring(0, pos) + "files/";
	});
	switch(attr){
	case 'download':
		$(location).attr('href', folderPath + "download/" + target);
		break;
	case 'delete':
		$(location).attr('href', folderPath + "delete/" + target);
		break;
	case 'copy':
		$('#pasteButton').fadeIn("fast");
		httpGet(folderPath + "copy/" + target);

		$("#pastButton").removeAttr("disabled");
		// taken from StackOverflow, by Anu - SO
		$("#copyNotification").fadeIn("slow").html('Fichier ' + target.replace(/¤/g, '/') +' copié <span class="dismiss"><a title="Dismiss this notification">X</a></span>');
		$(".dismiss").click(function(){
		       $("#copyNotification").fadeOut("slow");
		});
		break;
	case 'share':
		//We just check the checkbox and then go to the share modal
		var checkbox;
		clicked.find('input').each(function(){
			checkbox = $(this);
		});
		checkbox.prop('checked', true);
		break;
	case 'version':
		versionsRequest(folderPath + "getVersions/" + target, folderPath, target);
		break;
	default:
		break;
	}
}