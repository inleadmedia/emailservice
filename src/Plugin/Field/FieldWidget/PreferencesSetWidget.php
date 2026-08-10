<?php

namespace Drupal\emailservice\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\emailservice\Services\LmsRequestService;
use Drupal\taxonomy\Entity\Term;
use GuzzleHttp\Client;

/**
 * Plugin implementation of the 'preferences_set_widget' widget.
 *
 * @FieldWidget(
 *   id = "preferences_set_widget",
 *   label = @Translation("Preferences set widget"),
 *   field_types = {
 *     "preferences_set_field_type"
 *   }
 * )
 */
class PreferencesSetWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    return parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $item =& $items[$delta];

    $element = [
      '#type' => 'fieldset',
      '#title' => t('Preferences Set'),
      '#open' => TRUE,
    ];

    $element['label'] = [
      '#type' => 'textfield',
      '#title' => t('Label'),
      '#default_value' => $item->label ?? NULL,
      // Make label readonly if it already exists (editing existing item).
      '#disabled' => !empty($item->label),
      '#attributes' => !empty($item->label) ? ['readonly' => 'readonly'] : [],
    ];

    $element['machine_name'] = [
      '#type' => 'hidden',
      '#value' => $item->machine_name ?? NULL,
    ];

    $element['cql_query'] = [
      '#type' => 'textarea',
      '#title' => t('CQL Query'),
      '#default_value' => $item->cql_query ?? NULL,
      // Store original value to detect changes
      '#original_value' => $item->cql_query ?? NULL,
      '#element_validate' => [
        [static::class, 'validate'],
      ],
    ];

    $element['status'] = [
      '#type' => 'hidden',
      '#default_value' => $item->status ?? NULL,
    ];

    $options = [];
    $vid = 'types_materials';
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vid);
    foreach ($terms as $term) {
      $options[$term->tid] = $term->name;
    }

    $element['material_tid'] = [
      '#type' => 'select',
      '#title' => t('Related material type'),
      '#empty_option' => t('Choose type'),
      '#options' => $options,
      '#default_value' => $item->material_tid ?? NULL,
      '#required' => FALSE,
    ];

    return $element;
  }

  /**
   * {@inheritDoc}
   */
  public static function validate($element, FormStateInterface $form_state) {
    $cql_query = $element['#value'];
    $original_value = $element['#original_value'] ?? NULL;
    $alias = $form_state->get('municipality_alias');
    $delta = $element['#parents'][1] ?? 'unknown';

    // Skip validation on local/development environments
    if (isset($_SERVER['HTTP_HOST']) && (
      strpos($_SERVER['HTTP_HOST'], 'ddev.site') !== FALSE ||
      strpos($_SERVER['HTTP_HOST'], '.stg.') !== FALSE ||
      strpos($_SERVER['HTTP_HOST'], 'staging') !== FALSE
    )) {
      return;
    }

    // Skip validation if there's no CQL query to validate.
    if (empty($cql_query)) {
      return;
    }

    // Skip validation if the item is being removed (empty label).
    $categories_field = $form_state->getValue('field_types_categories');
    if (empty($categories_field[$delta]['label'])) {
      return;
    }

    // Skip validation if the CQL query hasn't changed.
    if ($cql_query === $original_value) {
      return;
    }

    // Check cache first.
    $cache = \Drupal::cache()->get('cql_validate.' . sha1($cql_query));
    if (!empty($cache) && $cache->data == 'valid') {
      return;
    }

    $url = \Drupal::config('emailservice.lms')->get('lms_api_url');

    $material_tid = $categories_field[$delta]['material_tid'];
    $type = Term::load($material_tid)->get('field_types_cql_query')->value;

    // Get materials count from the node, or use default.
    $materials_count = LmsRequestService::MATERIAL_COUNT_DEFAULT;
    $build_info = $form_state->getBuildInfo();
    if (isset($build_info['callback_object'])) {
      $node = $build_info['callback_object']->getEntity();
      if ($node && $node->hasField('field_materials_count') && !$node->get('field_materials_count')->isEmpty()) {
        $materials_count = $node->get('field_materials_count')->value;
      }
    }

    $query = "/search?query=(($type) AND ($cql_query)) AND term.acSource=\"bibliotekskatalog\" AND holdingsitem.accessionDate>=\"NOW-7DAYS\"&step=" . $materials_count;
    $uri = $url . $alias . $query;
    try {
      $request = new Client();
      $request->get($uri);
      \Drupal::cache()->set('cql_validate.' . sha1($cql_query), 'valid');
    }
    catch (\Exception $e) {
      $form_state->setError($element, t('There are errors in search string. Please correct this.'));
      \Drupal::logger('emailservice')->error($e->getMessage());
    }
  }

}
