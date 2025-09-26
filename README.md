# Multilingual Tokens

## INTRODUCTION

The Multilingual Tokens module provides tokens with multilingual support for Drupal sites. It allows you to access translated content in specific languages regardless of the current site language.

## REQUIREMENTS

This module requires the following Drupal core modules:
* Language
* Content Translation

Contributed modules:
* Token

## INSTALLATION

Install as you would normally install a contributed Drupal module. For further
information, see [Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

## CONFIGURATION

The module has no menu or modifiable settings. There is no configuration.

## USAGE

### Language-specific entity tokens

This module adds language-specific tokens for translatable content entities. These tokens allow you to display content in a specific language, regardless of the current site language.

The format for these tokens is:
```
[entity-type:field-name:_lang_LANGCODE]
```

Where:
- `entity-type` is the type of entity (e.g., node, user, taxonomy_term);
- `field-name` is the name of the field (e.g., title, body);
- `LANGCODE` is the language code (e.g., en, uk, fr, en-gb, en_gb).

#### Examples:

- `[node:title:_lang_uk]` - Displays the node title in Ukrainian, even if the current site language is English;
- `[node:body:_lang_en]` - Displays the node body in English, even if the current site language is Ukrainian;
- `[taxonomy_term:name:_lang_fr]` - Displays the taxonomy term name in French;
- `[node:title:_lang_en-gb]` - Displays the node title in British English (the `en-gb` suffix is langcode);
- `[node:body:_lang_en_us]` - Displays the node body in American English (the `en_us` suffix is langcode).
