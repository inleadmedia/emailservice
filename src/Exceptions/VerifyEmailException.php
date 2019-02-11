<?php

/**
 * @file
 * VerifyEmail Exception.
 */

/**
 * VerifyEmail exception handler.
 */
class VerifyEmailException extends Exception {

  /**
   * Prettify error message output.
   *
   * @return string
   *   Error message.
   */
  public function errorMessage() {
    $message = $this->getMessage();
    return $message;
  }

}
