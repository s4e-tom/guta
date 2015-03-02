//We add the contextual menu and prevent the clicks on the document to do what we want
$(document).on("ready", function(){
	$(menu()).appendTo("body");
})
$(document).on('click', 'a.menulink', function(event){
	event.preventDefault();
	menu_click($(this));
});

//Change document to only have the list elements (class)
$(document).bind("contextmenu", function(event){
	event.preventDefault();
	$("ul.dropdown-menu").css({display: "block", top: event.pageY + "px", left: event.pageX + "px"});
});
$(document).bind("click", function(event){
	$("ul.dropdown-menu").hide();
});

//HTML of the contextual menu
menu = function(){
	string = "<ul class='dropdown-menu'><li>Context Menu</li><li class='divider'></li>";
	//contextual menu here
	string += "<li><a id='download' class='menulink' href=''>Download</a></li>"
	// menu's end
	string += "</ul>";
	return string;
}

//Associate the actions of the contextual menu here
menu_click = function(object){
	switch(object.attr('id')){
	case 'download':
		var file = "testFile.txt";
		$(location).attr('href', "http://localhost/guta/guta/public/files/download/" + file);
		break;
	default:
		$(location).attr('href', object.attr('href'));
		break;
	}
}