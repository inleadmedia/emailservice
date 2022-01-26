<?php

namespace Drupal\emailservice\Helpers;

use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;

class PreferencesSetHelper {

  /**
   * Helper for generating machine name.
   *
   * @param object $node
   *   Node on which we are acting.
   * @param string $label
   *   The label of preference which have to be added.
   * @param int $tid
   *   Term id.
   *
   * @return string
   *   Generated machine name for category key.
   */
  public static function generateMachineName($node, $label, $tid) {
    $user = $node->get('uid')->target_id;
    $loaded_user = User::load($user);

    $prefix = $loaded_user->get('field_alias')->value;

    $term = Term::load($tid);
    $material_type = self::lowerDanishTerms($term->getName());

    $machine_name = self::lowerDanishTerms($label);

    return $prefix . '_' . $material_type . '-' . $machine_name;
  }

  /**
   * Lowerize words.
   *
   * @param string $term
   *   String to be lowerized.
   *
   * @return string|string[]|null
   *   Lowerized string.
   */
  private static function lowerDanishTerms(string $term) {
    $term_name = mb_strtolower($term, 'UTF-8');
    return preg_replace('@[^a-zæøå0-9-]+@', '-', strtolower($term_name));
  }
}
