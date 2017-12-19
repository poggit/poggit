Poggit
===

[![Join the chat at https://gitter.im/poggit/Lobby](https://badges.gitter.im/poggit/Lobby.svg)](https://gitter.im/poggit/Lobby?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

## What is this?
Poggit consists of a GitHub application and a website. It is a tool for PocketMine-family plugins hosted on GitHub repos. It has the following uses:

## Features
### CI (Building)
Poggit will build phars for your project when you push a commit or make a pull request.

Login on the Poggit website and authorize the Poggit application for your user account or your organizations. You can find buttons to enable Poggit-CI for particular repos at `/ci`. Poggit will prompt to create the file `.poggit.yml` (or `.poggit/.poggit.yml`) in your repo to declare the projects to build in this repo. This example shows what Poggit can do:

```yaml
branches: master
projects:
  First:
    path: FirstPlugin
    libs:
      - src: libuncommon # name of a project in this repo
        version: 1.0 # semver constraints
        shade: syntax # shade all programmatic references to virion antigen (main namespace)
      - src: librarian/libstrange/libstrange # full path of a project from another repo on Poggit
        version: ^1.0.0 # same as those in composer
        shade: single # blindly replace all virion antigen references
      - vendor: raw
        src: libs/libodd.phar # this project has a file libs/libodd.phar, i.e. this project has a file FirstPlugin/libs/libodd.phar
        shade: double # blidnly replace all virion antigen references as well as those with the \ escaped
      - vendor: raw
        src: /globlibs/libweird.phar # this repo has a file, outside the project path of FirstPlugin, at globlibs/libweird.phar. The prefix / means that it is a path relative to repo root, not project path root.
      - vendor: raw
        src: http://libextraordinary.com/raw.phar # download online without special permissions.
  HelpsFirst: 
    path: FirstPluginAux/HelpsFirst
    model: nowhere
  another:
    path: AnotherPlugin
  libuncommon:
    path: UncommonLib
    type: library
    model: virion
```

The `branches` attribute lets you decide pushes on or pull requests to which branches Poggit should respond to.

You can load multiple projects by adding multiple entries in the `projects` attribute. This is particularly useful if there are multiple plugins on your repo.

<!-- if version [gte 2.0]
If your project is a library project, you can add the `type: library` attribute. Then other projects will be able to include it through the `libs:` attribute.
end version if -->

<!-- if version [gte 2.0]
The `docs` attribute can be added to generate docs for your project at `/docs/{LOGIN_NAME}/{REPO_NAME}/{PROJECT_NAME}` on the Poggit website. 
end version if -->

### Release (Plugin List)
A project can be released after it has a development build. You can find the release button in the CI build page.

After the build is submitted for plugin release/update, it will be added to the "Unapproved" queue. Very basic check will be conducted by appointed reviewers to filter away very low-quality plugins and some malicious plugins, before the plugin is moved to the "Deep" queue (a reference to the "Deep Web").

Only registered members can view and download plugins in the "Deep" queue. Reputable members (based on their previously released plugins as well as probably some scores from their Forums account) can vote to approve or reject plugins in the "Deep" queue based on testing. If the net score (based on approving/rejecting members' own reputation) is high enough, it can be moved to the "Unofficial" queue, where the plugin gains access to most features in Poggit Release, and is visible to everyone.

Appointed reviewers will review plugins in the "Unofficial" and "Deep" queues to move them to the official list, where it can gain all features of Poggit Release.

#### Limitations
1. Releases cannot be created from private repos. You must publicize your repo if you want to create plugin releases from it.
2. For convenience of reviewing plugins, avoid force-pushing that modifies commit history of existing releases.

<!-- if version [gte 2.0]
### Virions
end version if -->

<!-- if version [gte 2.0]
### Translation
The `lang` attribute in `poggit.yml` will add the Poggit Translations Library to the plugin's phar, and a translation website for this project will be created at `/lang/{LOGIN_NAME}/{REPO_NAME}/{PROJECT_NAME}` on the Poggit website. Poggit users will be allowed to add translations for your project using this website. You can declare the English version for each translation at `en.xml` (or `.poggit/en.xml`), which will be used to explain the translations to translators.
end version if -->

## Status
The Poggit project is currently under development, hosted on a semi-public test server at https://poggit.pmmp.io (it will be hosted by PMMP when it is production-ready). The Poggit team is currently working on these:

- [x] Poggit-CI
  - [x] Project building
    - [x] commit pushes
    - [x] pull requests
  - [x] Build listing
  - [ ] Security enforcement (anti-DoS protection)
- [ ] Poggit-Release
  - [x] Submission page
  - [ ] Systematized release reviewing
  - [ ] Plugin downloads and convenient redirecting URLs for downloading plugins
  - [ ] Plugin searching
- [ ] Writing help pages

## Can I host it myself?
Yes, technically you can, although discouraged.

Poggit manages a website that allows users to find plugins. Therefore, if everyone creates their own Poggit website, it will be confusing for users as plugin lists are scattered everywhere. For the sake of the community, unless you have improved your version of Poggit so much that the original version is no longer worth existing, please don't host a public Poggit yourself.

However, for various reasons, mainly that I am a stubborn Free Software supporter, you can still host a Poggit instance yourself. This project is licensed under the Apache license, Version 2.0. You can obtain a full copy of this license at the [LICENSE](LICENSE) file.

Nevertheless, Poggit is open-sourced for developers, not businesses. It is here for developers to improve it, or to learn from it, _"to build software better, **together**"_. You are welcome if you want to host Poggit yourself for security reasons, or for testing. But if you are hosting Poggit for profit reasons, **I politely ask you not to do that**.

## Installation
**Please** read [_Can I host it myself?_](#can-i-host-it-myself) before installing Poggit.

Then, refer to [INSTALL.md](INSTALL.md) for instructions to install Poggit.
