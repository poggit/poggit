Poggit
===

[![Discord](https://img.shields.io/discord/402639859535052811.svg?color=7289DA&logo=discord)](https://discord.gg/NgHf9jt)

## What is this?
Poggit is a website for two things: a plugin builder (Poggit-CI) and a plugin list (Poggit-Release).

## Why not Laravel?
I wanted to use this project as a way to experiment how bad it is if no frameworks are used. I am not planning to change this, as this is a living evidence telling us why PHP (especially without server frameworks) is bad.

## Features
### Poggit-CI: plugin builder
Poggit will build phars for your project when you push a commit or make a pull request.

Login on the [Poggit website](https://poggit.pmmp.io) and authorize the Poggit application for your user account or your organizations. You can find buttons to enable Poggit-CI for particular repos at [the CI admin panel](https://poggit.pmmp.io/ci). Poggit will help you create the file `.poggit.yml` in your repo, and then Poggit will start building your projects in your repo every commit.

### Poggit-Release: plugin list
A project can be released after it has a development build. You can find the release button in the CI project page.

You can find a list of released plugins at https://poggit.pmmp.io/plugins. You can also find plugins pending review at https://poggit.pmmp.io/review.

### Virions
Poggit provides a library framework called ["Virions"](https://poggit.pmmp.io/virion).

## Planned features
* Generate docs for plugins
* Power an online translation platform for plugins
* Manage a plugin requests list to substitute https://forums.pmmp.io/forums/requests

## Can I host it myself?
Yes, technically you can, although discouraged.

Poggit manages a website that allows users to find plugins. Therefore, if everyone creates their own Poggit website, it will be confusing for users as plugin lists are scattered everywhere. For the sake of the community, unless you have improved your version of Poggit so much that the original version is no longer worth existing, please don't host a public Poggit yourself.

However, for various reasons, mainly that I am a stubborn Free Software supporter, you can still host a Poggit instance yourself. This project is licensed under the Apache license, Version 2.0. You can obtain a full copy of this license at the [LICENSE](LICENSE) file.

Nevertheless, Poggit is open-sourced for developers, not businesses. It is here for developers to improve it, or to learn from it, _"to build software better, **together**"_. You are welcome if you want to host Poggit yourself for security reasons, or for testing. But if you are hosting Poggit for profit reasons, **I politely ask you not to do that**.

## Installation
**Please** read [_Can I host it myself?_](#can-i-host-it-myself) before installing Poggit.

Then, refer to [INSTALL.md](INSTALL.md) for instructions to install Poggit.
