# osu! optimizer (PHP)

**Current version: Version 0.1a**

[round boi][round-boi-osu] has seen most of my works and it was his idea to combine them together before I start writing another one from scratch.

> "Swiss Army Knife style osu! library manager" -- [round boi][round-boi-osu]

## What's this?

This is my attempt at making a somewhat functional multitool for your library. I've had many separate smaller projects in various languages, but I decided to stick with PHP.

There is no real programming style or pattern set for this project, so it's kind of undocumented and hard to read.

Pull requests are welcome! I doubt this project would get any traffic, so feel free to message me directly about basically anything.

## Features

 - [x] Replace all backgrounds with 1x1 black images
 - [x] Remove storyboard specific files
 - [x] Remove background videos
 - [x] Remove beatmap skins
 - [x] Remove beatmap keyed hitsounds
 - [x] Remove beatmap default hitsounds
 - [x] Remove junk files (mapping garbage, unused storyboard, random files, etc.)
 - [x] Export maps to .osz
 - [ ] Delete maps based on star difficulty
 - [ ] Delete maps based on game mode
 - [ ] Blacklist: the maps that should not be touched by this program

## Warning!

This project is untested on MacOS. It is not tested thoroughly on Windows either!

You should always back up your osu! folder before using programs of these kinds. I will include a somewhat legal disclaimer just in case.

**Disclaimer**: This code is provided "**AS IS**," I and the other contributors take no responsibility for any harm you may cause to yourself or your computer and its data.

*You have been warned!*


## Usage

You will need PHP 7.4 or later. (Help: [Windows][Windows-PHP], [MacOS][Mac-OS-PHP])

Steps to set up with PHP's built-in solution:

1. Clone or download and unzip the source code.
2. Open a new terminal, powershell window, or command prompt at the source root.
3. Type `php -S 127.0.0.1:1337` and press enter. (Capital -S is important!)
4. Open <http://127.0.0.1:1337/> in your browser.
5. Enter your osu! folder's location and press "Save."
6. Pick your poison.
7. When you're done, terminate the terminal/pwsh/cmd with Ctrl+C.


If you know what you're doing, you could run it on a dedicated server that can handle PHP, but exposing functions capable of file deletion outside the current working directory should never be done on the web. At least bind to localhost.



[Windows-PHP]: https://www.php.net/manual/en/install.windows.tools.php
[Mac-OS-PHP]: https://www.php.net/manual/en/install.macosx.bundled.php
[round-boi-osu]: https://osu.ppy.sh/users/7357064
