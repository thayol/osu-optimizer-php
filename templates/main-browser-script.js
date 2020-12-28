var xmlhttp = new XMLHttpRequest();
var page = 1;
var url = "./splitter.php?format=json&page=" + page.toString();

xmlhttp.onreadystatechange = function() {
	// done & success
	if (this.readyState == 4 && this.status == 200) {
		var result = JSON.parse(this.responseText);
		alert(result);
		changeBrowser(result);
	}
};

xmlhttp.open("GET", url, true);
xmlhttp.send();

function changeBrowser(mapsets)
{
	var browser = document.getElementById("browser");
	
	var output = "";
	
	var template = "<div class=\"map\"><p class=\"map-title\">{{ MAP_TITLE }}</p></div>";
	for (var beatmap of mapsets.entries)
	{
		output += template.replaceAll(/\{\{ MAP_TITLE \}\}/g, beatmap.Metadata.Title);
	}
	
	browser.innerHTML = output;
}
