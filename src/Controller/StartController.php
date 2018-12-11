<?php

namespace Drupal\acquia_connector\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Class StartController.
 */
class StartController extends ControllerBase {

  /**
   * Callback for acquia_connector.start route.
   */
  public function info() {
    $build = [];

    $build['#title'] = $this->t('Get an Acquia Cloud Free subscription');

    $path = drupal_get_path('module', 'acquia_connector');

    $build['#attached']['library'][] = 'acquia_connector/acquia_connector.form';

    $banner = [
      '#theme' => 'image',
      '#attributes' => [
        'src' => Url::fromUri('base:' . $path . '/images/action.png', ['absolute' => TRUE])->toString(),
      ],
    ];
    $uri = Url::fromRoute('acquia_connector.setup', [], ['absolute' => TRUE])->toString();
    $banner = \Drupal::l($banner, Url::fromUri($uri, ['html' => TRUE]));

    $output = '<div class="an-start-form">';
    $output .= '<div id="an-pg-container">';
    $output .= '<div class="an-wrapper">';
    $output .= '<h2 id="an-info-header">' . $this->t('Acquia Subscription', ['@acquia-network' => 'http://acquia.com/products-services/acquia-network']) . '</h2>';
    $output .= '<p class="an-slogan">' . $this->t('A suite of products and services to create & maintain killer web experiences built on Drupal') . '</p>';
    $output .= '<div id="an-info-box">';
    $output .= '<div class="cell with-arrow an-left">';
    $output .= '<h2 class="cell-title"><i>' . $this->t('Answers you need') . '</i></h2>';
    $image = [
      '#theme' => 'image',
      '#attributes' => [
        'src' => Url::fromUri('base:' . $path . '/images/icon-library.png', ['absolute' => TRUE])->toString(),
      ],
    ];
    $output .= '<a href="http://library.acquia.com/" target="_blank">' . render($image) . '</a>';
    $output .= '<p class="cell-p">' . $this->t("Tap the collective knowledge of Acquia’s technical support team & partners.") . '</p>';
    $output .= '</div>';
    $output .= '<div class="cell with-arrow an-center">';
    $output .= '<h2 class="cell-title"><i>' . $this->t('Tools to extend your site') . '</i></h2>';
    $image = [
      '#theme' => 'image',
      '#attributes' => [
        'src' => Url::fromUri('base:' . $path . '/images/icon-tools.png', ['absolute' => TRUE])->toString(),
      ],
    ];
    $output .= '<a href="http://www.acquia.com/products-services/acquia-network/cloud-services" target="_blank">' . render($image) . '</a>';
    $output .= '<p class="cell-p">' . $this->t('Enhance and extend your site with an array of <a href=":services" target="_blank">services</a> from Acquia & our partners.', [':services' => 'http://www.acquia.com/products-services/acquia-network/cloud-services']) . '</p>';
    $output .= '</div>';
    $output .= '<div class="cell an-right">';
    $output .= '<h2 class="cell-title"><i>' . $this->t('Support when you want it') . '</i></h2>';
    $image = [
      '#theme' => 'image',
      '#attributes' => [
        'src' => Url::fromUri('base:' . $path . '/images/icon-support.png', ['absolute' => TRUE])->toString(),
      ],
    ];
    $output .= '<a href="http://www.acquia.com/drupal-support" target="_blank">' . render($image) . '</a>';
    $output .= '<p class="cell-p">' . $this->t("Experienced Drupalists are available to support you whenever you need it.") . '</p>';
    $output .= '</div>';
    $output .= '</div>';
    $output .= '</div>';
    $output .= '<div class="an-pg-banner">';
    $output .= $banner;
    $output .= '</div>';
    $output .= '</div>';
    $output .= '</div>';
    $build['output'] = [
      '#markup' => $output,
    ];

    return $build;
  }

}
