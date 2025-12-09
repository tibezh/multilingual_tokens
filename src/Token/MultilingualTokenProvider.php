<?php

namespace Drupal\multilingual_tokens\Token;

use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Token;
use Drupal\multilingual_tokens\Event\TokenReplacementEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides multilingual tokens.
 */
class MultilingualTokenProvider {

  use StringTranslationTrait;

  /**
   * Tracks tokens currently being processed to prevent infinite recursion.
   *
   * @var array<string, bool>
   */
  protected static array $processing = [];

  /**
   * Constructs a MultilingualTokenProvider object.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\content_translation\ContentTranslationManagerInterface $contentTranslationManager
   *   The content translation manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(
    protected LanguageManagerInterface $languageManager,
    protected EntityRepositoryInterface $entityRepository,
    protected Token $token,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ContentTranslationManagerInterface $contentTranslationManager,
    protected EventDispatcherInterface $eventDispatcher,
  ) {}

  /**
   * Gets the token info provided by this module.
   *
   * @return array<string, array<string, array<string, array<string, string>>>>
   *   An array of token info.
   */
  public function getTokenInfo(): array {
    $content_entity_types = $this->getContentEntityTypes();
    return $this->buildTokenInfoForLanguages($content_entity_types);
  }

  /**
   * Gets all content entity types.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface[]
   *   An array of content entity types.
   */
  protected function getContentEntityTypes(): array {
    $entity_types = $this->entityTypeManager->getDefinitions();
    return array_filter(
      $entity_types,
      fn($entity_type) => $entity_type->entityClassImplements(ContentEntityInterface::class)
    );
  }

  /**
   * Builds token info for all languages and content entity types.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface[] $content_entity_types
   *   An array of content entity types.
   *
   * @return array<string, array<string, array<string, array<string, string>>>>
   *   An array of token info.
   */
  protected function buildTokenInfoForLanguages(array $content_entity_types): array {
    $info = [];
    $languages = $this->languageManager->getLanguages();

    foreach ($languages as $langcode => $language) {
      foreach ($content_entity_types as $entity_type_id => $entity_type) {
        if ($this->contentTranslationManager->isEnabled($entity_type_id)) {
          $info['tokens'][$entity_type_id]["*:_lang_{$langcode}"] = [
            'name' => $this->t('Field in @language', ['@language' => $language->getName()]),
            'description' => $this->t(
              'The value of a field in @language language, regardless of current site language.',
              ['@language' => $language->getName()]
            ),
          ];
        }
      }
    }

    return $info;
  }

  /**
   * Parses a token name to extract language suffix information.
   *
   * @param string $name
   *   The token name (without brackets).
   *
   * @return array{base_token: string, langcode: string}|null
   *   Array with 'base_token' and 'langcode' keys, or NULL if not
   *   a language token.
   */
  protected function parseLanguageToken(string $name): ?array {
    if (preg_match('/^(.+):_lang_([a-z\-_]+)$/', $name, $matches)) {
      return [
        'base_token' => $matches[1],
        'langcode' => $matches[2],
      ];
    }
    return NULL;
  }

  /**
   * Replaces multilingual tokens with translated values.
   *
   * @param string $type
   *   The token type (e.g., 'node', 'user').
   * @param array<string, string> $tokens
   *   An array of tokens to be replaced, keyed by token name.
   * @param array<string, mixed> $data
   *   An associative array of data objects to use for token replacement.
   * @param array<string, mixed> $options
   *   An associative array of options for token replacement.
   * @param \Drupal\Core\Render\BubbleableMetadata $bubbleable_metadata
   *   The bubbleable metadata for cacheability.
   *
   * @return array<string, string>
   *   An array of replacement values keyed by the original token.
   */
  public function replaceTokens(
    string $type,
    array $tokens,
    array $data,
    array $options,
    BubbleableMetadata $bubbleable_metadata,
  ): array {
    $replacements = [];

    foreach ($tokens as $name => $original) {
      $parsed_token = $this->parseLanguageToken($name);
      if (!$parsed_token) {
        continue;
      }

      // Recursion protection.
      $recursion_key = $type . ':' . $name;
      if (isset(static::$processing[$recursion_key])) {
        continue;
      }

      static::$processing[$recursion_key] = TRUE;

      try {
        // Check if entity exists.
        if (isset($data[$type]) && $data[$type] instanceof ContentEntityInterface) {
          $entity = $data[$type];

          // Check entity translations.
          if ($entity->isTranslatable() && $entity->hasTranslation($parsed_token['langcode'])) {
            $translated_entity = $entity->getTranslation($parsed_token['langcode']);

            // Dispatch event to allow other modules to modify replacement.
            $event = new TokenReplacementEvent(
              $type,
              $parsed_token['base_token'],
              $parsed_token['langcode'],
              $original,
              $entity,
              $translated_entity,
              $data,
              $options,
              $bubbleable_metadata
            );
            $this->eventDispatcher->dispatch($event, TokenReplacementEvent::NAME);

            // Check if event subscriber provided custom replacement.
            if ($event->hasCustomReplacement()) {
              $replacements[$original] = $event->getReplacement();
              continue;
            }

            // Check if replacement should be skipped.
            if ($event->shouldSkipReplacement()) {
              continue;
            }

            // Create new data for token replace.
            $translation_data = array_merge($data, [
              $type => $translated_entity,
            ]);

            // Apply language context.
            $translation_options = array_merge($options, [
              'langcode' => $parsed_token['langcode'],
            ]);

            // Create full base token (entity_type:field_name).
            $full_base_token = "{$type}:{$parsed_token['base_token']}";
            $base_token_markup = "[{$full_base_token}]";

            $replacement = $this->token->replace(
              $base_token_markup,
              $translation_data,
              $translation_options,
              $bubbleable_metadata
            );

            // Add to results if replacement is successful.
            if ($replacement !== $base_token_markup) {
              $replacements[$original] = $replacement;
            }
          }
        }
      } finally {
        unset(static::$processing[$recursion_key]);
      }
    }

    return $replacements;
  }

}
