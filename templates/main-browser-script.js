var xmlhttp = new XMLHttpRequest();
var page = 1;
var maxpage = 1;
var url = "./splitter.php?format=json&page=";
updateBrowser();


xmlhttp.onreadystatechange = function() {
	// done & success
	if (this.readyState == 4 && this.status == 200) {
		var result = JSON.parse(this.responseText);
		changeBrowser(result);
	}
};

function requestPage(uri) {
	xmlhttp.open("GET", uri, true);
	xmlhttp.send();
}

function updateBrowser() {
	requestPage(url + page.toString());
}



function prevPage() {
	page -= 1;
	if (page < 0) page = 0;
	updateBrowser();
}

function nextPage() {
	page += 1;
	updateBrowser();
}

function firstPage() {
	page = 1;
	updateBrowser();
}

function lastPage() {
	page = maxpage;
	updateBrowser();
}

function changeBrowser(response)
{
	var browser = document.getElementById("browser");
	
	if (!response.page)
	{
		return;
	}
	
	if (response.maxpage)
	{
		maxpage = response.maxpage;
	}
	
	var output = "<div>Page " + page.toString() + "/" + response.maxpage.toString() + "</div>";
	
	var mapsetTemplate = `{{ MAIN_BROWSER_TEMPLATE_MAPSET }}`;
	var template = `{{ MAIN_BROWSER_TEMPLATE_DIFFICULTY }}`;
	
	for (var mapsetKey of Object.keys(response.mapsets))
	{
		var mapset = response.mapsets[mapsetKey];
		var subOutput = "";
		var summary = true;
		
		for (var beatmapKey of Object.keys(mapset.difficulties))
		{
			var beatmap = mapset.difficulties[beatmapKey];
			
			if (beatmap.Metadata && beatmap.Metadata.Title)
			{
				var path = mapset.path.toString() + "/" + beatmap.background.toString();
				path = encodeURI(path);
				path = path.replaceAll(/\+/g, "%2b");
				line = template;
				line = line.replaceAll(/\{\{ MAP_TITLE \}\}/g, beatmap.Metadata.Title);
				line = line.replaceAll(/\{\{ MAP_ARTIST \}\}/g, beatmap.Metadata.Artist);
				line = line.replaceAll(/\{\{ MAP_MAPPER \}\}/g, beatmap.Metadata.Creator);
				line = line.replaceAll(/\{\{ MAP_DIFFICULTY \}\}/g, beatmap.Metadata.Version);
				line = line.replaceAll(/\{\{ MAP_IMAGE \}\}/g, "./proxy.php?path=" + path);
				
				if (summary)
				{
					summary = false;
					line = "<summary>" + line + "</summary>";
				}
				
				subOutput += line;
			}
		}
		
		var uriSafeKey = mapset.key;
		uriSafeKey = encodeURI(uriSafeKey);
		uriSafeKey = uriSafeKey.replaceAll(/\+/g, "%2b");
		subOutput = mapsetTemplate.replaceAll(/\{\{ MAP_DIFFICULTIES \}\}/g, subOutput);
		subOutput = subOutput.replaceAll(/\{\{ MAPSET_KEY \}\}/g, uriSafeKey);
		output += subOutput;
	}
	
	browser.innerHTML = output;
}