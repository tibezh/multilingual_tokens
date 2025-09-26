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
   */
  public function getTokenType(): string {
    return $this->tokenType;
  }

  /**
   * Gets the base token name.
   */
  public function getBaseToken(): string {
    return $this->baseToken;
  }

  /**
   * Gets the language code.
   */
  public function getLangcode(): string {
    return $this->langcode;
  }

  /**
   * Gets the original token.
   */
  public function getOriginalToken(): string {
    return $this->originalToken;
  }

  /**
   * Gets the original entity.
   */
  public function getEntity(): ContentEntityInterface {
    return $this->entity;
  }

  /**
   * Gets the translated entity.
   */
  public function getTranslatedEntity(): ContentEntityInterface {
    return $this->translatedEntity;
  }

  /**
   * Sets the translated entity.
   */
  public function setTranslatedEntity(ContentEntityInterface $entity): void {
    $this->translatedEntity = $entity;
  }

  /**
   * Gets the token data.
   */
  public function getData(): array {
    return $this->data;
  }

  /**
   * Sets the token data.
   */
  public function setData(array $data): void {
    $this->data = $data;
  }

  /**
   * Gets the token options.
   */
  public function getOptions(): array {
    return $this->options;
  }

  /**
   * Sets the token options.
   */
  public function setOptions(array $options): void {
    $this->options = $options;
  }

  /**
   * Gets the bubbleable metadata.
   */
  public function getBubbleableMetadata(): BubbleableMetadata {
    return $this->bubbleableMetadata;
  }

  /**
   * Gets the custom replacement value.
   */
  public function getReplacement(): ?string {
    return $this->replacement;
  }

  /**
   * Sets a custom replacement value.
   */
  public function setReplacement(string $replacement, bool $skipReplacement = FALSE): void {
    $this->replacement = $replacement;
    if ($skipReplacement) {
      $this->skipReplacement();
    }
  }

  /**
   * Checks if replacement should be skipped.
   */
  public function shouldSkipReplacement(): bool {
    return $this->skipReplacement;
  }

  /**
   * Marks this token to be skipped.
   */
  public function skipReplacement(): void {
    $this->skipReplacement = TRUE;
  }

  /**
   * Checks if a custom replacement is set.
   */
  public function hasCustomReplacement(): bool {
    return $this->replacement !== NULL;
  }

}
