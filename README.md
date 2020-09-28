![GitHub Workflow Status](https://img.shields.io/github/workflow/status/verbruggenalex/robo-drupal/Testing%20repository?label=Build&logo=github)
![GitHub last commit](https://img.shields.io/github/last-commit/verbruggenalex/robo-drupal?label=Last%20commit&logo=github)
![GitHub issues](https://img.shields.io/github/issues/verbruggenalex/robo-drupal?label=issues&logo=github)
![Scrutinizer code quality](https://img.shields.io/scrutinizer/quality/g/verbruggenalex/robo-drupal?label=Code%20quality&logo=scrutinizer)

# Robo Drupal

A Composer package containing Robo based tasks for generating and maintaining
Drupal projects. This package has no official release yet and is currently only
used for personal reasons. So the package will be subject to change until there
is a stable release. The issue queue is open though.

## Installation

Recommended to be installed globally so you can use it anywhere on your system.

```bash
composer global require verbruggenalex/robo-drupal
```

## Usage

```bash
robo drupal:extend --name="vendor/project-name" --description="Project description"
```
