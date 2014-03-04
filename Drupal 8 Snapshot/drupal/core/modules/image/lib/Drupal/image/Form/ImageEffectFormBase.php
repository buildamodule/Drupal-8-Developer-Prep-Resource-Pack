<?php

/**
 * @file
 * Contains \Drupal\image\Form\ImageEffectFormBase.
 */

namespace Drupal\image\Form;

use Drupal\Core\Form\FormInterface;
use Drupal\image\ConfigurableImageEffectInterface;
use Drupal\image\ImageStyleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides a base form for image effects.
 */
abstract class ImageEffectFormBase implements FormInterface {

  /**
   * The image style.
   *
   * @var \Drupal\image\ImageStyleInterface
   */
  protected $imageStyle;

  /**
   * The image effect.
   *
   * @var \Drupal\image\ImageEffectInterface
   */
  protected $imageEffect;

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'image_effect_form';
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\image\ImageStyleInterface $image_style
   *   The image style.
   * @param string $image_effect
   *   The image effect ID.
   *
   * @return array
   *   The form structure.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   */
  public function buildForm(array $form, array &$form_state, Request $request = NULL, ImageStyleInterface $image_style = NULL, $image_effect = NULL) {
    $this->imageStyle = $image_style;
    $this->imageEffect = $this->prepareImageEffect($image_effect);

    if (!($this->imageEffect instanceof ConfigurableImageEffectInterface)) {
      throw new NotFoundHttpException();
    }

    $form['#attached']['css'][drupal_get_path('module', 'image') . '/css/image.admin.css'] = array();
    $form['uuid'] = array(
      '#type' => 'value',
      '#value' => $this->imageEffect->getUuid(),
    );
    $form['id'] = array(
      '#type' => 'value',
      '#value' => $this->imageEffect->getPluginId(),
    );

    $form['data'] = $this->imageEffect->getForm();
    $form['data']['#tree'] = TRUE;

    // Check the URL for a weight, then the image effect, otherwise use default.
    $form['weight'] = array(
      '#type' => 'hidden',
      '#value' => $request->query->has('weight') ? (int) $request->query->get('weight') : $this->imageEffect->getWeight(),
    );

    $form['actions'] = array('#type' => 'actions');
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#button_type' => 'primary',
    );
    $form['actions']['cancel'] = array(
      '#type' => 'link',
      '#title' => t('Cancel'),
      '#href' => 'admin/config/media/image-styles/manage/' . $this->imageStyle->id(),
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, array &$form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    form_state_values_clean($form_state);
    $this->imageStyle->saveImageEffect($form_state['values']);

    drupal_set_message(t('The image effect was successfully applied.'));
    $form_state['redirect'] = 'admin/config/media/image-styles/manage/' . $this->imageStyle->id();
  }

  /**
   * Converts an image effect ID into an object.
   *
   * @param string $image_effect
   *   The image effect ID.
   *
   * @return \Drupal\image\ImageEffectInterface
   *   The image effect object.
   */
  abstract protected function prepareImageEffect($image_effect);

}
