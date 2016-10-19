Poggit
===

[![Join the chat at https://gitter.im/poggit/Lobby](https://badges.gitter.im/poggit/Lobby.svg)](https://gitter.im/poggit/Lobby?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

## What is this?
Poggit consists of a GitHub application and a website. It is a tool for PocketMine-family plugins hosted on GitHub repos. It has the following uses:

## Features
### Building
Poggit will build phars for your project when you push a commit.

Login on the Poggit website and sign in to the Poggit application for your user account or your organizations. Going back to the Poggit website again, you will find buttons that let you enable Poggit for different repos.

After you have enabled Poggit for your repo, you can edit a `.poggit.yml` (or `.poggit/.poggit.yml`) file automatically created on the default branch. This example shows what Poggit can do:

```yaml
branches: [master]
projects:
  first:
    path: FirstPlugin
    model: nowhere
    lang: v1.0
    docs: gh-pages
    libs:
      - libuncommon
      - external: librarian/libstrange/libstrange
    release:
      categories:
        - developer tools
        - ease of access
      prerelease: true
      description: {file-md: description.md}
      license: {file-raw: LICENSE.txt}
      icon: {link: http://example.com/logo}
      spoon:
        Genisys: 1.9.3
      require:
        - mysql
        - email
  helpsFirst:
    path: HelpsFirst
    model: default
    addonFor: first
  another:
    path: AnotherPlugin
  libuncommon:
    path: UncommonLib
    type: library
    export: true
```

The `branches` attribute lets you decide which branches that Poggit should respond to.

You can load multiple projects by adding multiple entries in the `projects` attribute. This is particularly useful if there are multiple plugins on your repo.

If your project is a library project, you can add the `type: library` attribute. Then other projects (and projects in other repos if you enable `export: true`) will be able to include it through the `libs:` attribute.

<!-- The `docs` attribute can be added to generate docs for your project at `/docs/{LOGIN_NAME}/{REPO_NAME}/{PROJECT_NAME}` on the Poggit website. -->

### Releasing
After enabling releases on the Poggit website, every time you create a GitHub release for your repo, Poggit will scan through the release description and find the line `Poggit release: {PROJECT_NAME}` or `Poggit pre-release: {PROJECT_NAME}` (one project per release only :cry:). Poggit will then create/update the page `/release/{LOGIN_NAME}/{REPO_NAME}/{PROJECT_NAME}` on the Poggit website, where users can download your plugin (the plugins should be released for free!), after the release being reviewed.

#### Limitations
1. Releases cannot be created from private repos. You must publicize your repo if you want to create plugin releases from it.
2. For convenience of reviewing plugins, avoid force-pushing that modifies commit history of existing releases.

### Translation
The `lang` attribute in `poggit.yml` will add the Poggit Translations Library to the plugin's phar, and a translation website for this project will be created at `/lang/{LOGIN_NAME}/{REPO_NAME}/{PROJECT_NAME}` on the Poggit website. Poggit users will be allowed to add translations for your project using this website. You can declare the English version for each translation at `en.xml` (or `.poggit/en.xml`), which will be used to explain the translations to translators.

## Status
The Poggit project is currently under development, hosted on a private server. As of Oct 9 2016, the project is already functional to create builds for default model projects (with `plugin.yml`) from direct commit push, but other parts of the website are yet far from completion.

## Can I host it myself?
Yes, you can, although discouraged.

Poggit manages a website that allows users to download plugins, to find plugins from. Therefore, if everyone creates their own Poggit website, Poggit will lose its meaning. For the sake of the community, unless you have improved your version of Poggit so much that the original version is no longer worth existing, please don't host a public Poggit yourself.

However, for various reasons, mainly that I am a stubborn Free Software supporter, you can still host a Poggit instance yourself. This project is licensed under the Apache license, Version 2.0. You can obtain a full copy of this license at the [LICENSE](LICENSE) file.

Nevertheless, Poggit is open-sourced for developers, not businesses. It is here for developers to improve it, or to learn from it, _"to build software better, **together**"_. You are welcome if you want to host Poggit yourself for security reasons, or for testing. But if you are hosting Poggit for profit reasons, **I politely ask you not to do that**.

## Why not Composer?
Simple and real answer: I don't like composer.

[@Falkirks](https://github.com/Falkirks) has created a modified version of Composer, called [Miner](https://github.com/Falkirks/Miner), for PocketMine plugins to use composer, but it is not adapted to PocketMine plugins enough. It is planned that Poggit will add features specific to PocketMine plugins that can't be used with Composer, as well as convenient deployment of PocketMine plugins.
