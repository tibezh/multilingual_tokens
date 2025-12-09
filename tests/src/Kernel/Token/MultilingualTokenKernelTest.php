<?php

declare(strict_types=1);

namespace Drupal\Tests\multilingual_tokens\Kernel\Token;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\multilingual_tokens\Event\TokenReplacementEvent;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Tests multilingual token replacement with real Drupal services.
 *
 * @group multilingual_tokens
 */
class MultilingualTokenKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'filter',
    'language',
    'content_translation',
    'token',
    'multilingual_tokens',
  ];

  /**
   * The token provider service.
   *
   * @var \Drupal\multilingual_tokens\Token\MultilingualTokenProvider
   */
  protected $tokenProvider;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $tokenService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['system', 'field', 'filter', 'node', 'language']);

    // Create a German language.
    ConfigurableLanguage::createFromLangcode('de')->save();

    // Create a content type.
    $node_type = NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ]);
    $node_type->save();

    // Enable translation for articles.
    $this->container->get('content_translation.manager')
      ->setEnabled('node', 'article', TRUE);

    $this->tokenProvider = $this->container->get('multilingual_tokens.token_provider');
    $this->tokenService = $this->container->get('token');
  }

  /**
   * Tests token info is registered for translatable entity types.
   */
  public function testTokenInfoRegisteredForEnabledEntityTypes(): void {
    $token_info = $this->tokenProvider->getTokenInfo();

    $this->assertArrayHasKey('tokens', $token_info);
    $this->assertArrayHasKey('node', $token_info['tokens']);
    $this->assertArrayHasKey('*:_lang_en', $token_info['tokens']['node']);
    $this->assertArrayHasKey('*:_lang_de', $token_info['tokens']['node']);
  }

  /**
   * Tests token replacement with a translated node.
   */
  public function testTokenReplacementWithTranslation(): void {
    // Create a node with a German translation.
    $node = Node::create([
      'type' => 'article',
      'title' => 'English Title',
      'langcode' => 'en',
    ]);
    $node->save();

    // Add German translation.
    $node->addTranslation('de', [
      'title' => 'German Title',
    ]);
    $node->save();

    $bubbleable_metadata = new BubbleableMetadata();
    $tokens = [
      'title:_lang_de' => '[node:title:_lang_de]',
    ];

    $replacements = $this->tokenProvider->replaceTokens(
      'node',
      $tokens,
      ['node' => $node],
      [],
      $bubbleable_metadata
    );

    $this->assertArrayHasKey('[node:title:_lang_de]', $replacements);
    $this->assertEquals('German Title', $replacements['[node:title:_lang_de]']);
  }

  /**
   * Tests token replacement returns empty when translation does not exist.
   */
  public function testTokenReplacementWithoutTranslation(): void {
    // Create a node without French translation.
    $node = Node::create([
      'type' => 'article',
      'title' => 'English Title',
      'langcode' => 'en',
    ]);
    $node->save();

    $bubbleable_metadata = new BubbleableMetadata();
    $tokens = [
      'title:_lang_fr' => '[node:title:_lang_fr]',
    ];

    $replacements = $this->tokenProvider->replaceTokens(
      'node',
      $tokens,
      ['node' => $node],
      [],
      $bubbleable_metadata
    );

    $this->assertEmpty($replacements);
  }

  /**
   * Tests the event is dispatched during token replacement.
   */
  public function testTokenReplacementEventDispatched(): void {
    // Create a node with a German translation.
    $node = Node::create([
      'type' => 'article',
      'title' => 'English Title',
      'langcode' => 'en',
    ]);
    $node->save();
    $node->addTranslation('de', ['title' => 'German Title']);
    $node->save();

    // Create a test event subscriber.
    $eventCaptured = NULL;
    $subscriber = new class($eventCaptured) implements EventSubscriberInterface {

      /**
       * Reference to capture the event.
       *
       * @var \Drupal\multilingual_tokens\Event\TokenReplacementEvent|null
       */
      private $eventRef;

      /**
       * Constructs the subscriber.
       */
      public function __construct(&$eventRef) {
        $this->eventRef = &$eventRef;
      }

      /**
       * {@inheritdoc}
       */
      public static function getSubscribedEvents(): array {
        return [
          TokenReplacementEvent::NAME => 'onTokenReplacement',
        ];
      }

      /**
       * Handles the token replacement event.
       */
      public function onTokenReplacement(TokenReplacementEvent $event): void {
        $this->eventRef = $event;
      }

    };

    $this->container->get('event_dispatcher')->addSubscriber($subscriber);

    $bubbleable_metadata = new BubbleableMetadata();
    $tokens = [
      'title:_lang_de' => '[node:title:_lang_de]',
    ];

    $this->tokenProvider->replaceTokens(
      'node',
      $tokens,
      ['node' => $node],
      [],
      $bubbleable_metadata
    );

    $this->assertInstanceOf(TokenReplacementEvent::class, $eventCaptured);
    $this->assertEquals('node', $eventCaptured->getTokenType());
    $this->assertEquals('title', $eventCaptured->getBaseToken());
    $this->assertEquals('de', $eventCaptured->getLangcode());
  }

  /**
   * Tests custom replacement via event subscriber.
   */
  public function testCustomReplacementViaEventSubscriber(): void {
    // Create a node with a German translation.
    $node = Node::create([
      'type' => 'article',
      'title' => 'English Title',
      'langcode' => 'en',
    ]);
    $node->save();
    $node->addTranslation('de', ['title' => 'German Title']);
    $node->save();

    // Create a test event subscriber that provides custom replacement.
    $subscriber = new class() implements EventSubscriberInterface {

      /**
       * {@inheritdoc}
       */
      public static function getSubscribedEvents(): array {
        return [
          TokenReplacementEvent::NAME => 'onTokenReplacement',
        ];
      }

      /**
       * Handles the token replacement event.
       */
      public function onTokenReplacement(TokenReplacementEvent $event): void {
        $event->setReplacement('Custom Replacement Value');
      }

    };

    $this->container->get('event_dispatcher')->addSubscriber($subscriber);

    $bubbleable_metadata = new BubbleableMetadata();
    $tokens = [
      'title:_lang_de' => '[node:title:_lang_de]',
    ];

    $replacements = $this->tokenProvider->replaceTokens(
      'node',
      $tokens,
      ['node' => $node],
      [],
      $bubbleable_metadata
    );

    $this->assertEquals('Custom Replacement Value', $replacements['[node:title:_lang_de]']);
  }

  /**
   * Tests skip replacement via event subscriber.
   */
  public function testSkipReplacementViaEventSubscriber(): void {
    // Create a node with a German translation.
    $node = Node::create([
      'type' => 'article',
      'title' => 'English Title',
      'langcode' => 'en',
    ]);
    $node->save();
    $node->addTranslation('de', ['title' => 'German Title']);
    $node->save();

    // Create a test event subscriber that skips replacement.
    $subscriber = new class() implements EventSubscriberInterface {

      /**
       * {@inheritdoc}
       */
      public static function getSubscribedEvents(): array {
        return [
          TokenReplacementEvent::NAME => 'onTokenReplacement',
        ];
      }

      /**
       * Handles the token replacement event.
       */
      public function onTokenReplacement(TokenReplacementEvent $event): void {
        $event->skipReplacement();
      }

    };

    $this->container->get('event_dispatcher')->addSubscriber($subscriber);

    $bubbleable_metadata = new BubbleableMetadata();
    $tokens = [
      'title:_lang_de' => '[node:title:_lang_de]',
    ];

    $replacements = $this->tokenProvider->replaceTokens(
      'node',
      $tokens,
      ['node' => $node],
      [],
      $bubbleable_metadata
    );

    $this->assertEmpty($replacements);
  }

  /**
   * Tests integration with hook_tokens() via the token service.
   */
  public function testIntegrationWithTokenService(): void {
    // Create a node with a German translation.
    $node = Node::create([
      'type' => 'article',
      'title' => 'English Title',
      'langcode' => 'en',
    ]);
    $node->save();
    $node->addTranslation('de', ['title' => 'German Title']);
    $node->save();

    // Use the token service to replace the multilingual token.
    $result = $this->tokenService->replace(
      '[node:title:_lang_de]',
      ['node' => $node]
    );

    $this->assertEquals('German Title', $result);
  }

  /**
   * Tests that non-multilingual tokens are not affected.
   */
  public function testNonMultilingualTokensUnaffected(): void {
    // Create a node.
    $node = Node::create([
      'type' => 'article',
      'title' => 'Test Title',
      'langcode' => 'en',
    ]);
    $node->save();

    // Use the token service to replace a regular token.
    $result = $this->tokenService->replace(
      '[node:title]',
      ['node' => $node]
    );

    $this->assertEquals('Test Title', $result);
  }

}
