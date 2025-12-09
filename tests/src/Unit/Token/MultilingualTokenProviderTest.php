<?php

declare(strict_types=1);

namespace Drupal\Tests\multilingual_tokens\Unit\Token;

use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Utility\Token;
use Drupal\multilingual_tokens\Event\TokenReplacementEvent;
use Drupal\multilingual_tokens\Token\MultilingualTokenProvider;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Tests the MultilingualTokenProvider service.
 *
 * @coversDefaultClass \Drupal\multilingual_tokens\Token\MultilingualTokenProvider
 * @group multilingual_tokens
 */
class MultilingualTokenProviderTest extends UnitTestCase {

  /**
   * The language manager mock.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $languageManager;

  /**
   * The entity repository mock.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityRepository;

  /**
   * The token service mock.
   *
   * @var \Drupal\Core\Utility\Token|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $token;

  /**
   * The entity type manager mock.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The content translation manager mock.
   *
   * @var \Drupal\content_translation\ContentTranslationManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $contentTranslationManager;

  /**
   * The event dispatcher mock.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $eventDispatcher;

  /**
   * The service under test.
   *
   * @var \Drupal\multilingual_tokens\Token\MultilingualTokenProvider
   */
  protected $tokenProvider;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->languageManager = $this->createMock(LanguageManagerInterface::class);
    $this->entityRepository = $this->createMock(EntityRepositoryInterface::class);
    $this->token = $this->createMock(Token::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->contentTranslationManager = $this->createMock(ContentTranslationManagerInterface::class);
    $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

    $this->tokenProvider = new MultilingualTokenProvider(
      $this->languageManager,
      $this->entityRepository,
      $this->token,
      $this->entityTypeManager,
      $this->contentTranslationManager,
      $this->eventDispatcher
    );

    // Set up string translation.
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translateString')
      ->willReturnCallback(fn($string) => $string);
    $this->tokenProvider->setStringTranslation($translation);
  }

  /**
   * Tests parseLanguageToken with valid language tokens.
   *
   * @covers ::parseLanguageToken
   * @dataProvider validLanguageTokenProvider
   */
  public function testParseLanguageTokenValid(string $tokenName, string $expectedBase, string $expectedLangcode): void {
    $method = new \ReflectionMethod($this->tokenProvider, 'parseLanguageToken');

    $result = $method->invoke($this->tokenProvider, $tokenName);

    $this->assertIsArray($result);
    $this->assertArrayHasKey('base_token', $result);
    $this->assertArrayHasKey('langcode', $result);
    $this->assertEquals($expectedBase, $result['base_token']);
    $this->assertEquals($expectedLangcode, $result['langcode']);
  }

  /**
   * Data provider for valid language tokens.
   *
   * @return array
   *   Test cases with token name, expected base, and expected langcode.
   */
  public static function validLanguageTokenProvider(): array {
    return [
      'simple field with en' => ['title:_lang_en', 'title', 'en'],
      'simple field with de' => ['title:_lang_de', 'title', 'de'],
      'field with underscore langcode' => ['body:_lang_pt_br', 'body', 'pt_br'],
      'field with hyphen langcode' => ['field_name:_lang_zh-hans', 'field_name', 'zh-hans'],
      'nested token' => ['field_reference:entity:title:_lang_fr', 'field_reference:entity:title', 'fr'],
    ];
  }

  /**
   * Tests parseLanguageToken with invalid tokens.
   *
   * @covers ::parseLanguageToken
   * @dataProvider invalidLanguageTokenProvider
   */
  public function testParseLanguageTokenInvalid(string $tokenName): void {
    $method = new \ReflectionMethod($this->tokenProvider, 'parseLanguageToken');

    $result = $method->invoke($this->tokenProvider, $tokenName);

    $this->assertNull($result);
  }

