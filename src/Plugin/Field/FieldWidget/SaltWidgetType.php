<?php

namespace Drupal\emailservice\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Plugin implementation of the 'salt_widget_type' widget.
 *
 * @FieldWidget(
 *   id = "salt_widget_type",
 *   label = @Translation("Salt widget type"),
 *   field_types = {
 *     "salt_field_type"
 *   }
 * )
 */
class SaltWidgetType extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $current_user_roles = \Drupal::currentUser()->getRoles();

    $element['value'] = $element + [
      '#type' => 'textfield',
      '#default_value' => isset($items[$delta]->value) ? $items[$delta]->value : '1234',
      '#prefix' => '<div id="emailservice_salt_field">',
      '#suffix' => '</div>',
    ];

    if (in_array('administrator', $current_user_roles)) {
      $element['link'] = [
        '#type' => 'link',
        '#title' => 'Generate',
        '#url' => Url::fromRoute('emailservice.generate_salt'),
        '#attributes' => [
          'class' => [
            'use-ajax',
          ],
        ],
      ];
    }
    else {
      $element['value']['#attributes']['disabled'] = TRUE;
    }

    return $element;
  }

}
