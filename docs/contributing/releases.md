---
layout: default
title: Releases
parent: Contributing
nav_order: 2
---

# Releases

Project maintainers need to create releases when a new version of Hours is released. This document outlines the versioning scheme and release process.

## Versioning

The Hours CLI is versioned following the [Semantic Versioning 2.0.0](https://semver.org) specification. Version numbers include the `v` prefix. The leading 0 is included for pre 1.0 releases.

- Bad: `0.1.0`
- Bad: `v.1.0`
- Good: `v0.1.0`

## Release Process

1. Update [the changelog](https://github.com/matt-allan/hours/blob/master/CHANGELOG.md). The changelog should be updated when changes are merged into master. It's still a good idea to review the commits with `git log` and verify that all changes are documented.

Afterwards change the `## Unreleased` header to the release number (i.e. `## v0.1.0`) and commit the change.  For example:

```console
$ sed -i '' 's/Unreleased/v0.1.0/' CHANGELOG.md
$ git add .
$ git commit -m 'Pre-release changelog'
$ git push
```

2. Create a [git tag](https://git-scm.com/book/en/v2/Git-Basics-Tagging). Use an annotated tag with a simple commit message that states what the release is, i.e. 'v0.1.0 release'. For example:

```console
$ git tag -a -m 'v0.1.0 release' v0.1.0
```

**Verify you are on master before tagging!**. New releases should only ever be tagged from the master branch.

Verify the tag was created with `git tag -l`.

After the tag is created push it to github: `git push origin <tagname>`, i.e. `git push origin v0.1.0`.

## Release Assets

The hours CLI is distributed as a [Phar](https://www.php.net/manual/en/intro.phar.php) attached to the Github release. You do not need to create this manually. When a tag is pushed to Github the CI server will automatically build the phar and attach it to the release.