  /**
   * Data provider for invalid language tokens.
   *
   * @return array
   *   Test cases with invalid token names.
   */
  public static function invalidLanguageTokenProvider(): array {
    return [
      'regular token without lang suffix' => ['title'],
      'token with wrong prefix' => ['title:lang_en'],
      'token with uppercase langcode' => ['title:_lang_EN'],
      'empty string' => [''],
      'only lang suffix' => [':_lang_en'],
    ];
  }

  /**
   * Tests getTokenInfo returns expected structure.
   *
   * @covers ::getTokenInfo
   * @covers ::getContentEntityTypes
   * @covers ::buildTokenInfoForLanguages
   */
  public function testGetTokenInfoReturnsExpectedStructure(): void {
    // Set up entity types.
    $nodeEntityType = $this->createMock(EntityTypeInterface::class);
    $nodeEntityType->method('entityClassImplements')
      ->with(ContentEntityInterface::class)
      ->willReturn(TRUE);

    $configEntityType = $this->createMock(EntityTypeInterface::class);
    $configEntityType->method('entityClassImplements')
      ->with(ContentEntityInterface::class)
      ->willReturn(FALSE);

    $this->entityTypeManager->method('getDefinitions')
      ->willReturn([
        'node' => $nodeEntityType,
        'config_entity' => $configEntityType,
      ]);

    // Set up languages.
    $englishLanguage = $this->createMock(LanguageInterface::class);
    $englishLanguage->method('getName')->willReturn('English');

    $germanLanguage = $this->createMock(LanguageInterface::class);
    $germanLanguage->method('getName')->willReturn('German');

    $this->languageManager->method('getLanguages')
      ->willReturn([
        'en' => $englishLanguage,
        'de' => $germanLanguage,
      ]);

    // Set up content translation.
    $this->contentTranslationManager->method('isEnabled')
      ->willReturnMap([
        ['node', TRUE],
        ['config_entity', FALSE],
      ]);

    $result = $this->tokenProvider->getTokenInfo();

    $this->assertIsArray($result);
    $this->assertArrayHasKey('tokens', $result);
    $this->assertArrayHasKey('node', $result['tokens']);
    $this->assertArrayHasKey('*:_lang_en', $result['tokens']['node']);
    $this->assertArrayHasKey('*:_lang_de', $result['tokens']['node']);
    $this->assertArrayNotHasKey('config_entity', $result['tokens']);
  }

  /**
   * Tests replaceTokens skips non-language tokens.
   *
   * @covers ::replaceTokens
   */
  public function testReplaceTokensSkipsNonLanguageTokens(): void {
    $tokens = [
      'title' => '[node:title]',
      'body' => '[node:body]',
    ];
    $data = [];
    $options = [];
    $bubbleableMetadata = new BubbleableMetadata();

    $result = $this->tokenProvider->replaceTokens('node', $tokens, $data, $options, $bubbleableMetadata);

    $this->assertEmpty($result);
  }

  /**
   * Tests replaceTokens with valid language token and translation.
   *
   * @covers ::replaceTokens
   */
  public function testReplaceTokensWithTranslation(): void {
    $tokens = [
      'title:_lang_de' => '[node:title:_lang_de]',
    ];

    // Create mock entity.
    $translatedEntity = $this->createMock(ContentEntityInterface::class);
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('isTranslatable')->willReturn(TRUE);
    $entity->method('hasTranslation')->with('de')->willReturn(TRUE);
    $entity->method('getTranslation')->with('de')->willReturn($translatedEntity);

    $data = ['node' => $entity];
    $options = [];
    $bubbleableMetadata = new BubbleableMetadata();

    // Set up event dispatcher.
    $this->eventDispatcher->method('dispatch')
      ->willReturnCallback(function ($event, $eventName) {
        $this->assertInstanceOf(TokenReplacementEvent::class, $event);
        $this->assertEquals(TokenReplacementEvent::NAME, $eventName);
        return $event;
      });

    // Set up token replacement.
    $this->token->method('replace')
      ->willReturn('German Title');

    $result = $this->tokenProvider->replaceTokens('node', $tokens, $data, $options, $bubbleableMetadata);

    $this->assertArrayHasKey('[node:title:_lang_de]', $result);
    $this->assertEquals('German Title', $result['[node:title:_lang_de]']);
  }

