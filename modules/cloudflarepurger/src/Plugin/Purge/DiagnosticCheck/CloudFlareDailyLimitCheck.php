<?php

/**
 * @file
 * Contains \Drupal\cloudflare\Plugin\CloudFlareDailyLimitCheck.
 */

namespace Drupal\cloudflarepurger\Plugin\Purge\DiagnosticCheck;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\purge\Plugin\Purge\DiagnosticCheck\DiagnosticCheckBase;
use Drupal\purge\Plugin\Purge\DiagnosticCheck\DiagnosticCheckInterface;
use Drupal\cloudflare\CloudFlareStateInterface;
use CloudFlarePhpSdk\ApiEndpoints\CloudFlareAPI;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Checks that the site is within CloudFlare's Api Daily Rate limit.
 *
 * CloudFlare currently has a rate limit of 200 purges/day.
 *
 * @see https://support.cloudflare.com/hc/en-us/articles/206596608-How-to-Purge-Cache-Using-Cache-Tags
 *
 * @PurgeDiagnosticCheck(
 *   id = "cloudflare_daily_limit_check",
 *   title = @Translation("CloudFlare - Daily Tag Purge Limit"),
 *   description = @Translation("Checks that the site is not violating CloudFlare's daily purge limit."),
 *   dependent_queue_plugins = {},
 *   dependent_purger_plugins = {}
 * )
 */
class CloudFlareDailyLimitCheck extends DiagnosticCheckBase implements DiagnosticCheckInterface {

  /**
   * Tracks rate limits associated with CloudFlare Api.
   *
   * @var \Drupal\cloudflare\CloudFlareStateInterface
   */
  protected $state;

  /**
   * Constructs a CloudFlareDailyLimitCheck object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\cloudflare\CloudFlareStateInterface $state
   *   Tracks rate limits associated with CloudFlare Api.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CloudFlareStateInterface $state) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('cloudflare.state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function run() {
    // Current number of purges today.
    $daily_count = $this->state->getTagDailyCount();
    $this->value = $daily_count;

    // Warn at 75% of capacity.
    $daily_warning_level = .75 * CloudFlareAPI::API_TAG_PURGE_DAILY_RATE_LIMIT;

    $message_variables = [
      ':daily_limit' => CloudFlareAPI::API_TAG_PURGE_DAILY_RATE_LIMIT,
      ':$daily_count' => $daily_count,
    ];

    if ($daily_count < $daily_warning_level) {
      $this->recommendation = $this->t('Site is safely below the daily limit of :daily_limit tag purges/day.', $message_variables);
      return SELF::SEVERITY_OK;
    }

    elseif ($daily_count >= $daily_warning_level) {
      $this->recommendation = $this->t('Approaching Api limit of :daily_count/:daily_limit limit tag purges/day.', $message_variables);
      return SELF::SEVERITY_WARNING;
    }

    elseif ($daily_count > CloudFlareAPI::API_TAG_PURGE_DAILY_RATE_LIMIT) {
      $this->recommendation = $this->t('Past Api limit of :daily_count/:daily_limit limit tag purges/day.', $message_variables);
      return SELF::SEVERITY_ERROR;
    }

    return SELF::SEVERITY_OK;
  }

}
