var loadingContent = "Loading...";
var xmlhttp = new XMLHttpRequest();
xmlhttp.onreadystatechange = function() {
	if (this.readyState == 4 && this.status == 200) {
		loadingContent = this.responseText;
	}
};

xmlhttp.open("GET", "templates/loading-screen.html", true);
xmlhttp.send();

function displayLoading() {
	document.getElementById("options").style.display = "none";
	document.getElementById("loader").innerHTML = loadingContent;
}