  /**
   * Tests replaceTokens skips when entity has no translation.
   *
   * @covers ::replaceTokens
   */
  public function testReplaceTokensSkipsWhenNoTranslation(): void {
    $tokens = [
      'title:_lang_de' => '[node:title:_lang_de]',
    ];

    // Create mock entity without translation.
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('isTranslatable')->willReturn(TRUE);
    $entity->method('hasTranslation')->with('de')->willReturn(FALSE);

    $data = ['node' => $entity];
    $options = [];
    $bubbleableMetadata = new BubbleableMetadata();

    $result = $this->tokenProvider->replaceTokens('node', $tokens, $data, $options, $bubbleableMetadata);

    $this->assertEmpty($result);
  }

  /**
   * Tests replaceTokens skips when entity is not translatable.
   *
   * @covers ::replaceTokens
   */
  public function testReplaceTokensSkipsWhenNotTranslatable(): void {
    $tokens = [
      'title:_lang_de' => '[node:title:_lang_de]',
    ];

    // Create mock entity that is not translatable.
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('isTranslatable')->willReturn(FALSE);

    $data = ['node' => $entity];
    $options = [];
    $bubbleableMetadata = new BubbleableMetadata();

    $result = $this->tokenProvider->replaceTokens('node', $tokens, $data, $options, $bubbleableMetadata);

    $this->assertEmpty($result);
  }

  /**
   * Tests replaceTokens uses custom replacement from event.
   *
   * @covers ::replaceTokens
   */
  public function testReplaceTokensUsesCustomReplacementFromEvent(): void {
    $tokens = [
      'title:_lang_de' => '[node:title:_lang_de]',
    ];

    // Create mock entity.
    $translatedEntity = $this->createMock(ContentEntityInterface::class);
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('isTranslatable')->willReturn(TRUE);
    $entity->method('hasTranslation')->with('de')->willReturn(TRUE);
    $entity->method('getTranslation')->with('de')->willReturn($translatedEntity);

    $data = ['node' => $entity];
    $options = [];
    $bubbleableMetadata = new BubbleableMetadata();

    // Set up event dispatcher to set custom replacement.
    $this->eventDispatcher->method('dispatch')
      ->willReturnCallback(function (TokenReplacementEvent $event, $eventName) {
        $event->setReplacement('Custom Replacement');
        return $event;
      });

    $result = $this->tokenProvider->replaceTokens('node', $tokens, $data, $options, $bubbleableMetadata);

    $this->assertArrayHasKey('[node:title:_lang_de]', $result);
    $this->assertEquals('Custom Replacement', $result['[node:title:_lang_de]']);
  }

  /**
   * Tests replaceTokens skips when event marks skip.
   *
   * @covers ::replaceTokens
   */
  public function testReplaceTokensSkipsWhenEventMarksSkip(): void {
    $tokens = [
      'title:_lang_de' => '[node:title:_lang_de]',
    ];

    // Create mock entity.
    $translatedEntity = $this->createMock(ContentEntityInterface::class);
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('isTranslatable')->willReturn(TRUE);
    $entity->method('hasTranslation')->with('de')->willReturn(TRUE);
    $entity->method('getTranslation')->with('de')->willReturn($translatedEntity);

    $data = ['node' => $entity];
    $options = [];
    $bubbleableMetadata = new BubbleableMetadata();

    // Set up event dispatcher to skip replacement.
    $this->eventDispatcher->method('dispatch')
      ->willReturnCallback(function (TokenReplacementEvent $event, $eventName) {
        $event->skipReplacement();
        return $event;
      });

    $result = $this->tokenProvider->replaceTokens('node', $tokens, $data, $options, $bubbleableMetadata);

    $this->assertEmpty($result);
  }

}
