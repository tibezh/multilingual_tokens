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

## DEVELOPERS

### TokenReplacementEvent

The module dispatches a `TokenReplacementEvent` before processing each
multilingual token. This allows other modules to customize or override the
token replacement logic.

**Event name:** `multilingual_tokens.before_replacement`

**Example event subscriber:**

```php
<?php

namespace Drupal\my_module\EventSubscriber;

use Drupal\multilingual_tokens\Event\TokenReplacementEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class TokenSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      TokenReplacementEvent::NAME => 'onTokenReplacement',
    ];
  }

  /**
   * Handles multilingual token replacement.
   */
  public function onTokenReplacement(TokenReplacementEvent $event): void {
    // Get token information.
    $token_type = $event->getTokenType();
    $base_token = $event->getBaseToken();
    $langcode = $event->getLangcode();
    $entity = $event->getEntity();
    $translated_entity = $event->getTranslatedEntity();

    // Option 1: Provide custom replacement.
    $event->setReplacement('Custom value');

    // Option 2: Skip this token (no replacement).
    $event->skipReplacement();

    // Option 3: Modify the translated entity.
    $event->setTranslatedEntity($different_entity);
  }

}
```

**Register the subscriber in `my_module.services.yml`:**

```yaml
services:
  my_module.token_subscriber:
    class: Drupal\my_module\EventSubscriber\TokenSubscriber
    tags:
      - { name: event_subscriber }
```
