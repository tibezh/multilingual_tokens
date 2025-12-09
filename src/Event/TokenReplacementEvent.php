<?php

namespace Drupal\multilingual_tokens\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Render\BubbleableMetadata;

/**
 * Event fired before multilingual token replacement.
 */
class TokenReplacementEvent extends Event {

  /**
   * Event name.
   */
  const string NAME = 'multilingual_tokens.before_replacement';

  /**
   * The replacement value.
   */
  protected ?string $replacement = NULL;

  /**
   * Whether the replacement should be skipped.
   */
  protected bool $skipReplacement = FALSE;

  /**
   * Constructs a new TokenReplacementEvent.
   */
  public function __construct(
    protected string $tokenType,
    protected string $baseToken,
    protected string $langcode,
    protected string $originalToken,
    protected ContentEntityInterface $entity,
    protected ContentEntityInterface $translatedEntity,
    protected array $data,
    protected array $options,
    protected BubbleableMetadata $bubbleableMetadata
  ) { }

  /**
   * Gets the token type.
   *
   * @return string
   *   The token type (e.g., 'node', 'user').
   */
  public function getTokenType(): string {
    return $this->tokenType;
  }

  /**
   * Gets the base token name.
   *
   * @return string
   *   The base token name without the language suffix.
   */
  public function getBaseToken(): string {
    return $this->baseToken;
  }

  /**
   * Gets the language code.
   *
   * @return string
   *   The language code (e.g., 'en', 'de', 'fr').
   */
  public function getLangcode(): string {
    return $this->langcode;
  }

  /**
   * Gets the original token.
   *
   * @return string
   *   The original token string including brackets.
   */
  public function getOriginalToken(): string {
    return $this->originalToken;
  }

  /**
   * Gets the original entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The original entity in its default language.
   */
  public function getEntity(): ContentEntityInterface {
    return $this->entity;
  }

  /**
   * Gets the translated entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity translation for the requested language.
   */
  public function getTranslatedEntity(): ContentEntityInterface {
    return $this->translatedEntity;
  }

  /**
   * Sets the translated entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The translated entity to use for token replacement.
   */
  public function setTranslatedEntity(ContentEntityInterface $entity): void {
    $this->translatedEntity = $entity;
  }

  /**
   * Gets the token data.
   *
   * @return array
   *   An associative array of data objects for token replacement.
   */
  public function getData(): array {
    return $this->data;
  }

  /**
   * Sets the token data.
   *
   * @param array $data
   *   An associative array of data objects for token replacement.
   */
  public function setData(array $data): void {
    $this->data = $data;
  }

  /**
   * Gets the token options.
   *
   * @return array
   *   An associative array of options for token replacement.
   */
  public function getOptions(): array {
    return $this->options;
  }

  /**
   * Sets the token options.
   *
   * @param array $options
   *   An associative array of options for token replacement.
   */
  public function setOptions(array $options): void {
    $this->options = $options;
  }

  /**
   * Gets the bubbleable metadata.
   *
   * @return \Drupal\Core\Render\BubbleableMetadata
   *   The bubbleable metadata for cacheability.
   */
  public function getBubbleableMetadata(): BubbleableMetadata {
    return $this->bubbleableMetadata;
  }

  /**
   * Gets the custom replacement value.
   *
   * @return string|null
   *   The custom replacement value, or NULL if not set.
   */
  public function getReplacement(): ?string {
    return $this->replacement;
  }

  /**
   * Sets a custom replacement value.
   *
   * @param string $replacement
   *   The custom replacement value.
   * @param bool $skipReplacement
   *   If TRUE, also marks the default replacement to be skipped.
   */
  public function setReplacement(string $replacement, bool $skipReplacement = FALSE): void {
    $this->replacement = $replacement;
    if ($skipReplacement) {
      $this->skipReplacement();
    }
  }

  /**
   * Checks if replacement should be skipped.
   *
   * @return bool
   *   TRUE if the default replacement should be skipped, FALSE otherwise.
   */
  public function shouldSkipReplacement(): bool {
    return $this->skipReplacement;
  }

  /**
   * Marks this token replacement to be skipped.
   *
   * When called, the default token replacement logic will not be applied.
   */
  public function skipReplacement(): void {
    $this->skipReplacement = TRUE;
  }

  /**
   * Checks if a custom replacement value has been set.
   *
   * @return bool
   *   TRUE if a custom replacement was set, FALSE otherwise.
   */
  public function hasCustomReplacement(): bool {
    return $this->replacement !== NULL;
  }

}
